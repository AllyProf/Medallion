<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesStaffPermissions;
use App\Models\FoodItem;
use App\Models\FoodItemExtra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FoodMenuController extends Controller
{
    use HandlesStaffPermissions;

    /**
     * Display a listing of the food items.
     */
    public function index()
    {
        if (!$this->hasPermission('products', 'view')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $this->getOwnerId();
        $isSuperAdmin = $this->isSuperAdminRole();

        $query = FoodItem::query();
        
        // Super admin sees all items, others only their own
        if (!$isSuperAdmin && $ownerId) {
            $query->where('user_id', $ownerId);
        }

        $foodItems = $query->with('extras')
            ->orderBy('sort_order')
            ->paginate(200);

        return view('bar.food.index', compact('foodItems'));
    }

    /**
     * Show the form for creating a new food item.
     */
    public function create()
    {
        if (!$this->hasPermission('products', 'create')) {
            abort(403, 'Unauthorized');
        }

        return view('bar.food.create');
    }

    /**
     * Store a newly created food item in storage.
     */
    public function store(Request $request)
    {
        if (!$this->hasPermission('products', 'create')) {
            abort(403, 'Unauthorized');
        }

        $ownerId = $this->getOwnerId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'variant_name' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'prep_time_minutes' => 'nullable|integer',
            'image' => 'nullable|image|max:2048',
            'extras' => 'nullable|array',
            'extras.*.name' => 'required_with:extras|string|max:255',
            'extras.*.price' => 'required_with:extras|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('food_items', 'public');
            }

            $foodItem = FoodItem::create([
                'user_id' => $ownerId,
                'name' => $validated['name'],
                'variant_name' => $validated['variant_name'],
                'category' => $validated['category'] ?? null,
                'description' => $validated['description'],
                'price' => $validated['price'],
                'prep_time_minutes' => $validated['prep_time_minutes'],
                'image' => $imagePath,
                'is_available' => true,
            ]);

            // Save Extras
            if ($request->has('extras')) {
                foreach ($request->extras as $extra) {
                    if (!empty($extra['name'])) {
                        FoodItemExtra::create([
                            'food_item_id' => $foodItem->id,
                            'name' => $extra['name'],
                            'price' => $extra['price'],
                            'is_available' => true,
                        ]);
                    }
                }
            }

            DB::commit();
            return redirect()->route('bar.food.index')->with('success', 'Food item registered successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show the form for editing the specified food item.
     */
    public function edit(FoodItem $food)
    {
        if (!$this->hasPermission('products', 'edit')) {
            abort(403, 'Unauthorized');
        }

        $food->load('extras');
        return view('bar.food.edit', compact('food'));
    }

    /**
     * Update the specified food item in storage.
     */
    public function update(Request $request, FoodItem $food)
    {
        if (!$this->hasPermission('products', 'edit')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'variant_name' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'prep_time_minutes' => 'nullable|integer',
            'image' => 'nullable|image|max:2048',
            'extras' => 'nullable|array',
            'extras.*.id' => 'nullable|integer',
            'extras.*.name' => 'required_with:extras|string|max:255',
            'extras.*.price' => 'required_with:extras|numeric|min:0',
            'extras.*.is_available' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            if ($request->hasFile('image')) {
                if ($food->image) {
                    Storage::disk('public')->delete($food->image);
                }
                $imagePath = $request->file('image')->store('food_items', 'public');
                $food->image = $imagePath;
            }

            $food->update([
                'name' => $validated['name'],
                'variant_name' => $validated['variant_name'],
                'category' => $validated['category'] ?? null,
                'description' => $validated['description'],
                'price' => $validated['price'],
                'prep_time_minutes' => $validated['prep_time_minutes'],
            ]);

            // Handle Extras
            $existingExtraIds = [];
            if ($request->has('extras')) {
                foreach ($request->extras as $extraData) {
                    if (isset($extraData['id']) && $extraData['id']) {
                        $extra = FoodItemExtra::find($extraData['id']);
                        if ($extra && $extra->food_item_id == $food->id) {
                            $extra->update([
                                'name' => $extraData['name'],
                                'price' => $extraData['price'],
                                'is_available' => isset($extraData['is_available']) ? $extraData['is_available'] : true,
                            ]);
                            $existingExtraIds[] = $extra->id;
                        }
                    } else {
                        $newExtra = FoodItemExtra::create([
                            'food_item_id' => $food->id,
                            'name' => $extraData['name'],
                            'price' => $extraData['price'],
                            'is_available' => true,
                        ]);
                        $existingExtraIds[] = $newExtra->id;
                    }
                }
            }

            // Sync: Remove extras not in the request
            FoodItemExtra::where('food_item_id', $food->id)
                ->whereNotIn('id', $existingExtraIds)
                ->delete();

            DB::commit();
            return redirect()->route('bar.food.index')->with('success', 'Food item updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Quick update price via AJAX.
     */
    public function updatePrice(Request $request)
    {
        if (!$this->hasPermission('products', 'edit')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'id' => 'required|integer|exists:food_items,id',
            'price' => 'required|numeric|min:0'
        ]);

        $food = FoodItem::findOrFail($request->id);
        
        // Ensure user owns this item (Super Admin bypasses this)
        if (!$this->isSuperAdminRole() && $food->user_id !== $this->getOwnerId()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $food->update(['price' => $request->price]);

        return response()->json([
            'success' => true,
            'message' => 'Price updated',
            'new_price' => number_format($request->price) . ' TZS'
        ]);
    }

    /**
     * Remove the specified food item from storage.
     */
    public function destroy(FoodItem $food)
    {
        if (!$this->hasPermission('products', 'delete')) {
            abort(403, 'Unauthorized');
        }

        if ($food->image) {
            Storage::disk('public')->delete($food->image);
        }

        $food->delete();

        return redirect()->route('bar.food.index')->with('success', 'Food item deleted successfully.');
    }
}
