@extends('layouts.public_dashboard')

@section('title', 'Thank You - ' . ($owner->business_name ?? $owner->name))

@section('content')
<div class="card text-center">
    <div class="card-header">
        <div class="business-logo">{{ $owner->business_name ?? 'Medallion' }}</div>
    </div>
    <div class="card-body p-5">
        <div class="mb-4">
            <i class="fa fa-check-circle fa-5x text-success animate__animated animate__zoomIn"></i>
        </div>
        <h3 class="font-weight-bold mb-3">Feedback Received!</h3>
        <p class="text-muted mb-5">Asante Sana! Your feedback has been received and shared with the Management. We value your suggestions to help us serve you better.</p>
        
        <div class="row g-2">
            <div class="col-12 mb-2">
                <a href="{{ route('public.restaurant.menu', $owner->id) }}" class="btn btn-primary btn-block py-3 text-uppercase font-weight-bold">
                    View Menu
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
