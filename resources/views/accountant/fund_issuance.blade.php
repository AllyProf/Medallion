@extends('layouts.dashboard')

@section('title', 'Fund Issuance (Petty Cash)')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-money"></i> Fund Issuance (Petty Cash)</h1>
    <p>Issue funds to Chef or Stock Keeper for procurement</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="#">Accountant</a></li>
    <li class="breadcrumb-item">Fund Issuance</li>
  </ul>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-title-w-btn">
        <h3 class="title">History of Issued Funds</h3>
        <button class="btn btn-primary icon-btn" type="button" data-toggle="modal" data-target="#issueFundsModal">
          <i class="fa fa-plus"></i> Issue New Funds
        </button>
      </div>
      
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover table-bordered">
            <thead>
              <tr>
                <th>Dept</th>
                <th>Date</th>
                <th>Recipient</th>
                <th>Amount</th>
                <th>Source</th>
                <th>Purpose</th>
                <th>Issued By</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($issues as $issue)
                @php
                  $isFood = str_contains($issue->purpose, '[FOOD]');
                  $cleanPurpose = str_replace('[FOOD] ', '', $issue->purpose);
                @endphp
                <tr>
                  <td>
                    @if($isFood)
                      <span class="badge badge-primary">FOOD</span>
                    @else
                      <span class="badge badge-info text-white">DRINK</span>
                    @endif
                  </td>
                  <td>{{ $issue->issue_date->format('M d, Y') }}</td>
                  <td><strong>{{ $issue->recipient->full_name }}</strong></td>
                  <td class="font-weight-bold text-dark">TSh {{ number_format($issue->amount) }}</td>
                  <td>
                    <span class="badge {{ $issue->fund_source === 'profit' ? 'badge-info' : 'badge-secondary' }}">
                        {{ strtoupper($issue->fund_source ?? 'CIRCULATION') }}
                    </span>
                  </td>
                  <td>{{ $cleanPurpose }}</td>
                  <td>{{ $issue->issuer->name }}</td>
                  <td>
                    @if($issue->status === 'issued')
                      <span class="badge badge-warning">Issued</span>
                    @elseif($issue->status === 'completed')
                      <span class="badge badge-success">Completed</span>
                    @else
                      <span class="badge badge-danger">Cancelled</span>
                    @endif
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <a href="{{ route('accountant.fund-issuance.print', $issue->id) }}" class="btn btn-secondary" target="_blank" title="Print Voucher">
                        <i class="fa fa-print"></i>
                      </a>
                      <button class="btn btn-info edit-issue-btn" 
                              data-id="{{ $issue->id }}"
                              data-staff="{{ $issue->staff_id }}"
                              data-amount="{{ $issue->amount }}"
                              data-source="{{ $issue->fund_source }}"
                              data-purpose="{{ $cleanPurpose }}"
                              data-department="{{ $isFood ? 'food' : 'bar' }}"
                              data-date="{{ $issue->issue_date->format('Y-m-d') }}"
                              data-notes="{{ $issue->notes }}">
                        <i class="fa fa-pencil"></i>
                      </button>
                      <button class="btn btn-danger delete-issue-btn" data-id="{{ $issue->id }}">
                        <i class="fa fa-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="9" class="text-center">No fund issuances recorded yet.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-center">
            {{ $issues->links() }}
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Issue Funds Modal -->
<div class="modal fade" id="issueFundsModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Issue Funds to Staff</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form action="{{ route('accountant.fund-issuance.store') }}" method="POST">
        @csrf
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold text-primary"><i class="fa fa-building"></i> Target Department</label>
                <select name="department" class="form-control font-weight-bold" required>
                  <option value="bar">DRINKS (Bar Inventory)</option>
                  <option value="food">FOOD (Kitchen Market)</option>
                </select>
                <small class="text-muted">Determines which "Final Cash" is deducted.</small>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold"><i class="fa fa-user"></i> Recipient Staff</label>
                <select name="staff_id" class="form-control" required>
                  <option value="">-- Select Recipient --</option>
                  @foreach($staffMembers as $staff)
                    <option value="{{ $staff->id }}">{{ $staff->full_name }} ({{ $staff->role->name ?? 'Staff' }})</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold"><i class="fa fa-money"></i> Amount (TSh)</label>
                <input type="number" name="amount" class="form-control" min="0" required placeholder="0.00">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold"><i class="fa fa-bank"></i> Fund Source</label>
                <select name="fund_source" class="form-control" required>
                  <option value="circulation">Money in Circulation (Capital)</option>
                  <option value="profit">Today's Profit (Earnings)</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold"><i class="fa fa-calendar"></i> Issue Date</label>
                <input type="date" name="issue_date" class="form-control" value="{{ date('Y-m-d') }}" required>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label class="font-weight-bold"><i class="fa fa-info-circle"></i> Purpose / Description</label>
            <input type="text" name="purpose" class="form-control" required placeholder="e.g. Market purchase for kitchen">
          </div>
          <div class="form-group">
            <label class="font-weight-bold">Additional Notes <small>(Internal use)</small></label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Any specific details..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Issue Funds Now</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Funds Modal -->
<div class="modal fade" id="editFundsModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title"><i class="fa fa-pencil"></i> Edit Fund Issuance</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="editFundsForm" method="POST">
        @csrf
        @method('PUT')
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold">Recipient Staff</label>
                <select name="staff_id" id="edit_staff_id" class="form-control" required>
                  @foreach($staffMembers as $staff)
                    <option value="{{ $staff->id }}">{{ $staff->full_name }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold">Amount (TSh)</label>
                <input type="number" name="amount" id="edit_amount" class="form-control" min="0" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold">Fund Source</label>
                <select name="fund_source" id="edit_fund_source" class="form-control" required>
                  <option value="circulation">Money in Circulation (Capital)</option>
                  <option value="profit">Today's Profit (Earnings)</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold">Issue Date</label>
                <input type="date" name="issue_date" id="edit_issue_date" class="form-control" required>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label class="font-weight-bold">Purpose / Description</label>
            <input type="text" name="purpose" id="edit_purpose" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="font-weight-bold">Additional Notes</label>
            <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-info">Update Record</button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Department change listener: If Food, force source to Profit
    $('select[name="department"]').change(function() {
        const sourceSelect = $('select[name="fund_source"]');
        if ($(this).val() === 'food') {
            sourceSelect.val('profit').prop('disabled', false); // Keep enabled but pre-selected
            // Optionally we can show an info note
        }
    });

    // Handle form submission to ensure disabled fields are sent or value is correct
    $('form').submit(function() {
        if ($('select[name="department"]').val() === 'food') {
            $('select[name="fund_source"]').val('profit');
        }
    });

    // Edit Modal Populating
    $('.edit-issue-btn').click(function() {
        const id = $(this).data('id');
        $('#edit_staff_id').val($(this).data('staff'));
        $('#edit_amount').val($(this).data('amount'));
        $('#edit_fund_source').val($(this).data('source'));
        $('#edit_purpose').val($(this).data('purpose'));
        $('#edit_issue_date').val($(this).data('date'));
        $('#edit_notes').val($(this).data('notes'));
        
        $('#editFundsForm').attr('action', `{{ route('accountant.fund-issuance.update', ':id') }}`.replace(':id', id));
        $('#editFundsModal').modal('show');
    });

    // Delete Confirmation
    $('.delete-issue-btn').click(function() {
        const id = $(this).data('id');
        
        Swal.fire({
          title: 'Are you sure?',
          text: "Are you sure you want to delete this petty cash record? The money will be returned to the rollover calculation.",
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          cancelButtonColor: '#3085d6',
          confirmButtonText: 'Yes, Delete it!',
          cancelButtonText: 'No, Cancel'
        }).then((result) => {
          if (result.isConfirmed) {
            const form = $('<form>', {
                action: `{{ route('accountant.fund-issuance.delete', ':id') }}`.replace(':id', id),
                method: 'POST'
            }).append($('<input>', { type: 'hidden', name: '_token', value: '{{ csrf_token() }}' }))
              .append($('<input>', { type: 'hidden', name: '_method', value: 'DELETE' }));
            
            $('body').append(form);
            form.submit();
          }
        });
    });

    $('.update-status-btn').click(function() {
        const id = $(this).data('id');
        const status = $(this).data('status');
        const confirmText = status === 'completed' ? 'Mark this fund issuance as completed?' : 'Cancel this fund issuance?';
        
        if (confirm(confirmText)) {
            $.post(`{{ route('accountant.fund-issuance.update-status', ':id') }}`.replace(':id', id), {
                _token: '{{ csrf_token() }}',
                status: status
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        }
    });
});
</script>
@endsection
