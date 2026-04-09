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
  <div class="btn-group" role="group">
    <button type="button" class="btn btn-outline-info view-toggle-btn active" id="btn-table-view"><i class="fa fa-list"></i> Table</button>
    <button type="button" class="btn btn-outline-info view-toggle-btn" id="btn-card-view"><i class="fa fa-th-large"></i> Cards</button>
  </div>
</div>

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
                                <th width="80" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $i = 1; @endphp
                            @forelse($counterStockItems as $item)
                            <tr class="stock-row" data-name="{{ strtolower($item['item_name']) }} {{ strtolower($item['category']) }}">
                                <td class="text-center align-middle font-weight-bold">{{ $i++ }}</td>
                                <td class="align-middle h5 font-weight-bold">{{ $item['item_name'] }}</td>
                                <td class="align-middle"><span class="badge badge-secondary p-1 px-2">{{ $item['category'] }}</span></td>
                                <td class="text-center align-middle">
                                    <span class="h4 mb-0 font-weight-bold text-success">{{ number_format($item['quantity']) }}</span>
                                    <small class="text-muted d-block">{{ $item['quantity_unit'] }}s</small>
                                </td>
                                <td class="text-center align-middle font-weight-bold h5 mb-0">{{ $item['measurement'] }}</td>
                                <td class="text-center align-middle">
                                    <input type="checkbox" class="stock-check-input" style="width: 25px; height: 25px; cursor: pointer;">
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
                    <div class="col-md-3 col-sm-6 mb-3 stock-card-wrapper" data-name="{{ strtolower($item['item_name']) }} {{ strtolower($item['category']) }}">
                        <div class="card h-100 stock-card p-3 text-center">
                            <span class="badge badge-secondary mb-2 align-self-start">{{ $item['category'] }}</span>
                            <h5 class="font-weight-bold mb-3 text-dark" style="height: 44px; overflow: hidden;">{{ $item['item_name'] }}</h5>
                            
                            <div class="my-3">
                                <span class="qty-badge">{{ number_format($item['quantity']) }}</span>
                                <span class="unit-label d-block">{{ $item['quantity_unit'] }}s</span>
                            </div>
                            
                            <div class="mt-2 pt-2 border-top">
                                <span class="badge badge-light border p-1 px-3 font-weight-bold">{{ $item['measurement'] }}</span>
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
                <form action="{{ route('bar.shifts.store') }}" method="POST">
                    @csrf
                    <div class="row align-items-center">
                        <div class="col-md-9">
                            <input type="text" name="notes" class="form-control form-control-lg border-dashed" 
                                   placeholder="Add any notes here...">
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary btn-block btn-lg shadow py-3 font-weight-bold" type="submit">
                                <i class="fa fa-play mr-2"></i> START SESSION
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
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
});
</script>
<style>
    .border-dashed { border-style: dashed !important; border-width: 2px !important; }
</style>
@endpush
@endsection
