<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\CustomerFeedback;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrMenuController extends Controller
{
    private function getOwnerId()
    {
        return session('is_staff') ? \App\Models\Staff::find(session('staff_id'))->user_id : Auth::id();
    }

    /**
     * Dashboard to see and download QR codes.
     */
    public function index()
    {
        $ownerId = $this->getOwnerId();
        $owner = \App\Models\User::findOrFail($ownerId);
        
        // Construct URLs (Clean version)
        $firstOwner = \App\Models\User::whereIn('role', ['owner', 'admin', 'customer'])->first();
        if ($ownerId == $firstOwner->id) {
            $menuUrl = route('public.restaurant.menu');
            $feedbackUrl = route('public.restaurant.feedback');
        } else {
            $menuUrl = route('public.restaurant.menu', $ownerId);
            $feedbackUrl = route('public.restaurant.feedback', $ownerId);
        }

        // Generate QRs (as SVG strings)
        $menuQr = QrCode::size(300)->generate($menuUrl);
        $feedbackQr = QrCode::size(300)->generate($feedbackUrl);

        return view('manager.qr_manager', compact('menuUrl', 'feedbackUrl', 'menuQr', 'feedbackQr', 'owner'));
    }

    /**
     * View all customer suggestions and ratings.
     */
    public function feedbackInbox()
    {
        $ownerId = $this->getOwnerId();
        
        $feedbacks = CustomerFeedback::where('user_id', $ownerId)
            ->orderBy('created_at', 'desc')
            ->paginate(15);
            
        return view('manager.feedback_inbox', compact('feedbacks'));
    }
}
