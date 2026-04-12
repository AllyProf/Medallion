<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use App\Models\FoodItem;
use App\Models\ProductVariant;
use App\Models\CustomerFeedback;
use App\Services\SmsService;

class PublicRestaurantController extends Controller
{
    /**
     * Display the digital menu to the public.
     */
    public function showMenu($ownerId = null)
    {
        if (!$ownerId) {
            $owner = User::whereIn('role', ['owner', 'admin', 'customer'])->first();
            $ownerId = $owner->id;
        } else {
            $owner = User::findOrFail($ownerId);
        }
        
        // Fetch Food Items
        $foodItems = FoodItem::where('user_id', $ownerId)
            ->where('is_available', true)
            ->with('extras') // Eager load for modal display
            ->get()
            ->groupBy('category');
            
        $barItems = ProductVariant::whereHas('product', function($q) use ($ownerId) {
                $q->where('user_id', $ownerId)->where('is_active', true);
            })
            ->whereHas('stockLocations', function($q) use ($ownerId) {
                $q->where('user_id', $ownerId)
                  ->where('location', 'counter')
                  ->where('quantity', '>', 0);
            })
            ->where('is_active', true)
            ->with(['product', 'stockLocations' => function($q) use ($ownerId) {
                $q->where('user_id', $ownerId)->where('location', 'counter');
            }])
            ->get()
            ->groupBy(function($item) {
                return $item->product->category ?? 'Other Beverages';
            });
            
        return view('guest.menu', compact('owner', 'foodItems', 'barItems'));
    }

    /**
     * Show the public feedback form.
     */
    public function showFeedbackForm($ownerId = null)
    {
        if (!$ownerId) {
            $owner = User::whereIn('role', ['owner', 'admin', 'customer'])->first();
            $ownerId = $owner->id;
        } else {
            $owner = User::findOrFail($ownerId);
        }
        return view('guest.feedback', compact('owner'));
    }

    /**
     * Store public customer feedback.
     */
    public function submitFeedback(Request $request, SmsService $smsService, $ownerId = null)
    {
        if (!$ownerId) {
            $owner = User::whereIn('role', ['owner', 'admin', 'customer'])->first();
            $ownerId = $owner->id;
        }

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comments' => 'nullable|string',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'waiter_name' => 'nullable|string|max:255',
        ]);

        CustomerFeedback::create([
            'user_id' => $ownerId,
            'rating' => $request->rating,
            'comments' => $request->comments,
            'customer_name' => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'waiter_name' => $request->waiter_name,
        ]);

        // Send Thank You SMS if phone is provided
        if ($request->customer_phone) {
            $businessName = $owner->business_name ?? 'Medallion';
            $customerName = $request->customer_name ?: 'Mteja';
            $message = "Habari {$customerName}! Asante kwa maoni yako. Tunafurahi kukuhudumia hapa {$businessName}. Karibu tena!";
            $smsService->sendSms($request->customer_phone, $message);
        }

        return view('guest.feedback_success', ['owner' => User::find($ownerId)]);
    }
}
