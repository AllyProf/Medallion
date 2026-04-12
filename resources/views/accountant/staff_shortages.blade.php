@extends('layouts.dashboard')

@section('title', 'Staff Shortages Archive')

@push('styles')
<style>
  .shortage-card {
    border-radius: 12px;
    border-left: 5px solid #dc3545;
    transition: transform 0.2s, box-shadow 0.2s;
  }
  .shortage-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(220, 53, 69, 0.2) !important;
  }
  .waiter-avatar {
    width: 52px; height: 52px; border-radius: 50%;
    background: linear-gradient(135deg, #ffebee, #ffcdd2);
    color: #dc3545; display: flex; align-items: center;
    justify-content: center; font-size: 1.5rem; font-weight: bold;
    flex-shrink: 0;
  }
  .money-column { font-family: 'Courier New', Courier, monospace; }
  .shift-row { transition: background 0.15s; }
  .shift-row:hover { background: #fff8f8; }

  /* Settle button */
  .btn-settle {
    font-size: 0.7rem; padding: 2px 10px;
    background: #fd7e14; border: none; color: #fff;
    border-radius: 20px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
    transition: background 0.2s, transform 0.1s;
  }
  .btn-settle:hover { background: #e96b00; transform: scale(1.05); color: #fff; }

  /* Summary widget */
  .summary-strip {
    background: linear-gradient(135deg, #dc3545, #a71d2a);
    border-radius: 12px; color: #fff; padding: 20px 28px;
  }
</style>
@endpush

@section('content')

<div class="app-title">
  <div>
    <h1><i class="fa fa-exclamation-triangle text-danger"></i> Outstanding Staff Shortages</h1>
    <p class="text-muted mb-0">Settle waiter debts directly from this page — no navigation needed.</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item">Accountant</li>
    <li class="breadcrumb-item active">Staff Shortages</li>
  </ul>
</div>

{{-- Summary Banner --}}
<div class="row mb-4">
  <div class="col-md-5">
    <div class="summary-strip shadow">
      <div class="d-flex align-items-center">
        <i class="fa fa-money fa-3x mr-3 opacity-75"></i>
        <div>
          <div style="font-size:0.8rem; opacity:0.8; text-transform:uppercase; letter-spacing:1px;">Total Owed to Business</div>
          <div class="money-column" style="font-size:1.8rem; font-weight:700; letter-spacing:1px;">
            TSh {{ number_format($totalOutstandingShortages) }}
          </div>
          <div style="font-size:0.75rem; opacity:0.7;">
            {{ count($staffShortageSummaries) }} staff member(s) outstanding
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Cards --}}
<div class="row">
  @if(count($staffShortageSummaries) > 0)
    @foreach($staffShortageSummaries as $summary)
      <div class="col-md-6 col-lg-4 mb-4">
        <div class="tile shortage-card shadow-sm h-100 p-0" style="overflow:hidden;">

          {{-- Card Header --}}
          <div class="d-flex align-items-center p-3 border-bottom" style="background:#fff5f5;">
            <div class="waiter-avatar mr-3">
              <i class="fa fa-user"></i>
            </div>
            <div>
              <h5 class="mb-0 font-weight-bold text-dark">
                {{ $summary['waiter']->full_name ?? 'Unknown Waiter' }}
              </h5>
              <span class="text-danger font-weight-bold money-column" style="font-size:0.9rem;">
                Total Debt: TSh {{ number_format($summary['total_owed']) }}
              </span>
            </div>
          </div>

          {{-- Shift Breakdown --}}
          <div class="p-0">
            <table class="table table-sm mb-0" style="font-size:0.82rem;">
              <thead class="bg-light text-muted" style="font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px;">
                <tr>
                  <th class="pl-3">Date</th>
                  <th class="text-center">Dept</th>
                  <th class="text-right">Short</th>
                  <th class="text-center" style="width:80px;">Action</th>
                </tr>
              </thead>
              <tbody>
                @foreach($summary['records'] as $record)
                  <tr class="shift-row" id="row-{{ $record->id }}">
                    <td class="pl-3">
                      <i class="fa fa-calendar-o text-muted mr-1"></i>
                      {{ \Carbon\Carbon::parse($record->reconciliation_date)->format('D, d M Y') }}
                    </td>
                    <td class="text-center">
                      @if($record->reconciliation_type === 'food')
                        <span class="badge badge-info" style="font-size: 0.6rem; letter-spacing: 0.5px;">KITCHEN</span>
                      @else
                        <span class="badge badge-warning" style="font-size: 0.6rem; letter-spacing: 0.5px;">DRINKS</span>
                      @endif
                    </td>
                    <td class="text-right text-danger font-weight-bold money-column">
                      - {{ number_format(abs($record->difference)) }}
                    </td>
                    <td class="text-center">
                      <button class="btn-settle settle-btn"
                        data-rec-id="{{ $record->id }}"
                        data-waiter="{{ $summary['waiter']->full_name ?? 'Unknown' }}"
                        data-date="{{ \Carbon\Carbon::parse($record->reconciliation_date)->format('D, d M Y') }}"
                        data-amount="{{ abs($record->difference) }}">
                        Settle
                      </button>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    @endforeach
  @else
    <div class="col-md-12">
      <div class="tile shadow-sm text-center py-5" style="border-radius:15px;">
        <i class="fa fa-check-circle text-success" style="font-size: 4rem;"></i>
        <h4 class="mt-3 text-dark font-weight-bold">All Clear!</h4>
        <p class="text-muted">No outstanding shortages. All staff accounts are settled.</p>
      </div>
    </div>
  @endif
</div>

{{-- Recent Settlement History --}}
<div class="row mt-5">
    <div class="col-md-12">
        <div class="tile shadow-sm" style="border-radius:15px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="font-weight-bold mb-0">
                    <i class="fa fa-history text-info mr-2"></i> Recent Settlement History (30 Days)
                </h4>
                <span class="badge badge-info p-2 px-3">{{ count($settlementHistory) }} Entries</span>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead class="bg-light text-muted" style="font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px;">
                        <tr>
                            <th class="pl-3 py-2">Settlement Date</th>
                            <th>Staff Member</th>
                            <th class="text-center">Shift Info</th>
                            <th>Method</th>
                            <th class="text-right">Amount Paid</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($settlementHistory as $history)
                            <tr class="align-middle">
                                <td class="pl-3 py-2">
                                    <span class="font-weight-bold">{{ \Carbon\Carbon::parse($history['date'])->format('d M, H:i') }}</span>
                                </td>
                                <td>
                                    <div class="font-weight-bold text-dark">{{ $history['waiter_name'] }}</div>
                                    <small class="text-muted">Recorded by {{ $history['recorded_by'] }}</small>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light border">{{ $history['dept'] }}</span>
                                    <div class="small text-muted mt-1">{{ $history['shift_date'] }}</div>
                                </td>
                                <td>
                                    @php
                                        $channel = $history['channel'] ?? 'cash';
                                        $label = strtoupper(str_replace('_', ' ', $channel));
                                        $class = 'badge-light border';
                                        if($channel === 'salary_deduction') $class = 'badge-warning';
                                        elseif($channel === 'cash') $class = 'badge-success text-white';
                                    @endphp
                                    <span class="badge {{ $class }} px-2" style="font-size: 0.65rem;">{{ $label }}</span>
                                </td>
                                <td class="text-right font-weight-bold text-success">
                                    TSh {{ number_format($history['amount']) }}
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-danger undo-settle-btn px-2 p-1" 
                                            data-id="{{ $history['id'] }}" 
                                            data-reconciliation="{{ $history['reconciliation_id'] }}"
                                            title="Undo settlement">
                                        <i class="fa fa-undo"></i> Undo
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fa fa-history fa-2x mb-2 opacity-50"></i>
                                    <p>No recent settlements found in the last 30 days.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Settlement Modal --}}
<div class="modal fade" id="settleModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content" style="border-radius:12px; border:none; overflow:hidden;">
      <div class="modal-header" style="background:linear-gradient(135deg, #dc3545,#a71d2a); color:#fff; border:none;">
        <h5 class="modal-title font-weight-bold">
          <i class="fa fa-money mr-2"></i> Record Shortage Payment
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body p-4">
        <div class="alert alert-warning py-2 mb-3" style="border-radius:8px; font-size:0.85rem;">
          <i class="fa fa-info-circle mr-1"></i>
          The recovered cash will be added to the vault for that shift's date, increasing the Rollover Float.
        </div>
        <div class="mb-3">
          <label class="text-muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px;">Staff Member</label>
          <div class="font-weight-bold text-dark" id="modal-waiter-name" style="font-size:1rem;"></div>
        </div>
        <div class="mb-3">
          <label class="text-muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px;">Shortage Date</label>
          <div class="font-weight-bold" id="modal-date" style="font-size:0.9rem;"></div>
        </div>
        <div class="mb-3">
          <label class="font-weight-bold">Amount Received (TSh)</label>
          <input type="number" id="modal-amount" class="form-control" style="font-size:1.1rem; font-weight:700;">
          <small class="text-muted">Outstanding: <span class="text-danger font-weight-bold" id="modal-max-amount"></span></small>
        </div>
        <div class="mb-3">
          <label class="font-weight-bold">Payment channel</label>
          <select id="modal-channel" class="form-control">
            <option value="cash">Cash (Vault)</option>
            <option value="mobile_money">Mobile Money</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="pos_card">POS / Card</option>
            <option value="salary_deduction">Deduct from Salary</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="font-weight-bold">Notes <small class="text-muted font-weight-normal">(optional)</small></label>
          <textarea id="modal-notes" class="form-control" rows="2" placeholder="e.g. Cash in hand, deducted from salary…"></textarea>
        </div>
        <input type="hidden" id="modal-rec-id">
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success font-weight-bold px-4" id="confirmSettleBtn">
          <i class="fa fa-check mr-1"></i> Confirm Payment
        </button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {

  // Open settlement modal
  $(document).on('click', '.settle-btn', function() {
    const btn = $(this);
    $('#modal-rec-id').val(btn.data('rec-id'));
    $('#modal-waiter-name').text(btn.data('waiter'));
    $('#modal-date').text(btn.data('date'));
    $('#modal-amount').val(btn.data('amount'));
    $('#modal-max-amount').text('TSh ' + Number(btn.data('amount')).toLocaleString());
    $('#modal-notes').val('');
    $('#settleModal').modal('show');
  });

  // Submit settlement
  $('#confirmSettleBtn').on('click', function() {
    const btn = $(this);
    const recId = $('#modal-rec-id').val();
    const amount = parseFloat($('#modal-amount').val());
    const notes  = $('#modal-notes').val();
    const max    = parseFloat($('#modal-amount').attr('max') || 9999999);

    if (!amount || amount <= 0) {
      Swal.fire('Invalid', 'Please enter a valid payment amount.', 'warning');
      return;
    }

    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Processing...');

    $.ajax({
      url: '{{ route("accountant.counter.settle-shortage") }}',
      method: 'POST',
      data: {
        _token: '{{ csrf_token() }}',
        reconciliation_id: recId,
        amount: amount,
        notes: notes,
        channel: $('#modal-channel').val()
      },
      success: function(resp) {
        if (resp.success) {
          $('#settleModal').modal('hide');
          Swal.fire({
            icon: 'success',
            title: 'Payment Recorded!',
            text: resp.message,
            timer: 2000,
            showConfirmButton: false
          }).then(() => {
            // Remove the settled row from the table
            $('#row-' + recId).fadeOut(400, function() {
              $(this).remove();
              // If no more rows in the card, reload to show updated totals
              location.reload();
            });
          });
        } else {
          Swal.fire('Error', resp.error || 'Failed to settle shortage.', 'error');
          btn.prop('disabled', false).html('<i class="fa fa-check mr-1"></i> Confirm Payment');
        }
      },
      error: function(xhr) {
        const err = xhr.responseJSON?.error || 'A server error occurred.';
        Swal.fire('Error', err, 'error');
        btn.prop('disabled', false).html('<i class="fa fa-check mr-1"></i> Confirm Payment');
      }
    });
  });

  // Undo Settlement logic
  $(document).on('click', '.undo-settle-btn', function() {
    const settlementId = $(this).data('id');
    const reconciliationId = $(this).data('reconciliation');
    const $btn = $(this);
    
    Swal.fire({
      title: 'Reverse this settlement?',
      text: "The payment will be removed and the staff debt will be restored.",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545',
      confirmButtonText: 'Yes, Reverse It'
    }).then((result) => {
      if (result.isConfirmed) {
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        
        $.post("{{ route('accountant.counter.undo-settle-shortage') }}", {
          _token: "{{ csrf_token() }}",
          reconciliation_id: reconciliationId,
          settlement_id: settlementId
        }, function(resp) {
          if (resp.success) {
            Swal.fire('Reversed!', resp.message, 'success').then(() => location.reload());
          } else {
            Swal.fire('Error', resp.error || 'Undo failed', 'error');
            $btn.prop('disabled', false).html('<i class="fa fa-undo"></i> Undo');
          }
        }).fail(function() {
          Swal.fire('Error', 'Server communication error', 'error');
          $btn.prop('disabled', false).html('<i class="fa fa-undo"></i> Undo');
        });
      }
    });
  });

});
</script>
@endpush
