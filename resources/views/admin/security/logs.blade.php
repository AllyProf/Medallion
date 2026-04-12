@extends('layouts.dashboard')
@section('title', 'Activity Logs')

@section('content')
<div class="app-title">
  <div>
    <h1><i class="fa fa-list-alt"></i> Activity Logs</h1>
    <p>What has been happening in the system</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item">Super Admin</li>
    <li class="breadcrumb-item active">Activity Logs</li>
  </ul>
</div>

{{-- Search & Filter --}}
<div class="tile mb-3">
  <div class="tile-body">
    <div class="row align-items-center">
      {{-- Search --}}
      <div class="col-md-5 mb-2 mb-md-0">
        <div class="input-group">
          <span class="input-group-addon"><i class="fa fa-search"></i></span>
          <input type="text" id="logSearch" class="form-control" placeholder="Search events…">
          <span class="input-group-btn">
            <button class="btn btn-secondary" onclick="clearSearch()"><i class="fa fa-times"></i></button>
          </span>
        </div>
      </div>
      {{-- Level filter buttons --}}
      <div class="col-md-7">
        <div class="btn-group flex-wrap" role="group" id="levelFilters">
          <button type="button" class="btn btn-sm btn-dark active" onclick="setLevel('')">All</button>
          @foreach($levels as $lv)
            <button type="button" class="btn btn-sm btn-default" onclick="setLevel('{{ $lv }}')" data-level="{{ $lv }}">{{ $lv }}</button>
          @endforeach

        </div>
      </div>
    </div>
  </div>
</div>

{{-- Results tile --}}
<div class="tile">
  <div class="tile-title-w-btn">
    <h3 class="title">
      Showing <strong id="resultCount">{{ count($entries) }}</strong> of <strong>{{ count($entries) }}</strong> events
    </h3>
    <p class="btn-group">
      <a href="{{ route('admin.security.logs') }}" class="btn btn-sm btn-secondary">
        <i class="fa fa-refresh"></i> Refresh
      </a>
    </p>
  </div>
  <div class="tile-body p-0">
    @if(empty($entries))
      <div class="text-center text-muted py-5">
        <i class="fa fa-inbox fa-3x mb-3" style="display:block;"></i>
        No events found in the log file.
      </div>
    @else
      <table class="table table-hover mb-0" id="logsTable">
        <thead>
          <tr>
            <th style="width:90px;">Status</th>
            <th style="width:140px;">Time</th>
            <th>What happened</th>
            <th style="width:50px;"></th>
          </tr>
        </thead>
        <tbody id="logsBody">
          @foreach($entries as $i => $entry)
            @php
              $badgeClass = match($entry->level) {
                'INFO'                          => 'badge-primary',
                'WARNING', 'ALERT'              => 'badge-warning',
                'ERROR','CRITICAL','EMERGENCY'  => 'badge-danger',
                default                         => 'badge-secondary',
              };
              $hasDetails = !empty($entry->context) || !empty($entry->trace);
              $dt = \Carbon\Carbon::parse($entry->datetime);
            @endphp
            <tr class="log-row" data-level="{{ $entry->level }}"
                data-text="{{ strtolower(strip_tags($entry->message)) }} {{ strtolower($entry->level) }} {{ $dt->format('d M H:i') }}">
              <td>
                <span class="badge {{ $badgeClass }}" style="font-size:11px; padding:4px 8px;">
                  {{ $entry->level }}
                </span>
              </td>
              <td class="text-muted" style="font-size:12px; white-space:nowrap;">
                <i class="fa fa-clock-o"></i> {{ $dt->format('d M H:i') }}
              </td>
              <td style="font-size:14px;">{!! $entry->message !!}</td>
              <td class="text-center">
                @if($hasDetails)
                  <button class="btn btn-sm btn-default" type="button"
                          data-toggle="collapse" data-target="#detail-{{ $i }}">
                    <i class="fa fa-info-circle text-muted"></i>
                  </button>
                @endif
              </td>
            </tr>
            @if($hasDetails)
              <tr class="collapse log-detail-row" id="detail-{{ $i }}"
                  data-level="{{ $entry->level }}"
                  data-text="{{ strtolower(strip_tags($entry->message)) }}">
                <td colspan="4" style="background:#f8f9fa; padding:10px 18px;">
                  @if(!empty($entry->context))
                    <strong><i class="fa fa-code"></i> Details:</strong>
                    <pre style="background:#fff; border:1px solid #eee; border-radius:6px; padding:10px; font-size:12px; max-height:150px; overflow-y:auto; margin:6px 0 0;">{{ $entry->context }}</pre>
                  @endif
                  @if(!empty($entry->trace))
                    <strong class="d-block mt-2"><i class="fa fa-list"></i> Technical trace:</strong>
                    <pre style="background:#1e1e1e; color:#d4d4d4; border-radius:6px; padding:10px; font-size:11px; max-height:160px; overflow-y:auto; margin:4px 0 0;">{{ implode("\n", array_slice($entry->trace, 0, 15)) }}</pre>
                  @endif
                </td>
              </tr>
            @endif
          @endforeach
        </tbody>
      </table>

      {{-- Bootstrap Pagination --}}
      <div class="d-flex justify-content-between align-items-center p-3" id="paginationWrap">
        <small class="text-muted" id="pageInfo"></small>
        <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
      </div>
    @endif
  </div>
</div>

@push('scripts')
<script>
(function () {
  var PER_PAGE   = 15;
  var currentPage= 1;
  var activeLevel= '';
  var searchTerm = '';

  var allRows = Array.from(document.querySelectorAll('#logsBody .log-row'));
  var totalAll = {{ count($entries) }};

  /*── Helpers ─────────────────────────────────────────────*/
  function isDetailRow(tr) { return tr.classList.contains('log-detail-row'); }

  function visibleRows() {
    return allRows.filter(function(tr) {
      var levelOk  = !activeLevel || tr.dataset.level === activeLevel;
      var searchOk = !searchTerm  || tr.dataset.text.includes(searchTerm);
      return levelOk && searchOk;
    });
  }

  /*── Render ───────────────────────────────────────────────*/
  function render() {
    var rows  = visibleRows();
    var total = rows.length;
    var pages = Math.ceil(total / PER_PAGE) || 1;

    if (currentPage > pages) currentPage = 1;

    var start = (currentPage - 1) * PER_PAGE;
    var end   = start + PER_PAGE;
    var pageRows = rows.slice(start, end);

    // Hide all rows + their detail rows
    allRows.forEach(function(tr) {
      tr.style.display = 'none';
      var rowId = tr.querySelector('[data-target]');
      if (rowId) {
        var detail = document.querySelector(rowId.dataset.target);
        if (detail) detail.style.display = 'none';
      }
    });

    // Show page rows
    pageRows.forEach(function(tr) {
      tr.style.display = '';
    });

    // Result count
    document.getElementById('resultCount').textContent = total;

    // Page info
    var from = total === 0 ? 0 : start + 1;
    var to   = Math.min(end, total);
    document.getElementById('pageInfo').textContent =
      'Showing ' + from + '–' + to + ' of ' + total + ' events';

    // Build pagination
    buildPagination(pages);
  }

  function buildPagination(pages) {
    var ul = document.getElementById('pagination');
    ul.innerHTML = '';
    if (pages <= 1) return;

    // Prev
    ul.insertAdjacentHTML('beforeend',
      '<li class="page-item ' + (currentPage === 1 ? 'disabled' : '') + '">' +
      '<a class="page-link" href="#" onclick="goPage(' + (currentPage - 1) + ');return false;">&laquo;</a></li>');

    // Pages (show window of 5)
    var startP = Math.max(1, currentPage - 2);
    var endP   = Math.min(pages, startP + 4);
    if (endP - startP < 4) startP = Math.max(1, endP - 4);

    for (var p = startP; p <= endP; p++) {
      ul.insertAdjacentHTML('beforeend',
        '<li class="page-item ' + (p === currentPage ? 'active' : '') + '">' +
        '<a class="page-link" href="#" onclick="goPage(' + p + ');return false;">' + p + '</a></li>');
    }

    // Next
    ul.insertAdjacentHTML('beforeend',
      '<li class="page-item ' + (currentPage === pages ? 'disabled' : '') + '">' +
      '<a class="page-link" href="#" onclick="goPage(' + (currentPage + 1) + ');return false;">&raquo;</a></li>');
  }

  /*── Public API ───────────────────────────────────────────*/
  window.goPage = function(p) {
    currentPage = p;
    render();
    document.getElementById('logsTable').scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  window.setLevel = function(lv) {
    activeLevel  = lv;
    currentPage  = 1;
    // Update button styles
    document.querySelectorAll('#levelFilters button').forEach(function(btn) {
      btn.classList.remove('active', 'btn-dark', 'btn-primary', 'btn-warning', 'btn-danger');
      btn.classList.add('btn-default');
    });
    var clicked = lv
      ? document.querySelector('#levelFilters [data-level="' + lv + '"]')
      : document.querySelector('#levelFilters button:first-child');
    if (clicked) {
      var colorMap = {'INFO':'btn-primary','WARNING':'btn-warning','ALERT':'btn-warning',
                      'ERROR':'btn-danger','CRITICAL':'btn-danger','EMERGENCY':'btn-danger'};
      clicked.classList.remove('btn-default');
      clicked.classList.add(lv ? (colorMap[lv] || 'btn-dark') : 'btn-dark', 'active');
    }
    render();
  };

  window.clearSearch = function() {
    document.getElementById('logSearch').value = '';
    searchTerm = '';
    currentPage = 1;
    render();
  };

  // Search input
  var searchTimer;
  document.getElementById('logSearch').addEventListener('input', function() {
    clearTimeout(searchTimer);
    var val = this.value.trim().toLowerCase();
    searchTimer = setTimeout(function() {
      searchTerm  = val;
      currentPage = 1;
      render();
    }, 300);
  });

  // Initial render
  render();
})();
</script>
@endpush
@endsection
