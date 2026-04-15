<?php

namespace App\Http\Controllers\Bar;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesStaffPermissions;
use App\Models\StockReceipt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\StockReceiptSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockReceiptController extends Controller
{
    use HandlesStaffPermissions;
    /**
     * Display a listing of stock receipts.
     */
    public function index()
    {
        // Check permission
        if (!$this->hasPermission('stock_receipt', 'view')) {
            abort(403, 'You do not have permission to view stock receipts.');
        }

        $ownerId = $this->getOwnerId();
        
        // Group by receipt_number to show deliveries rather than individual items
        $receipts = StockReceipt::where('stock_receipts.user_id', $ownerId)
            ->leftJoin('product_variants', 'stock_receipts.product_variant_id', '=', 'product_variants.id')
            ->select('stock_receipts.receipt_number', 'stock_receipts.supplier_id', 'stock_receipts.received_date', 'stock_receipts.notes', 'stock_receipts.received_by')
            ->selectRaw('count(stock_receipts.id) as item_count')
            ->selectRaw('sum(stock_receipts.total_units) as total_units_sum')
            ->selectRaw('sum(stock_receipts.quantity_received) as total_packages_sum')
            ->selectRaw('sum(stock_receipts.final_buying_cost) as total_cost_sum')
            ->selectRaw('sum(stock_receipts.total_profit) as total_profit_sum')
            ->selectRaw('CASE WHEN count(distinct product_variants.packaging) = 1 THEN MAX(product_variants.packaging) ELSE "Mixed" END as pkg_label')
            ->groupBy('stock_receipts.receipt_number', 'stock_receipts.supplier_id', 'stock_receipts.received_date', 'stock_receipts.notes', 'stock_receipts.received_by')
            ->with(['supplier', 'receivedBy'])
            ->orderBy('received_date', 'desc')
            ->paginate(20);

        // Role-based visibility: Hide revenue/profit for Stock Keepers
        $showRevenue = true;
        if (session('is_staff')) {
            $staff = \App\Models\Staff::with('role')->find(session('staff_id'));
            if ($staff && $staff->role) {
                $roleName = strtolower(trim($staff->role->name ?? ''));
                if (in_array($roleName, ['stock keeper', 'stockkeeper'])) {
                    $showRevenue = false;
                }
            }
        }

        return view('bar.stock-receipts.index', compact('receipts', 'showRevenue'));
    }

    /**
     * Show the form for creating a new stock receipt.
     */
    public function create()
    {
        // Check permission
        if (!$this->hasPermission('stock_receipt', 'create')) {
            abort(403, 'You do not have permission to create stock receipts.');
        }

        $ownerId = $this->getOwnerId();

        $suppliers = Supplier::where('user_id', $ownerId)
            ->where('is_active', true)
            ->orderBy('company_name')
            ->get();

        $products = Product::where('user_id', $ownerId)
            ->where('is_active', true)
            ->with(['variants' => function($q) {
                $q->where('is_active', true);
            }])
            ->orderBy('name')
            ->get();

        // Get all unique active brands as Distributors
        $distributorGroups = $products->pluck('brand')
            ->map(fn($b) => strtoupper(trim($b)))
            ->unique()
            ->filter()
            ->sort()
            ->values()
            ->all();

        // Preparing productsData for JavaScript
        $productsData = $products->map(function($product) use ($ownerId) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'brand' => $product->brand,
                'category' => $product->category,
                'variants' => $product->variants->map(function($variant) use ($ownerId) {
                    $warehouseStock = StockLocation::where('user_id', $ownerId)
                        ->where('product_variant_id', $variant->id)
                        ->where('location', 'warehouse')
                        ->first();
                    
                    $lastReceipt = StockReceipt::where('user_id', $ownerId)
                        ->where('product_variant_id', $variant->id)
                        ->latest('received_date')
                        ->first();
                    
                    $existingQuantity = $warehouseStock ? (float)$warehouseStock->quantity : 0;
                    $itemsPerPackage = $variant->items_per_package ?? 1;
                    $existingPackages = $itemsPerPackage > 0 ? floor($existingQuantity / $itemsPerPackage) : 0;
                    
                    return [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'measurement' => $variant->measurement,
                        'packaging' => $variant->packaging,
                        'unit' => $variant->inventory_unit,
                        'items_per_package' => $variant->items_per_package,
                        'selling_type' => $variant->selling_type,
                        'buying_price_per_unit' => $variant->buying_price_per_unit ? (float)$variant->buying_price_per_unit : null,
                        'selling_price_per_unit' => $variant->selling_price_per_unit ? (float)$variant->selling_price_per_unit : null,
                        'can_sell_in_tots' => $variant->can_sell_in_tots,
                        'selling_price_per_tot' => $variant->selling_price_per_tot ? (float)$variant->selling_price_per_tot : null,
                        'existing_quantity' => $existingQuantity,
                        'existing_packages' => $existingPackages,
                        'average_buying_price' => $warehouseStock ? (float)$warehouseStock->average_buying_price : ($variant->buying_price_per_unit ? (float)$variant->buying_price_per_unit : 0),
                        'last_supplier_id' => $lastReceipt ? $lastReceipt->supplier_id : null,
                    ];
                })->values()->all()
            ];
        })->all();

        // Role-based visibility: Hide revenue/profit for Stock Keepers
        // Owners (non-staff) and Accountants will always see full details.
        $showRevenue = true;
        if (session('is_staff')) {
            $staff = \App\Models\Staff::with('role')->find(session('staff_id'));
            if ($staff && $staff->role) {
                $roleName = strtolower(trim($staff->role->name ?? ''));
                // ONLY hide for stock keepers. Accountants and others should see it.
                if (in_array($roleName, ['stock keeper', 'stockkeeper'])) {
                    $showRevenue = false;
                }
            }
        }

        return view('bar.stock-receipts.create', compact('suppliers', 'products', 'productsData', 'distributorGroups', 'showRevenue'));
    }


    /**
     * Store a newly created stock receipt.
     */
    public function store(Request $request)
    {
        // Check permission
        if (!$this->hasPermission('stock_receipt', 'create')) {
            abort(403, 'You do not have permission to create stock receipts.');
        }

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'received_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_variant_id' => 'required|exists:product_variants,id',
            'items.*.quantity_received' => 'required|numeric|min:0',
            'items.*.loose_received' => 'nullable|numeric|min:0',
            'items.*.buying_price_per_unit' => 'required|numeric|min:0',
            'items.*.buying_price_mode' => 'nullable|string|in:pkg,unit',
            'items.*.selling_price_per_unit' => 'required|numeric|min:0',
            'items.*.selling_price_per_tot' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|string|in:fixed,percent',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.expiry_date' => 'nullable|date|after:received_date',
        ]);

        $ownerId = $this->getOwnerId();

        // Verify supplier belongs to user
        $supplier = Supplier::where('id', $validated['supplier_id'])
            ->where('user_id', $ownerId)
            ->first();

        if (!$supplier) {
            return back()->withErrors(['supplier_id' => 'Invalid supplier selected.'])->withInput();
        }

        DB::beginTransaction();
        try {
            // Generate ONE receipt number for the entire batch
            $receiptNumber = StockReceipt::generateReceiptNumber($ownerId);
            $receiptsCount = 0;
            $lastCreatedReceipt = null;

            foreach ($validated['items'] as $item) {
                // Verify product variant belongs to user
                $productVariant = ProductVariant::where('id', $item['product_variant_id'])
                    ->whereHas('product', function($query) use ($ownerId) {
                        $query->where('user_id', $ownerId);
                    })
                    ->first();

                if (!$productVariant) continue;
                if ($item['quantity_received'] <= 0 && ($item['loose_received'] ?? 0) <= 0) continue;

                // Calculate values
                // quantity_received from frontend is the number of PACKAGES (crates/cartons)
                $packagesOnly = $item['quantity_received'];
                $looseOnly = $item['loose_received'] ?? 0;
                $itemsPerPackage = $productVariant->items_per_package ?? 1;
                
                $totalUnits = ($packagesOnly * $itemsPerPackage) + $looseOnly;
                $numPackages = $packagesOnly + ($looseOnly / $itemsPerPackage);

                $inputPrice = $item['buying_price_per_unit'];
                $priceMode = $item['buying_price_mode'] ?? 'pkg';
                $totalBuyingCost = 0;

                if ($priceMode === 'unit') {
                    $totalBuyingCost = $totalUnits * $inputPrice;
                } else {
                    // Per package mode (standard)
                    $totalBuyingCost = $numPackages * $inputPrice;
                }
                
                // Actual unit buying price for stock valuation and DB storage
                $actualUnitBuyingPrice = $totalUnits > 0 ? ($totalBuyingCost / $totalUnits) : $inputPrice;
                
                // Calculate smart selling value (following frontend logic: use tot revenue if available)
                $bottleSellingValue = $totalUnits * $item['selling_price_per_unit'];
                $totSellingValue = 0;
                $totPrice = $item['selling_price_per_tot'] ?? 0;
                
                if ($totPrice > 0 && ($productVariant->total_tots ?? 0) > 0) {
                    $totSellingValue = $totalUnits * $productVariant->total_tots * $totPrice;
                }
                
                $totalSellingValue = ($totSellingValue > 0) ? $totSellingValue : $bottleSellingValue;
                $profitPerUnit = $totalUnits > 0 ? ($totalSellingValue - $totalBuyingCost) / $totalUnits : 0;
                $totalProfit = $totalSellingValue - $totalBuyingCost;
                
                // Calculate discount
                $discountType = $item['discount_type'] ?? null;
                $discountAmount = $item['discount_amount'] ?? 0;
                $discountValue = 0;
                $finalBuyingCost = $totalBuyingCost;
                
                if ($discountType && $discountAmount > 0) {
                    if ($discountType === 'fixed') {
                        $discountValue = min($discountAmount, $totalBuyingCost);
                        $finalBuyingCost = $totalBuyingCost - $discountValue;
                    } elseif ($discountType === 'percent') {
                        $discountValue = ($totalBuyingCost * $discountAmount) / 100;
                        $finalBuyingCost = $totalBuyingCost - $discountValue;
                    }
                }

                // Create stock receipt record
                $receipt = StockReceipt::create([
                    'user_id' => $ownerId,
                    'product_variant_id' => $item['product_variant_id'],
                    'supplier_id' => $validated['supplier_id'],
                    'receipt_number' => $receiptNumber,
                    'quantity_received' => $numPackages,
                    'total_units' => $totalUnits,
                    'buying_price_per_unit' => $actualUnitBuyingPrice,
                    'selling_price_per_unit' => $item['selling_price_per_unit'],
                    'selling_price_per_tot' => $item['selling_price_per_tot'] ?? 0,
                    'total_buying_cost' => $totalBuyingCost,
                    'total_selling_value' => $totalSellingValue,
                    'profit_per_unit' => $profitPerUnit,
                    'total_profit' => $totalProfit,
                    'discount_type' => $discountType,
                    'discount_amount' => $discountAmount,
                    'discount_value' => $discountValue,
                    'final_buying_cost' => $finalBuyingCost,
                    'received_date' => $validated['received_date'],
                    'notes' => $validated['notes'],
                    'received_by' => Auth::id() ?? $ownerId,
                    'received_by_staff_id' => session('is_staff') ? session('staff_id') : null,
                ]);

                // Update Warehouse Stock (With Weighted Average Costing)
                $warehouseStock = \App\Models\StockLocation::firstOrCreate(
                    [
                        'user_id' => $ownerId,
                        'product_variant_id' => $item['product_variant_id'],
                        'location' => 'warehouse',
                    ],
                    [
                        'quantity' => 0,
                        'average_buying_price' => $actualUnitBuyingPrice,
                        'selling_price' => $item['selling_price_per_unit'],
                        'selling_price_per_tot' => $item['selling_price_per_tot'] ?? 0,
                    ]
                );

                // Weighted Average Buying Price Formula:
                // ((Existing Qty * Current Ave) + (New Qty * New Cost)) / (Existing Qty + New Qty)
                $existingQty = $warehouseStock->quantity;
                $currentAve = $warehouseStock->average_buying_price;
                $newCost = $actualUnitBuyingPrice;
                $newQty = $totalUnits;

                $newAveragePrice = (($existingQty * $currentAve) + ($newQty * $newCost)) / ($existingQty + $newQty);

                $warehouseStock->update([
                    'quantity' => $existingQty + $newQty,
                    'average_buying_price' => $newAveragePrice,
                    'selling_price' => $item['selling_price_per_unit'], // Use newest selling price
                    'selling_price_per_tot' => $item['selling_price_per_tot'] ?? 0,
                ]);

                // Update Master Product Variant prices to reflect latest delivery prices
                $productVariant->update([
                    'buying_price_per_unit' => $actualUnitBuyingPrice,
                    'selling_price_per_unit' => $item['selling_price_per_unit'],
                    'selling_price_per_tot' => $item['selling_price_per_tot'] ?? 0,
                    'can_sell_in_tots' => ($item['selling_price_per_tot'] ?? 0) > 0,
                ]);

                // Log Stock Movement
                \App\Models\StockMovement::create([
                    'user_id' => $ownerId,
                    'product_variant_id' => $item['product_variant_id'],
                    'from_location' => 'supplier',
                    'to_location' => 'warehouse',
                    'quantity' => $totalUnits,
                    'type' => 'in',
                    'reference_type' => 'stock_receipt',
                    'reference_id' => $receipt->id,
                    'notes' => "Stock received from {$supplier->company_name}. Receipt #{$receiptNumber}",
                ]);

                $receiptsCount++;
                $lastCreatedReceipt = $receipt;
            }

            DB::commit();

            // Send Batch Notifications
            try {
                $smsService = new \App\Services\StockReceiptSmsService();
                $smsService->sendBatchStockReceiptNotification($receiptNumber, $ownerId);
            } catch (\Exception $e) {
                \Log::error('SMS Batch Notification failed for stock receipt: ' . $e->getMessage());
            }

            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Stock receipt created successfully with {$receiptsCount} items. Receipt #{$receiptNumber}",
                    'receipt_number' => $receiptNumber
                ]);
            }

            return redirect()->route('bar.stock-receipts.index')
                ->with('success', "Stock receipt created successfully with {$receiptsCount} items. Receipt #{$receiptNumber}");

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating stock receipt: ' . $e->getMessage());
            
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error creating stock receipt: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->with('error', 'Error creating stock receipt: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified stock receipt or batch of receipts.
     */
    public function show($idOrNumber)
    {
        $ownerId = $this->getOwnerId();
        
        // Find by receipt number first (Batch view)
        $receipts = StockReceipt::where('user_id', $ownerId)
            ->where('receipt_number', $idOrNumber)
            ->with(['productVariant.product', 'supplier', 'receivedBy'])
            ->get();

        if ($receipts->count() > 0) {
            $receiptNumber = $idOrNumber;

            // Role-based visibility
            $showRevenue = true;
            if (session('is_staff')) {
                $staff = \App\Models\Staff::with('role')->find(session('staff_id'));
                if ($staff && $staff->role) {
                    $roleName = strtolower(trim($staff->role->name ?? ''));
                    if (in_array($roleName, ['stock keeper', 'stockkeeper'])) {
                        $showRevenue = false;
                    }
                }
            }

            return view('bar.stock-receipts.show_batch', compact('receipts', 'receiptNumber', 'showRevenue'));
        }

        // Fallback to single ID if needed (for legacy links)
        $stockReceipt = StockReceipt::where('user_id', $ownerId)->find($idOrNumber);
        if (!$stockReceipt) {
            abort(404, 'Stock receipt not found.');
        }

        // Redirect to batch view for consistency
        return redirect()->route('bar.stock-receipts.show', $stockReceipt->receipt_number);
    }

    /**
     * Delete an entire batch of stock receipts.
     */
    public function deleteBatch($receiptNumber)
    {
        // Check permission
        if (!$this->hasPermission('stock_receipt', 'delete')) {
            abort(403, 'You do not have permission to delete stock receipts.');
        }

        $ownerId = $this->getOwnerId();
        
        DB::beginTransaction();
        try {
            $receipts = StockReceipt::where('user_id', $ownerId)
                ->where('receipt_number', $receiptNumber)
                ->get();

            if ($receipts->isEmpty()) {
                throw new \Exception('Receipt batch not found.');
            }

            foreach ($receipts as $receipt) {
                // Adjust Warehouse Stock (Reverse)
                $warehouseStock = StockLocation::where('user_id', $ownerId)
                    ->where('product_variant_id', $receipt->product_variant_id)
                    ->where('location', 'warehouse')
                    ->first();

                if ($warehouseStock) {
                    $warehouseStock->decrement('quantity', $receipt->total_units);
                }

                // Delete related movement logs
                StockMovement::where('reference_type', 'stock_receipt')
                    ->where('reference_id', $receipt->id)
                    ->delete();

                // Delete the receipt record
                $receipt->delete();
            }

            DB::commit();
            return redirect()->route('bar.stock-receipts.index')
                ->with('success', "Batch #{$receiptNumber} deleted successfully. Stock has been adjusted.");

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Batch deletion failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete batch: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified stock receipt.
     */
    public function edit(StockReceipt $stockReceipt)
    {
        $ownerId = $this->getOwnerId();
        
        // Check ownership
        if ($stockReceipt->user_id !== $ownerId) {
            abort(403, 'You do not have access to this stock receipt.');
        }

        // Check permission
        if (!$this->hasPermission('stock_receipt', 'edit')) {
            abort(403, 'You do not have permission to edit stock receipts.');
        }

        $ownerId = $this->getOwnerId();

        $suppliers = Supplier::where('user_id', $ownerId)
            ->where('is_active', true)
            ->orderBy('company_name')
            ->get();

        $products = Product::where('user_id', $ownerId)
            ->where('is_active', true)
            ->with('variants')
            ->orderBy('name')
            ->get();

        // Prepare products data for JavaScript
        $productsData = $products->map(function($product) {
            return [
                'id' => $product->id,
                'variants' => $product->variants->map(function($variant) {
                    return [
                        'id' => $variant->id,
                        'measurement' => $variant->measurement,
                        'packaging' => $variant->packaging,
                        'items_per_package' => $variant->items_per_package,
                        'buying_price_per_unit' => $variant->buying_price_per_unit ? (float)$variant->buying_price_per_unit : null,
                        'selling_price_per_unit' => $variant->selling_price_per_unit ? (float)$variant->selling_price_per_unit : null,
                    ];
                })->values()->all()
            ];
        })->all();

        $stockReceipt->load(['productVariant.product', 'supplier']);

        return view('bar.stock-receipts.edit', compact('stockReceipt', 'suppliers', 'products', 'productsData'));
    }

    /**
     * Update the specified stock receipt.
     */
    public function update(Request $request, StockReceipt $stockReceipt)
    {
        $ownerId = $this->getOwnerId();
        
        // Check ownership
        if ($stockReceipt->user_id !== $ownerId) {
            abort(403, 'You do not have access to this stock receipt.');
        }

        // Check permission
        if (!$this->hasPermission('stock_receipt', 'edit')) {
            abort(403, 'You do not have permission to edit stock receipts.');
        }

        $validated = $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'quantity_received' => 'required|integer|min:0',
            'loose_received' => 'nullable|integer|min:0',
            'buying_price_per_unit' => 'required|numeric|min:0',
            'buying_price_mode' => 'nullable|in:pkg,unit',
            'selling_price_per_unit' => 'required|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percent',
            'discount_amount' => 'nullable|numeric|min:0|required_with:discount_type',
            'received_date' => 'required|date',
            'expiry_date' => 'nullable|date|after_or_equal:received_date',
            'notes' => 'nullable|string',
        ]);

        // Verify product variant belongs to user
        $productVariant = ProductVariant::where('id', $validated['product_variant_id'])
            ->whereHas('product', function($query) use ($ownerId) {
                $query->where('user_id', $ownerId);
            })
            ->first();

        if (!$productVariant) {
            return back()->withErrors(['product_variant_id' => 'Invalid product variant selected.'])->withInput();
        }

        // Verify supplier belongs to user
        $supplier = Supplier::where('id', $validated['supplier_id'])
            ->where('user_id', $ownerId)
            ->first();

        if (!$supplier) {
            return back()->withErrors(['supplier_id' => 'Invalid supplier selected.'])->withInput();
        }

        DB::beginTransaction();
        try {
            // Calculate old values for stock adjustment
            $oldTotalUnits = $stockReceipt->total_units;
            $oldFinalCost = $stockReceipt->final_buying_cost;

            // Calculate new values
            $numPackages = $validated['quantity_received'];
            $looseItems = $validated['loose_received'] ?? 0;
            $itemsPerPackage = $productVariant->items_per_package ?? 1;
            
            // Total Units = Full Packages + Loose items
            $totalUnits = ($numPackages * $itemsPerPackage) + $looseItems;
            $truePackages = $numPackages + ($looseItems / $itemsPerPackage);

            $buyingPriceInput = $validated['buying_price_per_unit'];
            $buyingPriceMode = $validated['buying_price_mode'] ?? 'pkg';
            
            $totalBuyingCost = 0;
            if ($buyingPriceMode === 'unit') {
                $totalBuyingCost = $totalUnits * $buyingPriceInput;
            } else {
                $totalBuyingCost = $truePackages * $buyingPriceInput;
            }

            $actualUnitBuyingPrice = $totalUnits > 0 ? ($totalBuyingCost / $totalUnits) : ($buyingPriceMode === 'unit' ? $buyingPriceInput : $buyingPriceInput / $itemsPerPackage);
            
            $totalSellingValue = $totalUnits * $validated['selling_price_per_unit'];
            $profitPerUnit = ($totalUnits > 0) ? ($totalSellingValue - $totalBuyingCost) / $totalUnits : 0;
            $totalProfit = $totalSellingValue - $totalBuyingCost;
            
            // Calculate discount
            $discountType = $validated['discount_type'] ?? null;
            $discountAmount = $validated['discount_amount'] ?? 0;
            $discountValue = 0;
            $finalBuyingCost = $totalBuyingCost;
            
            if ($discountType && $discountAmount > 0) {
                if ($discountType === 'fixed') {
                    $discountValue = min($discountAmount, $totalBuyingCost);
                    $finalBuyingCost = $totalBuyingCost - $discountValue;
                } elseif ($discountType === 'percent') {
                    $discountValue = ($totalBuyingCost * $discountAmount) / 100;
                    $finalBuyingCost = $totalBuyingCost - $discountValue;
                }
            }

            // Update stock receipt
            $stockReceipt->update([
                'product_variant_id' => $validated['product_variant_id'],
                'supplier_id' => $validated['supplier_id'],
                'quantity_received' => $truePackages,
                'total_units' => $totalUnits,
                'buying_price_per_unit' => $actualUnitBuyingPrice,
                'selling_price_per_unit' => $validated['selling_price_per_unit'],
                'total_buying_cost' => $totalBuyingCost,
                'total_selling_value' => $totalSellingValue,
                'profit_per_unit' => $profitPerUnit,
                'total_profit' => $totalProfit,
                'discount_type' => $discountType,
                'discount_amount' => $discountAmount > 0 ? $discountAmount : null,
                'discount_value' => $discountValue,
                'final_buying_cost' => $finalBuyingCost,
                'received_date' => $validated['received_date'],
                'expiry_date' => $validated['expiry_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Adjust warehouse stock
            $warehouseStock = StockLocation::where('user_id', $ownerId)
                ->where('product_variant_id', $productVariant->id)
                ->where('location', 'warehouse')
                ->first();

            if ($warehouseStock) {
                // Calculate stock adjustment
                $quantityDifference = $totalUnits - $oldTotalUnits;
                $newQuantity = $warehouseStock->quantity + $quantityDifference;

                // Recalculate average buying price
                $existingQuantity = $warehouseStock->quantity;
                $existingTotalCost = $existingQuantity * $warehouseStock->average_buying_price;
                $costDifference = $finalBuyingCost - $oldFinalCost;
                $newTotalCost = $existingTotalCost + $costDifference;
                $newAverageBuyingPrice = $newQuantity > 0 ? $newTotalCost / $newQuantity : $validated['buying_price_per_unit'];

                $warehouseStock->update([
                    'quantity' => max(0, $newQuantity), // Ensure non-negative
                    'average_buying_price' => $newAverageBuyingPrice,
                    'selling_price' => $validated['selling_price_per_unit'],
                ]);
            }

            // Update product variant prices
            $productVariant->update([
                'buying_price_per_unit' => $actualUnitBuyingPrice,
                'selling_price_per_unit' => $validated['selling_price_per_unit'],
            ]);

            // Update stock movement record if exists
            $stockMovement = StockMovement::where('reference_type', StockReceipt::class)
                ->where('reference_id', $stockReceipt->id)
                ->first();

            if ($stockMovement) {
                $stockMovement->update([
                    'product_variant_id' => $productVariant->id,
                    'quantity' => $totalUnits,
                    'unit_price' => $actualUnitBuyingPrice,
                    'notes' => 'Stock receipt updated: ' . $stockReceipt->receipt_number,
                ]);
            }

            DB::commit();

            return redirect()->route('bar.stock-receipts.index')
                ->with('success', 'Stock receipt updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Stock receipt update failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update stock receipt: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Remove the specified stock receipt from storage.
     */
    public function destroy(StockReceipt $stockReceipt)
    {
        $ownerId = $this->getOwnerId();
        
        // Check ownership
        if ($stockReceipt->user_id !== $ownerId) {
            abort(403, 'You do not have access to this stock receipt.');
        }

        // Check permission
        $canDelete = $this->hasPermission('stock_receipt', 'delete');
        
        // Allow delete for stock keeper role even without explicit permission
        if (!$canDelete && session('is_staff')) {
            $staff = \App\Models\Staff::with('role')->find(session('staff_id'));
            if ($staff && $staff->role) {
                $roleName = strtolower(trim($staff->role->name ?? ''));
                if (in_array($roleName, ['stock keeper', 'stockkeeper'])) {
                    $canDelete = true;
                }
            }
        }

        if (!$canDelete) {
            abort(403, 'You do not have permission to delete stock receipts.');
        }

        // Note: We will delete associated stock movements and adjust warehouse stock
        // This is safe because we're properly handling the stock adjustments

        DB::beginTransaction();
        try {
            // Adjust warehouse stock (remove the stock that was added)
            $warehouseStock = StockLocation::where('user_id', $ownerId)
                ->where('product_variant_id', $stockReceipt->product_variant_id)
                ->where('location', 'warehouse')
                ->first();

            if ($warehouseStock) {
                $newQuantity = max(0, $warehouseStock->quantity - $stockReceipt->total_units);
                
                // Recalculate average buying price
                $existingQuantity = $warehouseStock->quantity;
                $existingTotalCost = $existingQuantity * $warehouseStock->average_buying_price;
                $removedCost = $stockReceipt->final_buying_cost;
                $newTotalCost = $existingTotalCost - $removedCost;
                $newAverageBuyingPrice = $newQuantity > 0 ? $newTotalCost / $newQuantity : $warehouseStock->average_buying_price;

                $warehouseStock->update([
                    'quantity' => $newQuantity,
                    'average_buying_price' => $newAverageBuyingPrice,
                ]);
            }

            // Delete stock movement records
            StockMovement::where('reference_type', StockReceipt::class)
                ->where('reference_id', $stockReceipt->id)
                ->delete();

            // Delete the receipt
            $stockReceipt->delete();

            DB::commit();

            return redirect()->route('bar.stock-receipts.index')
                ->with('success', 'Stock receipt deleted successfully. Stock has been adjusted.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Stock receipt deletion failed: ' . $e->getMessage());
            return redirect()->route('bar.stock-receipts.index')
                ->with('error', 'Failed to delete stock receipt: ' . $e->getMessage());
        }
    }

    /**
     * Print a batch of stock receipts by receipt number.
     */
    public function printBatch($receiptNumber)
    {
        $ownerId = $this->getOwnerId();
        
        $receipts = StockReceipt::where('user_id', $ownerId)
            ->where('receipt_number', $receiptNumber)
            ->with(['productVariant.product', 'supplier', 'receivedBy'])
            ->get();

        if ($receipts->isEmpty()) {
            abort(404, 'Receipt batch not found.');
        }

        $supplier = $receipts->first()->supplier;
        $receivedDate = $receipts->first()->received_date;
        $receivedBy = $receipts->first()->receivedBy;
        $notes = $receipts->first()->notes;

        // Role-based visibility
        $showRevenue = true;
        if (session('is_staff')) {
            $staff = \App\Models\Staff::with('role')->find(session('staff_id'));
            if ($staff && $staff->role) {
                $roleName = strtolower(trim($staff->role->name ?? ''));
                if (in_array($roleName, ['stock keeper', 'stockkeeper'])) {
                    $showRevenue = false;
                }
            }
        }

        $owner = \App\Models\User::find($ownerId);
        $businessName = $owner->business_name;
        $stockKeeper = $receivedBy ? $receivedBy->name : 'N/A';
        $accountant = 'Accountant'; // Designate role for signature area

        return view('bar.stock-receipts.print', compact(
            'receipts', 'receiptNumber', 'supplier', 'receivedDate', 
            'receivedBy', 'notes', 'showRevenue', 'owner', 
            'businessName', 'stockKeeper', 'accountant'
        ));
    }
}
