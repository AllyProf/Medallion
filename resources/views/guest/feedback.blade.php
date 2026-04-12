@extends('layouts.public_dashboard')

@section('title', 'Feedback - ' . ($owner->business_name ?? $owner->name))

@section('extra_css')
<style>
    .star-rating {
        display: flex;
        flex-direction: row-reverse;
        justify-content: center;
        gap: 15px;
        margin: 20px 0;
    }
    .star-rating input { display: none; }
    .star-rating label {
        font-size: 3rem;
        color: #e0e0e0;
        cursor: pointer;
        transition: 0.2s;
        margin: 0;
    }
    .star-rating input:checked ~ label,
    .star-rating label:hover,
    .star-rating label:hover ~ label {
        color: #FEA116;
    }
</style>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <div class="business-logo">{{ $owner->business_name ?? 'Medallion' }}</div>
        <p class="mb-0">Customer Feedback Portal</p>
    </div>
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <h5 class="fw-bold">We Value Your Opinion</h5>
            <p class="text-muted small">Please take a moment to share your experience with us.</p>
        </div>

        <form action="{{ route('public.restaurant.feedback.submit', $owner->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to submit your feedback?')">
            @csrf
            
            <div class="form-group text-center border-bottom pb-4 mb-4">
                <label class="font-weight-bold d-block mb-3">How would you rate our service?</label>
                <div class="star-rating">
                    <input type="radio" id="star5" name="rating" value="5" required />
                    <label for="star5" title="5 stars"><i class="fa fa-star"></i></label>
                    <input type="radio" id="star4" name="rating" value="4" />
                    <label for="star4" title="4 stars"><i class="fa fa-star"></i></label>
                    <input type="radio" id="star3" name="rating" value="3" />
                    <label for="star3" title="3 stars"><i class="fa fa-star"></i></label>
                    <input type="radio" id="star2" name="rating" value="2" />
                    <label for="star2" title="2 stars"><i class="fa fa-star"></i></label>
                    <input type="radio" id="star1" name="rating" value="1" />
                    <label for="star1" title="1 star"><i class="fa fa-star"></i></label>
                </div>
                @error('rating') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
            </div>

            <div class="form-group mb-3">
                <label class="font-weight-bold">Suggestions / Comments</label>
                <textarea name="comments" class="form-control" rows="4" placeholder="What can we do better?"></textarea>
            </div>

            <div class="form-group mb-4">
                <label class="font-weight-bold">Which waiter served you? (Optional)</label>
                <input type="text" name="waiter_name" class="form-control">
            </div>

            <div class="p-3 bg-light rounded mb-4">
                <h6 class="text-muted text-uppercase small font-weight-bold mb-3">Your Contact Details (Optional)</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="small">Name</label>
                        <input type="text" name="customer_name" class="form-control" placeholder="Your Name">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="small">Phone</label>
                        <input type="text" name="customer_phone" class="form-control" value="+255">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg text-uppercase font-weight-bold mb-3 shadow">
                Submit Feedback
            </button>
            
            <a href="{{ route('public.restaurant.menu', $owner->id) }}" class="btn btn-link btn-block text-muted small">
                <i class="fa fa-arrow-left"></i> Back to Menu
            </a>
        </form>
    </div>
</div>
@endsection
