@extends('layouts.dashboard')

@section('title', 'Open New Shift')

@section('content')
<style>
    .stock-card {
        border-radius: 12px;
        transition: all 0.3s ease;
        border: 1px solid #e0e0e0;
        cursor: pointer;
        position: relative;
    }
    .stock-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        border-color: #009688;
    }
    .stock-card.verified {
        border-color: #28a745;
        background-color: #f8fff9;
    }
    .stock-card.verified::after {
        content: '\f058';
        font-family: FontAwesome;
        position: absolute;
        top: 10px;
        right: 10px;
        color: #28a745;
        font-size: 1.5rem;
    }
    .stock-card .qty-badge {
        font-size: 1.5rem;
        font-weight: bold;
        color: #009688;
    }
    .stock-card .unit-label {
        font-size: 0.8rem;
        text-transform: uppercase;
        font-weight: bold;
        color: #777;
    }
    .view-toggle-btn.active {
        background-color: #009688 !important;
        color: white !important;
        border-color: #009688 !important;
    }
</style>

<div class="app-title">
  <div>
    <h1><i class="fa fa-check-square-o text-primary"></i> Stock Verification</h1>
    <p>Verify counter inventory before starting your shift</p>
  </div>
  @if(empty($anyOpenShift))
  <div class="btn-group" role="group">
    <button type="button" class="btn btn-outline-info view-toggle-btn active" id="btn-table-view"><i class="fa fa-list"></i> Table</button>
    <button type="button" class="btn btn-outline-info view-toggle-btn" id="btn-card-view"><i class="fa fa-th-large"></i> Cards</button>
  </div>
  @endif
</div>

@if(!empty($anyOpenShift))
<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="tile shadow-sm border-0 rounded-lg text-center p-5">
            <i class="fa fa-lock fa-3x text-warning mb-3"></i>
            <h3 class="mb-2">Another shift is already open</h3>
            <p class="lead mb-3">
                You cannot start a new counter session until the current shift is closed.
            </p>
            <p class="mb-1">
                <strong>Opened by:</strong> {{ $anyOpenShift->staff->full_name ?? 'Staff' }}
            </p>
            <p class="text-muted mb-4">
                <strong>Opened at:</strong> {{ optional($anyOpenShift->opened_at)->format('d M Y, H:i') }}
            </p>
            <p class="small text-muted mb-0">
                Ask that counter staff to close their shift, then refresh this page to start yours.
            </p>
        </div>
    </div>
</div>
@else
<div class="row">
    <div class="col-md-12">
        <div class="tile shadow-sm border-0 rounded-lg">
            <!-- Search Bar -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="input-group input-group-lg shadow-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
                        </div>
                        <input type="text" class="form-control" id="stock-search" placeholder="Quick search items...">
                    </div>
                </div>
            </div>

            <!-- TABLE VIEW (Default) -->
            <div id="table-view-container">
                <div class="table-responsive" style="max-height: 550px;">
                    <table class="table table-hover table-bordered" id="stock-table">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th width="50" class="text-center">#</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th class="text-center" width="150">In-Stock</th>
                                <th class="text-center" width="120">Measurement</th>
                                <th width="150" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $i = 1; @endphp
                            @forelse($counterStockItems as $item)
                            <tr class="stock-row" data-name="{{ strtolower($item['item_name']) }} {{ strtolower($item['category']) }}" data-variant-id="{{ $item['variant_id'] }}">
                                <td class="text-center align-middle font-weight-bold">{{ $i++ }}</td>
                                <td class="align-middle h5 font-weight-bold">{{ $item['item_name'] }}</td>
                                <td class="align-middle"><span class="badge badge-secondary p-1 px-2">{{ $item['category'] }}</span></td>
                                <td class="text-center align-middle">
                                    <span class="h4 mb-0 font-weight-bold text-success qty-display">{{ number_format($item['quantity']) }}</span>
                                    <small class="text-muted d-block">{{ $item['quantity_unit'] }}s</small>
                                </td>
                                <td class="text-center align-middle font-weight-bold h5 mb-0">{{ $item['measurement'] }}</td>
                                <td class="text-center align-middle">
                                    <div class="d-flex align-items-center justify-content-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger mr-2 py-1 px-2 btn-report-shortage" 
                                                style="border-radius: 6px;"
                                                title="Report Shortage"
                                                onclick="event.stopPropagation(); openShortageModal({{ $item['variant_id'] }}, '{{ addslashes($item['item_name']) }}', {{ $item['quantity'] }}, this)">
                                            <i class="fa fa-minus-circle"></i> Short
                                        </button>
                                        <input type="checkbox" class="stock-check-input" style="width: 25px; height: 25px; cursor: pointer;">
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center p-5">No items found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- CARD VIEW (Hidden by default) -->
            <div id="card-view-container" style="display: none;">
                <div class="row" id="stock-cards-grid">
                    @forelse($counterStockItems as $item)
                    <div class="col-md-3 col-sm-6 mb-3 stock-card-wrapper" data-name="{{ strtolower($item['item_name']) }} {{ strtolower($item['category']) }}" data-variant-id="{{ $item['variant_id'] }}">
                        <div class="card h-100 stock-card p-3 text-center">
                            <span class="badge badge-secondary mb-2 align-self-start">{{ $item['category'] }}</span>
                            <h5 class="font-weight-bold mb-3 text-dark" style="height: 44px; overflow: hidden;">{{ $item['item_name'] }}</h5>
                            
                            <div class="my-3">
                                <span class="qty-badge">{{ number_format($item['quantity']) }}</span>
                                <span class="unit-label d-block">{{ $item['quantity_unit'] }}s</span>
                            </div>
                            
                            <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                                <span class="badge badge-light border p-1 px-3 font-weight-bold">{{ $item['measurement'] }}</span>
                                <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2 btn-report-shortage-card"
                                        style="border-radius: 6px;"
                                        onclick="event.stopPropagation(); openShortageModal({{ $item['variant_id'] }}, '{{ addslashes($item['item_name']) }}', {{ $item['quantity'] }}, this)">
                                    <i class="fa fa-minus-circle"></i> Short
                                </button>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="col-12 text-center p-5">No items found.</div>
                    @endforelse
                </div>
            </div>

            <!-- Start Shift Footer -->
            <div class="tile-footer border-top mt-4 pt-4">
                <form action="{{ route('bar.shifts.store') }}" method="POST" id="start-shift-form">
                    @csrf
                    <div class="row align-items-center">
                        <div class="col-md-9">
                            <input type="text" name="notes" class="form-control form-control-lg border-dashed" 
                                   placeholder="Add any notes here...">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary btn-block btn-lg shadow py-3 font-weight-bold" type="submit" id="start-shift-btn">
                                <i class="fa fa-play mr-2"></i> START SESSION
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Report Stock Shortage Modal -->
<div class="modal fade shadow" id="shortageModal" tabindex="-1" role="dialog" aria-labelledby="stockShortageTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content border-0 overflow-hidden" style="border-radius: 15px;">
      <div class="modal-header bg-danger text-white py-3">
        <h5 class="modal-title h6 mb-0 text-white" id="stockShortageTitle">
            <i class="fa fa-minus-circle mr-2"></i> REPORT STOCK SHORTAGE
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body p-4 bg-light">
        <form id="shortageForm">
          @csrf
          <input type="hidden" id="shortage-variant-id" name="product_variant_id">
          
          <div class="tile mb-0 p-3 shadow-sm border-0" style="border-radius: 10px;">
              <div class="mb-3 text-center border-bottom pb-2">
                  <h6 id="shortage-product-name" class="font-weight-bold text-dark mb-1">Product Name</h6>
                  <p class="small text-muted mb-0">Record actual physical stock to log discrepancies before starting shift</p>
              </div>
              
              <div class="form-group mb-3">
                  <label class="control-label font-weight-bold mb-1">Current Expected Stock</label>
                  <input type="text" id="shortage-expected-display" class="form-control font-weight-bold bg-white text-dark" readonly style="border-radius: 8px;">
              </div>

              <div class="form-group mb-3">
                  <label class="control-label font-weight-bold mb-1">Physical Stock Count</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text bg-white border-right-0" style="border-radius: 8px 0 0 8px;"><i class="fa fa-calculator text-danger"></i></span>
                    </div>
                    <input type="number" id="shortage-physical-count" name="physical_count" class="form-control font-weight-bold" min="0" placeholder="Enter counted physical quantity" required style="border-radius: 0 8px 8px 0; padding-left: 10px;">
                  </div>
                  <div id="shortage-calc-feedback" class="mt-2 smallest font-weight-bold text-danger d-none">
                      <i class="fa fa-exclamation-triangle"></i> Shortage amount: <span id="shortage-qty-calculated">0</span> units
                  </div>
              </div>

              <div class="form-group mb-0">
                  <label class="control-label font-weight-bold mb-1">Notes / Explanation <span class="text-danger">*</span></label>
                  <textarea id="shortage-notes" name="notes" class="form-control" rows="3" placeholder="Explain why this shortage happened (e.g. broken bottle, missing during stocktake)..." style="border-radius: 8px;" required></textarea>
              </div>
          </div>
        </form>
      </div>
      <div class="modal-footer bg-light border-0 px-4 pb-4 pt-0">
        <button type="button" class="btn btn-secondary shadow-sm font-weight-bold px-4" style="border-radius: 8px;" data-dismiss="modal">CANCEL</button>
        <button type="button" class="btn btn-danger shadow-sm font-weight-bold px-4" style="border-radius: 8px;" id="saveShortageBtn">REPORT SHORTAGE</button>
      </div>
    </div>
  </div>
</div>
</div>

@push('scripts')
<script>
var _currentShortageExpected = 0;
var _currentTriggerButton = null;

function openShortageModal(id, name, expected, btn) {
  _currentTriggerButton = btn;
  $('#shortage-variant-id').val(id);
  $('#shortage-product-name').text(name);
  $('#shortage-expected-display').val(expected);
  _currentShortageExpected = parseFloat(expected);
  $('#shortage-physical-count').val('');
  $('#shortage-calc-feedback').addClass('d-none');
  $('#shortage-notes').val('');
  $('#shortageModal').modal('show');
}

$(document).ready(function() {
    // Shortage live calculations
    $('#shortage-physical-count').on('input', function() {
        const physical = parseFloat($(this).val());
        if (isNaN(physical) || physical >= _currentShortageExpected) {
            $('#shortage-calc-feedback').addClass('d-none');
            return;
        }
        const diff = _currentShortageExpected - physical;
        $('#shortage-qty-calculated').text(diff);
        $('#shortage-calc-feedback').removeClass('d-none');
    });

    // Save Shortage AJAX
    $('#saveShortageBtn').on('click', function() {
        const btn = $(this);
        const form = $('#shortageForm');
        const physicalVal = parseFloat($('#shortage-physical-count').val());
        
        if (isNaN(physicalVal) || physicalVal < 0) {
            alert('Please enter a valid physical count of 0 or greater.');
            return;
        }

        if (physicalVal >= _currentShortageExpected) {
            alert('Physical count must be less than current expected stock to report a shortage.');
            return;
        }

        const notesVal = $('#shortage-notes').val().trim();
        if (notesVal === '') {
            alert('Please provide Notes / Explanation for this shortage.');
            $('#shortage-notes').focus();
            return;
        }

        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> REPORTING...');

        $.ajax({
            url: "{{ route('bar.stock-shortages.store') }}",
            method: 'POST',
            data: form.serialize(),
            success: function(response) {
                if (response.success) {
                    const id = $('#shortage-variant-id').val();
                    const newQty = physicalVal;
                    const name = $('#shortage-product-name').text();

                    // Update Table view
                    const row = $(`.stock-row[data-variant-id="${id}"]`);
                    if (row.length) {
                        row.find('.qty-display').text(newQty);
                        row.find('.btn-report-shortage').attr('onclick', `event.stopPropagation(); openShortageModal(${id}, '${name.replace(/'/g, "\\'")}', ${newQty}, this)`);
                    }

                    // Update Card view
                    const cardWrapper = $(`.stock-card-wrapper[data-variant-id="${id}"]`);
                    if (cardWrapper.length) {
                        cardWrapper.find('.qty-badge').text(newQty);
                        cardWrapper.find('.btn-report-shortage-card').attr('onclick', `event.stopPropagation(); openShortageModal(${id}, '${name.replace(/'/g, "\\'")}', ${newQty}, this)`);
                    }

                    $('#shortageModal').modal('hide');

                    $.notify({
                        title: "Success: ",
                        message: response.message || "Shortage reported and stock adjusted successfully.",
                        icon: 'fa fa-check' 
                    },{
                        type: "success"
                    });
                }
            },
            error: function(xhr) {
                const err = xhr.responseJSON ? xhr.responseJSON.error : 'Network error. Failed to report shortage.';
                alert(err);
            },
            complete: function() {
                btn.prop('disabled', false).text('REPORT SHORTAGE');
            }
        });
    });

    // View Switcher
    $('#btn-table-view').on('click', function() {
        $('.view-toggle-btn').removeClass('active');
        $(this).addClass('active');
        $('#table-view-container').fadeIn();
        $('#card-view-container').hide();
    });

    $('#btn-card-view').on('click', function() {
        $('.view-toggle-btn').removeClass('active');
        $(this).addClass('active');
        $('#card-view-container').fadeIn();
        $('#table-view-container').hide();
    });

    // Search
    $("#stock-search").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $(".stock-row, .stock-card-wrapper").filter(function() {
            $(this).toggle($(this).data('name').indexOf(value) > -1)
        });
    });

    // Verification - Table
    $('.stock-check-input').on('change', function() {
        $(this).closest('tr').toggleClass('table-success', $(this).is(':checked'));
    });

    // Verification - Cards
    $('.stock-card').on('click', function() {
        $(this).toggleClass('verified');
    });

    // Prevent Double Submission
    $('#start-shift-form').on('submit', function() {
        var btn = $('#start-shift-btn');
        btn.prop('disabled', true);
        btn.html('<i class="fa fa-spinner fa-spin mr-2"></i> STARTING...');
        return true;
    });
});
</script>
<style>
    .border-dashed { border-style: dashed !important; border-width: 2px !important; }
</style>
@endpush
@endsection
