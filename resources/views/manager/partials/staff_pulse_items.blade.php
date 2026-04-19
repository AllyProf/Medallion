@foreach($staffPulse as $staff)
<li class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 mb-2 py-2" style="border-bottom: 1px solid #f0f0f0 !important;">
    <div class="d-flex align-items-center">
        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 40px; height: 40px; border: 1px solid #eee;">
            <i class="fa fa-user text-primary"></i>
        </div>
        <div>
            <div class="font-weight-bold text-dark">{{ $staff->full_name }}</div>
            <small class="text-muted">{{ $staff->orders_count }} orders today</small>
        </div>
    </div>
    <div class="text-right">
        <div class="text-success font-weight-bold" style="font-size: 1rem;">TSh {{ number_format($staff->total_sales) }}</div>
        <div class="bg-success" style="height: 4px; width: 100%; border-radius: 2px; opacity: 0.2;"></div>
    </div>
</li>
@endforeach
@if($staffPulse->isEmpty())
<div class="text-center py-4">
    <p class="text-muted small">No staff activity yet today.</p>
</div>
@endif
