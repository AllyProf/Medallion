<?php

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Models\StaffItemShortage;
use App\Models\ProductVariant;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Staff;
use App\Models\WaiterDailyReconciliation;
use App\Models\BarShift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StaffItemShortageController extends Controller
{
    /**
     * Get active business owner ID
     */
    private function getOwnerId()
    {
        if (session('is_staff') && session('staff_id')) {
            $staff = Staff::find(session('staff_id'));
            return $staff ? $staff->user_id : auth()->id();
        }
        return auth()->id();
    }

    /**
     * Display the Manager/Accountant Item Shortages dashboard
     */
    public function index(Request $request)
    {
        $ownerId = $this->getOwnerId();

        // Start base query
        $query = StaffItemShortage::where('user_id', $ownerId);

        // Apply filters
        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
        }

        $shortages = $query->with(['staff', 'productVariant.product', 'recorder', 'recorderStaff', 'approvedBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Grand totals for cards (respecting filters but independent of pagination)
        $totalsQuery = StaffItemShortage::where('user_id', $ownerId);
        if ($request->filled('staff_id')) $totalsQuery->where('staff_id', $request->staff_id);
        if ($request->filled('status')) $totalsQuery->where('status', $request->status);
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $totalsQuery->whereBetween('created_at', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
        }

        $totalItemsShort = $totalsQuery->sum('quantity_short');
        $totalMoneyInSupply = $totalsQuery->sum('money_in_supply');
        $totalExpectedRevenue = $totalsQuery->sum('expected_revenue');
        $totalLostProfit = $totalsQuery->sum('lost_profit');

        // Fetch staff and variants for modals/filters
        $staffMembers = Staff::where('user_id', $ownerId)->orderBy('full_name')->get();
        
        $variants = ProductVariant::whereHas('product', function ($q) use ($ownerId) {
            $q->where('user_id', $ownerId);
        })->with(['product', 'counterStock' => function ($query) use ($ownerId) {
            $query->where('user_id', $ownerId);
        }])->get()->map(function($pv) {
            $pv->counter_qty = $pv->counterStock ? (float)$pv->counterStock->quantity : 0.0;
            return $pv;
        });

        return view('manager.stock.shortages.index', compact(
            'shortages',
            'totalItemsShort',
            'totalMoneyInSupply',
            'totalExpectedRevenue',
            'totalLostProfit',
            'staffMembers',
            'variants'
        ));
    }

    /**
     * Record a new Item Shortage (Counter/Manager AJAX submission)
     */
    public function store(Request $request)
    {
        $ownerId = $this->getOwnerId();

        $validated = $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id',
            'physical_count' => 'required|numeric|min:0',
            'notes' => 'required|string|max:500',
            'staff_id' => 'nullable|exists:staff,id', // Can be passed manually by manager
        ], [
            'notes.required' => 'Please provide an explanation/note for this shortage.',
        ]);

        $variant = ProductVariant::findOrFail($validated['product_variant_id']);
        
        // Load the counter stock location
        $counterStock = StockLocation::where('user_id', $ownerId)
            ->where('product_variant_id', $variant->id)
            ->where('location', 'counter')
            ->first();

        if (!$counterStock) {
            $counterStock = StockLocation::create([
                'user_id' => $ownerId,
                'product_variant_id' => $variant->id,
                'location' => 'counter',
                'quantity' => 0.0,
                'average_buying_price' => $variant->buying_price_per_unit ?? 0.0,
                'selling_price' => $variant->selling_price_per_unit ?? 0.0,
            ]);
        }

        $expectedQty = (float) $counterStock->quantity;
        $physicalCount = (float) $validated['physical_count'];

        if ($physicalCount >= $expectedQty) {
            return response()->json(['error' => 'Physical count must be less than current expected stock to record a shortage.'], 400);
        }

        $quantityShort = $expectedQty - $physicalCount;

        // Financial Pricing Logic
        $buyingPrice = (float) ($counterStock->average_buying_price ?? $variant->buying_price_per_unit ?? 0);
        $sellingPrice = (float) ($counterStock->selling_price ?? $variant->selling_price_per_unit ?? 0);

        $expectedRevenue = $quantityShort * $sellingPrice;
        $moneyInSupply = $quantityShort * $buyingPrice;
        $lostProfit = $expectedRevenue - $moneyInSupply;

        // Auto-Attribution Logic if not provided manually
        $staffId = $validated['staff_id'] ?? null;
        $barShiftId = null;

        if (!$staffId) {
            // Find the most recent shift at this location branch to attribute the shortage to
            $currentStaff = Staff::find(session('staff_id'));
            $branch = $currentStaff ? $currentStaff->location_branch : 'Counter';

            // Find previous closed shift at this branch
            $prevShift = BarShift::where('user_id', $ownerId)
                ->where('location_branch', $branch)
                ->where('status', 'closed')
                ->orderBy('closed_at', 'desc')
                ->first();

            if ($prevShift) {
                $staffId = $prevShift->staff_id;
                $barShiftId = $prevShift->id;
            } else {
                // Fallback to active/open shift if there is no previous closed shift
                $activeShift = BarShift::where('user_id', $ownerId)
                    ->where('location_branch', $branch)
                    ->where('status', 'open')
                    ->first();
                
                if ($activeShift) {
                    $staffId = $activeShift->staff_id;
                    $barShiftId = $activeShift->id;
                }
            }
        }

        // Determine initial status based on who is recording this shortage
        $recordingStaffId   = session('staff_id') ?? null;
        $recordingStaff     = $recordingStaffId ? Staff::with('role')->find($recordingStaffId) : null;
        $recordingRoleSlug  = strtolower($recordingStaff?->role?->slug ?? $recordingStaff?->role?->name ?? '');

        // Managers and accountants pre-approve when they record (no extra Approve step needed)
        // Counter staff, waiters, chefs, stock keepers NEED manager approval
        $managementRoles    = ['manager', 'accountant', 'hr-manager'];
        $isManagementRole   = in_array($recordingRoleSlug, $managementRoles);

        // Counter staff: status starts 'pending' → needs manager approval
        // Management staff: status starts 'approved' → Charge/Waive directly
        $initialStatus          = $isManagementRole ? 'approved' : 'pending';
        $initialApprovedBy      = $isManagementRole ? $recordingStaffId : null;
        $initialApprovedAt      = $isManagementRole ? now() : null;

        DB::beginTransaction();
        try {
            // 1. Decrement the Counter Stock (Adjust to Physical Count)
            $counterStock->quantity = $physicalCount;
            $counterStock->save();

            // Check alerts
            try {
                if (class_exists(\App\Services\StockAlertService::class)) {
                    app(\App\Services\StockAlertService::class)->checkCounterStock($variant->id, $ownerId);
                }
            } catch (\Exception $e) {
                Log::error('StockAlertService failed during shortage recording: ' . $e->getMessage());
            }

            // 2. Create the Shortage Record
            $shortage = StaffItemShortage::create([
                'user_id'              => $ownerId,
                'staff_id'             => $staffId,
                'product_variant_id'   => $variant->id,
                'bar_shift_id'         => $barShiftId,
                'quantity_short'       => $quantityShort,
                'buying_price'         => $buyingPrice,
                'selling_price'        => $sellingPrice,
                'expected_revenue'     => $expectedRevenue,
                'money_in_supply'      => $moneyInSupply,
                'lost_profit'          => $lostProfit,
                'status'               => $initialStatus,
                'notes'                => $validated['notes'] ?? 'Discrepancy reported during stock review.',
                'recorded_by'          => auth()->id() ?? $ownerId,
                'recorded_by_staff_id' => $recordingStaffId,
                'approved_by_staff_id' => $initialApprovedBy,
                'approved_at'          => $initialApprovedAt,
            ]);

            // 3. Create StockMovement for auditing
            StockMovement::create([
                'user_id' => $ownerId,
                'product_variant_id' => $variant->id,
                'movement_type' => 'adjustment',
                'from_location' => 'counter',
                'to_location' => null,
                'quantity' => $quantityShort,
                'unit_price' => $buyingPrice,
                'reference_type' => StaffItemShortage::class,
                'reference_id' => $shortage->id,
                'created_by' => auth()->id() ?? $ownerId,
                'notes' => 'Staff item shortage: ' . ($shortage->notes ?? ''),
            ]);

            DB::commit();

            // 4. Send SMS to the attributed staff if they have a phone number
            if ($shortage->staff && $shortage->staff->phone_number) {
                try {
                    $smsService = new \App\Services\SmsService();
                    $firstName = explode(' ', trim($shortage->staff->full_name))[0];
                    $smsMsg = "Habari " . $firstName . ", umepungukiwa chupa " . number_format($quantityShort) . " za " . $variant->display_name . ". Gharama yake ni TSh " . number_format($expectedRevenue) . ". Tafadhali wasiliana na uongozi kwa reconciliation. Medallion.";
                    $smsService->sendSms($shortage->staff->phone_number, $smsMsg);
                } catch (\Exception $smsEx) {
                    Log::error('Failed to send shortage SMS notification to waiter: ' . $smsEx->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Shortage of ' . number_format($quantityShort) . ' units successfully recorded and stock adjusted.',
                'shortage' => $shortage->load('staff')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to save staff item shortage: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to record shortage: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Approve / Acknowledge a shortage (Manager action — before charging or waiving)
     */
    public function approve($id)
    {
        $ownerId = $this->getOwnerId();
        $shortage = StaffItemShortage::where('user_id', $ownerId)->findOrFail($id);

        if ($shortage->status !== 'pending') {
            return response()->json(['error' => 'Only pending shortages can be approved.'], 400);
        }

        // Record who approved and when
        $approverStaffId = session('staff_id') ?? null;

        $shortage->status              = 'approved';
        $shortage->approved_by_staff_id = $approverStaffId;
        $shortage->approved_at         = now();
        $shortage->save();

        $approverName = $approverStaffId ? (Staff::find($approverStaffId)?->full_name ?? 'Manager') : 'Manager';

        return response()->json([
            'success' => true,
            'message' => "Shortage approved by {$approverName}. You can now Charge or Waive it."
        ]);
    }

    /**
     * Waive / Excuse an Item Shortage
     */
    public function waive($id)
    {
        $ownerId = $this->getOwnerId();
        $shortage = StaffItemShortage::where('user_id', $ownerId)->findOrFail($id);

        if (!in_array($shortage->status, ['pending', 'approved'])) {
            return response()->json(['error' => 'This shortage has already been processed.'], 400);
        }

        $shortage->status = 'waived';
        $shortage->save();

        return response()->json([
            'success' => true,
            'message' => 'Shortage has been successfully excused and waived.'
        ]);
    }

    /**
     * Charge an Item Shortage to Staff (Converts to Financial Cash Debt)
     */
    public function charge($id)
    {
        $ownerId = $this->getOwnerId();
        $shortage = StaffItemShortage::where('user_id', $ownerId)->findOrFail($id);

        if (!in_array($shortage->status, ['pending', 'approved'])) {
            return response()->json(['error' => 'This shortage has already been processed.'], 400);
        }

        if (!$shortage->staff_id) {
            return response()->json(['error' => 'Cannot charge shortage because no responsible staff member is attributed.'], 400);
        }

        DB::beginTransaction();
        try {
            // Find active reconciliation for the staff member
            // Search either matching the shift context, or the most recent unverified daily reconciliation
            $reconciliation = null;
            
            if ($shortage->bar_shift_id) {
                $reconciliation = WaiterDailyReconciliation::where('user_id', $ownerId)
                    ->where('waiter_id', $shortage->staff_id)
                    ->where('bar_shift_id', $shortage->bar_shift_id)
                    ->first();
            }

            if (!$reconciliation) {
                $reconciliation = WaiterDailyReconciliation::where('user_id', $ownerId)
                    ->where('waiter_id', $shortage->staff_id)
                    ->where('status', '!=', 'verified')
                    ->orderBy('created_at', 'desc')
                    ->first();
            }

            // If absolutely no pending daily reconciliation exists, create a new manual reconciliation record to hold this debt!
            if (!$reconciliation) {
                $reconciliation = WaiterDailyReconciliation::create([
                    'user_id' => $ownerId,
                    'waiter_id' => $shortage->staff_id,
                    'reconciliation_date' => now()->format('Y-m-d'),
                    'reconciliation_type' => 'bar',
                    'total_sales' => 0,
                    'cash_collected' => 0,
                    'expected_amount' => 0,
                    'submitted_amount' => 0,
                    'difference' => 0,
                    'status' => 'submitted',
                    'notes' => json_encode(['legacy_notes' => 'Shortage Auto Reconciliation Ledger']),
                ]);
            }

            $chargeValue = (float) $shortage->expected_revenue;

            // 1. Charge the staff member by adding to reconciliation's expected amount
            $reconciliation->expected_amount += $chargeValue;
            $reconciliation->difference = $reconciliation->submitted_amount - $reconciliation->expected_amount;
            
            // Append detail history to notes
            $notesData = json_decode($reconciliation->notes, true) ?: [];
            if (!is_array($notesData)) $notesData = ['legacy_notes' => $reconciliation->notes];
            
            $notesData['item_shortage_charges'][] = [
                'shortage_id' => $shortage->id,
                'variant_id' => $shortage->product_variant_id,
                'variant_name' => $shortage->productVariant->display_name ?? 'N/A',
                'qty' => $shortage->quantity_short,
                'charge_amount' => $chargeValue,
                'date' => now()->toDateTimeString(),
            ];
            
            $reconciliation->notes = json_encode($notesData);
            $reconciliation->save();

            // 2. Mark the shortage status as charged
            $shortage->status = 'charged';
            $shortage->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Shortage successfully converted to financial cash shortage and charged to ' . $shortage->staff->full_name . '.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to charge staff item shortage: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to charge staff: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Undo and delete a recorded shortage, restoring stock levels.
     */
    public function undo($id)
    {
        $ownerId = $this->getOwnerId();
        $shortage = StaffItemShortage::where('user_id', $ownerId)->findOrFail($id);

        DB::beginTransaction();
        try {
            // 1. Restore Counter Stock
            $counterStock = StockLocation::where('user_id', $ownerId)
                ->where('product_variant_id', $shortage->product_variant_id)
                ->where('location', 'counter')
                ->first();

            if ($counterStock) {
                $counterStock->quantity += $shortage->quantity_short;
                $counterStock->save();
            }

            // 2. Remove or delete the StockMovement created for this shortage
            StockMovement::where('user_id', $ownerId)
                ->where('reference_type', StaffItemShortage::class)
                ->where('reference_id', $shortage->id)
                ->delete();

            // 3. If it was already charged as debt, remove it from waiter daily reconciliation
            if ($shortage->status === 'charged' && $shortage->staff_id) {
                $reconciliations = \App\Models\WaiterDailyReconciliation::where('user_id', $ownerId)
                    ->where('waiter_id', $shortage->staff_id)
                    ->get();
                
                foreach ($reconciliations as $reconciliation) {
                    $notesData = json_decode($reconciliation->notes, true) ?: [];
                    if (is_array($notesData) && isset($notesData['item_shortage_charges'])) {
                        $charges = $notesData['item_shortage_charges'];
                        $foundKey = null;
                        foreach ($charges as $key => $charge) {
                            if ($charge['shortage_id'] == $shortage->id) {
                                $foundKey = $key;
                                break;
                            }
                        }
                        
                        if ($foundKey !== null) {
                            // Deduct the charged amount from expected_amount
                            $reconciliation->expected_amount = max(0, $reconciliation->expected_amount - $shortage->expected_revenue);
                            $reconciliation->difference = $reconciliation->submitted_amount - $reconciliation->expected_amount;
                            
                            // Remove the charge from notes history
                            unset($charges[$foundKey]);
                            $notesData['item_shortage_charges'] = array_values($charges);
                            $reconciliation->notes = json_encode($notesData);
                            $reconciliation->save();
                            break;
                        }
                    }
                }
            }

            // 4. Delete the shortage record itself
            $shortage->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Shortage successfully undone! Stock level has been restored.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to undo staff item shortage: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to undo shortage record: ' . $e->getMessage()], 500);
        }
    }
}
