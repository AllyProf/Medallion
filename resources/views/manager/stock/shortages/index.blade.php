@extends('layouts.dashboard')

@section('title', 'Item Shortages Management')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container--default .select2-selection--single {
        height: 38px !important;
        border: 1px solid #ced4da !important;
        border-radius: 4px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 38px !important;
        padding-left: 12px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
    }
    .select2-container {
        width: 100% !important;
    }
</style>
@endpush

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-exclamation-circle text-danger"></i> Staff Item Shortages</h1>
    <p>Monitor, waive, or convert physical stock shortages into waiter cash reconciliation debt</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('bar.counter.dashboard') }}">Counter</a></li>
    <li class="breadcrumb-item">Stock Shortages</li>
  </ul>
</div>

<!-- Search, Filter & Audit View -->
<div class="row">
    <div class="col-md-12">
        <div class="tile">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                <h3 class="tile-title mb-0"><i class="fa fa-filter text-primary"></i> Filter Discrepancies</h3>
                <a href="{{ route('bar.stock-shortages.index') }}" class="btn btn-sm btn-secondary">
                    <i class="fa fa-times"></i> Clear Filters
                </a>
            </div>
            
            <form method="GET" action="{{ route('bar.stock-shortages.index') }}" class="row align-items-end">
                <div class="form-group col-md-3 mb-2">
                    <label class="control-label font-weight-bold">Attributed Staff Member</label>
                    <select name="staff_id" class="form-control">
                        <option value="">-- All Staff --</option>
                        @foreach($staffMembers as $staff)
                            <option value="{{ $staff->id }}" {{ request('staff_id') == $staff->id ? 'selected' : '' }}>
                                {{ $staff->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="form-group col-md-3 mb-2">
                    <label class="control-label font-weight-bold">Status</label>
                    <select name="status" class="form-control">
                        <option value="">-- All Statuses --</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending Audit</option>
                        <option value="waived" {{ request('status') === 'waived' ? 'selected' : '' }}>Waived / Excused</option>
                        <option value="charged" {{ request('status') === 'charged' ? 'selected' : '' }}>Charged to Staff</option>
                    </select>
                </div>

                <div class="form-group col-md-2 mb-2">
                    <label class="control-label font-weight-bold">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                </div>

                <div class="form-group col-md-2 mb-2">
                    <label class="control-label font-weight-bold">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                </div>

                <div class="form-group col-md-2 mb-2">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fa fa-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="tile">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                <h3 class="tile-title mb-0"><i class="fa fa-list"></i> Shortage Register Audit Log</h3>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#recordShortageModal">
                    <i class="fa fa-plus-circle mr-1"></i> Record Stock Shortage
                </button>
            </div>
            
            <div class="tile-body">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered" id="shortagesTable">
                        <thead class="bg-light">
                            <tr>
                                <th>Date & Recorder</th>
                                <th>Product Variant</th>
                                <th class="text-center">Short Qty</th>
                                <th class="text-right">Capital Cost (Buy)</th>
                                <th class="text-right">Expected Revenue</th>
                                <th class="text-right">Lost Profit</th>
                                <th>Attributed Staff</th>
                                <th class="text-center">Status</th>
                                <th class="text-center" width="160">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($shortages as $shortage)
                            <tr>
                                <td class="align-middle">
                                    <span class="font-weight-bold">{{ $shortage->created_at->format('M d, Y h:i A') }}</span><br>
                                    <small class="text-muted"><i class="fa fa-user-circle"></i> {{ $shortage->recorder->name ?? 'System' }}</small>
                                </td>
                                <td class="align-middle">
                                    <span class="text-primary font-weight-bold">{{ $shortage->productVariant->display_name ?? 'Product Deleted' }}</span>
                                    @if($shortage->barShift)
                                        <div class="smallest text-info mt-1"><i class="fa fa-clock-o"></i> Shift #{{ $shortage->barShift->id }}</div>
                                    @endif
                                </td>
                                <td class="text-center align-middle font-weight-bold text-danger">
                                    {{ number_format($shortage->quantity_short) }} btl{{ $shortage->quantity_short > 1 ? 's' : '' }}
                                </td>
                                <td class="text-right align-middle font-weight-bold text-dark">
                                    TSh {{ number_format($shortage->money_in_supply) }}<br>
                                    <small class="smallest text-muted">@ TSh {{ number_format($shortage->buying_price) }}</small>
                                </td>
                                <td class="text-right align-middle font-weight-bold text-success">
                                    TSh {{ number_format($shortage->expected_revenue) }}<br>
                                    <small class="smallest text-muted">@ TSh {{ number_format($shortage->selling_price) }}</small>
                                </td>
                                <td class="text-right align-middle font-weight-bold text-info">
                                    TSh {{ number_format($shortage->lost_profit) }}
                                </td>
                                <td class="align-middle">
                                    @if($shortage->staff)
                                        <strong><i class="fa fa-user mr-1"></i> {{ $shortage->staff->full_name }}</strong>
                                    @else
                                        <span class="text-muted font-italic"><i class="fa fa-question-circle mr-1"></i> Unattributed</span>
                                    @endif
                                </td>
                                <td class="text-center align-middle">
                                    @if($shortage->status === 'pending')
                                        <span class="badge badge-warning text-uppercase"><i class="fa fa-hourglass-half"></i> Pending</span>
                                    @elseif($shortage->status === 'waived')
                                        <span class="badge badge-secondary text-uppercase"><i class="fa fa-times-circle"></i> Waived</span>
                                    @elseif($shortage->status === 'charged')
                                        <span class="badge badge-success text-uppercase"><i class="fa fa-check-circle"></i> Charged</span>
                                    @endif
                                </td>
                                <td class="text-center align-middle">
                                    <div class="d-flex align-items-center justify-content-center">
                                        @if($shortage->status === 'pending')
                                            @if($shortage->staff_id)
                                                <button type="button" class="btn btn-sm btn-success mr-1"
                                                        onclick="chargeStaff({{ $shortage->id }}, '{{ addslashes($shortage->staff->full_name) }}', {{ $shortage->expected_revenue }})">
                                                    <i class="fa fa-gavel"></i> Charge
                                                </button>
                                            @endif
                                            <button type="button" class="btn btn-sm btn-secondary mr-1"
                                                    onclick="waiveShortage({{ $shortage->id }})">
                                                <i class="fa fa-gift"></i> Waive
                                            </button>
                                        @else
                                            <span class="badge badge-light text-muted mr-2 font-italic small">Processed</span>
                                        @endif
                                        
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                title="Undo Shortage (Restore Stock)"
                                                onclick="undoShortage({{ $shortage->id }}, '{{ addslashes($shortage->productVariant->display_name ?? 'Item') }}', {{ $shortage->quantity_short }})">
                                            <i class="fa fa-undo"></i> Undo
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 bg-light">
                                    <div class="p-4">
                                        <i class="fa fa-check-circle fa-4x text-success mb-3 opacity-50"></i>
                                        <h5 class="text-dark">All clean! No item shortages logged.</h5>
                                        <p class="text-muted mb-0 smallest">Any physical count shortfalls reported will show up here for auditing.</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4 d-flex justify-content-center">
                    {{ $shortages->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Record Direct Shortage Modal -->
<div class="modal fade" id="recordShortageModal" tabindex="-1" role="dialog" aria-labelledby="recordShortageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold" id="recordShortageModalLabel">
                    <i class="fa fa-exclamation-circle text-danger mr-1"></i> Record Direct Stock Shortage
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="recordShortageForm">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small">
                        <i class="fa fa-info-circle text-info"></i> Direct stock discrepancy logging interface. Decrements current counter stock to align with physical stock count.
                    </p>
                    
                    <div class="row">
                        <!-- Product Selection -->
                        <div class="form-group col-md-6">
                            <label class="control-label font-weight-bold">Select Product Variant <span class="text-danger">*</span></label>
                            <select name="product_variant_id" id="modal_product_variant" class="form-control select2-modal" style="width: 100%;" required>
                                <option value="" data-qty="0" data-buy-price="0" data-sell-price="0">-- Choose Counter Item --</option>
                                @foreach($variants as $variant)
                                    <option value="{{ $variant->id }}" 
                                            data-qty="{{ $variant->counter_qty }}" 
                                            data-buy-price="{{ $variant->counterStock?->average_buying_price ?? $variant->buying_price_per_unit ?? 0 }}"
                                            data-sell-price="{{ $variant->counterStock?->selling_price ?? $variant->selling_price_per_unit ?? 0 }}">
                                        {{ $variant->display_name }} (Counter Stock: {{ number_format($variant->counter_qty) }} btl)
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <!-- Responsible Staff -->
                        <div class="form-group col-md-6">
                            <label class="control-label font-weight-bold">Attributed Staff (Optional)</label>
                            <select name="staff_id" class="form-control">
                                <option value="">-- Unattributed / System --</option>
                                @foreach($staffMembers as $staff)
                                    <option value="{{ $staff->id }}">{{ $staff->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Physical Count -->
                        <div class="form-group col-md-6">
                            <label class="control-label font-weight-bold">Physical Count Found <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="physical_count" id="modal_physical_count" class="form-control" placeholder="Enter actual count found..." required disabled>
                                <div class="input-group-append">
                                    <span class="input-group-text bg-light">btls</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Notes -->
                        <div class="form-group col-md-6">
                            <label class="control-label font-weight-bold">Notes / Reason <span class="text-danger">*</span></label>
                            <input type="text" name="notes" class="form-control" placeholder="e.g. Broken bottle, miscount, theft..." required>
                        </div>
                    </div>
                    
                    <!-- Live Metric Discrepancy Table -->
                    <div class="form-group">
                        <label class="control-label font-weight-bold">Shortage & Financial Estimates</label>
                        <table class="table table-bordered bg-light mb-0">
                            <tbody>
                                <tr>
                                    <td width="50%"><strong>Expected Stock:</strong></td>
                                    <td><span id="calc_expected_qty" class="font-weight-bold">0.00 btls</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Discrepancy Shortage:</strong></td>
                                    <td><span id="calc_short_qty" class="text-danger font-weight-bold">0.00 btls</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Expected Revenue Loss:</strong></td>
                                    <td><span id="calc_revenue" class="text-success font-weight-bold">TSh 0</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Capital Cost (Buy):</strong></td>
                                    <td><span id="calc_cost" class="text-dark font-weight-bold">TSh 0</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Lost Profit Margins:</strong></td>
                                    <td><span id="calc_profit" class="text-info font-weight-bold">TSh 0</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" id="modal_submit_btn" class="btn btn-primary" disabled>
                        <i class="fa fa-check-circle"></i> Log Shortage
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection


@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // Initialize Select2
    $('#modal_product_variant').select2({
        dropdownParent: $('#recordShortageModal'),
        placeholder: "-- Choose Counter Item --",
        allowClear: true
    });

    // Live calculations inside shortage modal
    $('#modal_product_variant').on('change', function() {
        var option = $(this).find('option:selected');
        var expectedQty = parseFloat(option.data('qty')) || 0;
        
        if ($(this).val()) {
            $('#modal_physical_count').prop('disabled', false).val('');
            $('#calc_expected_qty').text(expectedQty.toFixed(2) + ' btls');
            $('#modal_physical_count').attr('max', (expectedQty - 0.01).toFixed(2));
        } else {
            $('#modal_physical_count').prop('disabled', true).val('');
            $('#calc_expected_qty').text('0.00 btls');
        }
        updateShortageCalculations();
    });

    $('#modal_physical_count').on('input keyup', function() {
        updateShortageCalculations();
    });

    function updateShortageCalculations() {
        var option = $('#modal_product_variant option:selected');
        var expectedQty = parseFloat(option.data('qty')) || 0;
        var buyPrice = parseFloat(option.data('buy-price')) || 0;
        var sellPrice = parseFloat(option.data('sell-price')) || 0;
        
        var physicalCountStr = $('#modal_physical_count').val();
        if (physicalCountStr === '') {
            $('#calc_short_qty').text('0.00 btls').removeClass('text-danger text-warning').addClass('text-muted');
            $('#calc_revenue').text('TSh 0');
            $('#calc_cost').text('TSh 0');
            $('#calc_profit').text('TSh 0');
            $('#modal_submit_btn').prop('disabled', true);
            return;
        }
        
        var physicalCount = parseFloat(physicalCountStr) || 0;
        
        if (physicalCount >= expectedQty) {
            $('#calc_short_qty').text('Must be less than expected stock').removeClass('text-danger text-muted').addClass('text-warning');
            $('#calc_revenue').text('TSh 0');
            $('#calc_cost').text('TSh 0');
            $('#calc_profit').text('TSh 0');
            $('#modal_submit_btn').prop('disabled', true);
            return;
        }
        
        var shortQty = expectedQty - physicalCount;
        var revenue = shortQty * sellPrice;
        var cost = shortQty * buyPrice;
        var profit = revenue - cost;
        
        $('#calc_short_qty').text(shortQty.toFixed(2) + ' btls').removeClass('text-muted text-warning').addClass('text-danger');
        $('#calc_revenue').text('TSh ' + Math.round(revenue).toLocaleString());
        $('#calc_cost').text('TSh ' + Math.round(cost).toLocaleString());
        $('#calc_profit').text('TSh ' + Math.round(profit).toLocaleString());
        $('#modal_submit_btn').prop('disabled', false);
    }

    // Handle Direct Shortage AJAX Form Submission
    $('#recordShortageForm').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var btn = $('#modal_submit_btn');
        var originalHtml = btn.html();
        
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Logging...');
        
        $.ajax({
            url: '{{ route("bar.stock-shortages.store") }}',
            method: 'POST',
            data: form.serialize(),
            success: function(response) {
                $('#recordShortageModal').modal('hide');
                Swal.fire({
                    title: 'Shortage Logged!',
                    text: response.message || 'Discrepancy logged and counter stock updated successfully.',
                    icon: 'success',
                    confirmButtonColor: '#009688'
                }).then(function() {
                    location.reload();
                });
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(originalHtml);
                var err = xhr.responseJSON ? (xhr.responseJSON.error || xhr.responseJSON.message) : 'Failed to record shortage.';
                Swal.fire({
                    title: 'Discrepancy Check Failed',
                    text: err,
                    icon: 'error',
                    confirmButtonColor: '#343a40'
                });
            }
        });
    });
});

function chargeStaff(id, name, amount) {
    Swal.fire({
        title: 'Charge shortage to staff?',
        html: `This will convert this item shortage into a monetary debt of <b class="text-danger">TSh ${parseInt(amount).toLocaleString()}</b> and programmatically charge it to <b>${name}</b>'s active shift/daily reconciliation.<br><br><span class="text-muted smaller">This action cannot be undone.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Charge Staff',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return $.ajax({
                url: `/bar/stock-shortages/${id}/charge`,
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                }
            }).then(response => {
                if (!response.success) throw new Error(response.error || 'Failed to charge staff');
                return response;
            }).catch(error => {
                Swal.showValidationMessage(`Request failed: ${error.message || error}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Charged!',
                text: result.value.message || 'Staff shortage charged successfully.',
                icon: 'success'
            }).then(() => {
                location.reload();
            });
        }
    });
}

function waiveShortage(id) {
    Swal.fire({
        title: 'Waive / Excuse shortage?',
        html: `Are you sure you want to waive and excuse this shortage? This means no staff member will be charged and the loss is marked as accepted by the house.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#6c757d',
        cancelButtonColor: '#343a40',
        confirmButtonText: 'Yes, Waive Loss',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return $.ajax({
                url: `/bar/stock-shortages/${id}/waive`,
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                }
            }).then(response => {
                if (!response.success) throw new Error(response.error || 'Failed to waive shortage');
                return response;
            }).catch(error => {
                Swal.showValidationMessage(`Request failed: ${error.message || error}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Waived!',
                text: result.value.message || 'Loss excused successfully.',
                icon: 'success'
            }).then(() => {
                location.reload();
            });
        }
    });
}

function undoShortage(id, itemName, qty) {
    Swal.fire({
        title: 'Undo Shortage Record?',
        html: `This will completely reverse and delete this shortage record.<br><br><b>Restored Stock:</b> +${qty} btl(s) of <b>${itemName}</b> will be returned to the counter stock.<br><br><span class="text-danger small font-weight-bold">If this shortage was already charged to a waiter, that charged debt will be automatically deducted from their reconciliation.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Undo Shortage',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return $.ajax({
                url: `/bar/stock-shortages/${id}/undo`,
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                }
            }).then(response => {
                if (!response.success) throw new Error(response.error || 'Failed to undo shortage');
                return response;
            }).catch(error => {
                Swal.showValidationMessage(`Request failed: ${error.message || error}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Undone & Stock Restored!',
                text: result.value.message || 'Shortage has been completely reversed.',
                icon: 'success'
            }).then(() => {
                location.reload();
            });
        }
    });
}
</script>
@endpush
