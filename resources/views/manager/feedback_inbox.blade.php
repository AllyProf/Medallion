@extends('layouts.dashboard')

@section('title', 'Customer Feedback Inbox')

@section('content')
<div class="app-title">
    <div>
        <h1><i class="fa fa-envelope-open-text"></i> Customer Feedback</h1>
        <p>Listen to what your customers are saying.</p>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="tile">
            <h3 class="tile-title">Recent Suggestions & Ratings</h3>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Rating</th>
                            <th>Suggestions / Comments</th>
                            <th>Waiter Mentioned</th>
                            <th>Customer Info</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($feedbacks as $item)
                        <tr>
                            <td>{{ $item->created_at->format('M d, Y H:i') }}</td>
                            <td>
                                @for($i=1; $i<=5; $i++)
                                    <i class="fa fa-star {{ $i <= $item->rating ? 'text-warning' : 'text-light' }}"></i>
                                @endfor
                            </td>
                            <td>{{ $item->comments ?: 'No comments left' }}</td>
                            <td><span class="badge badge-info">{{ $item->waiter_name ?: 'N/A' }}</span></td>
                            <td>
                                <div>{{ $item->customer_name ?: 'Anonymous' }}</div>
                                <small class="text-muted">{{ $item->customer_phone ?: '' }}</small>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <p class="text-muted">No feedback received yet. Share your QR codes to start receiving suggestions!</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $feedbacks->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
