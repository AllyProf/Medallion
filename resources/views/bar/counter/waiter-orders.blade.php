@extends('layouts.dashboard')

@section('title', 'Waiter Orders')

@section('content')
<style>
/* Aggressive Red Highlight for Cancelled Orders */
#orders-table tbody tr.order-row-cancelled td {
    background-color: #fdf2f2 !important;
    color: #9b1c1c !important;
    border-bottom: 2px solid #fbd5d5 !important;
}
#orders-table tbody tr.order-row-cancelled {
    border-left: 5px solid #f05252 !important;
}
#orders-table tbody tr.order-row-cancelled strong {
    text-decoration: line-through !important;
    color: #e02424 !important;
}
#orders-table tbody tr.order-row-cancelled .badge-danger {
    background-color: #f05252 !important;
    box-shadow: 0 0 5px rgba(240, 82, 82, 0.4);
}
</style>
<div class="app-title">
  <div>
    <h1><i class="fa fa-list-alt"></i> Waiter Orders</h1>
    <p>Manage orders from waiters</p>
  </div>
  <ul class="app-breadcrumb breadcrumb">
    <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item">Counter</li>
    <li class="breadcrumb-item">Waiter Orders</li>
  </ul>
</div>

<div class="row">
  <!-- Status Summary Cards -->
  <div class="col-md-3">
    <div class="widget-small primary coloured-icon">
      <i class="icon fa fa-clock-o fa-3x"></i>
      <div class="info">
        <h4>Pending</h4>
        <p><b>{{ $pendingCount }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small warning coloured-icon">
      <i class="icon fa fa-truck fa-3x"></i>
      <div class="info">
        <h4>Served</h4>
        <p><b>{{ $servedCount }}</b></p>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="widget-small success coloured-icon">
      <i class="icon fa fa-money fa-3x"></i>
      <div class="info">
        <h4>Total Orders</h4>
        <p><b>{{ $orders->total() }}</b></p>
      </div>
    </div>
  </div>
</div>

<!-- Search and Filtering -->
<div class="row">
  <div class="col-md-12">
    <div class="tile p-3 mb-4 shadow-sm">
      <form method="GET" action="{{ route('bar.counter.waiter-orders') }}" class="row align-items-end">
        <div class="col-md-4 mb-2 mb-md-0">
          <label class="font-weight-bold smallest text-uppercase text-muted mb-1"><i class="fa fa-search"></i> Search Order</label>
          <div class="input-group">
            <input type="text" name="search" id="order-search" class="form-control" placeholder="Order or Item..." value="{{ request('search') }}">
          </div>
        </div>
        <div class="col-md-3 mb-2 mb-md-0">
          <label class="font-weight-bold smallest text-uppercase text-muted mb-1"><i class="fa fa-user"></i> By Waiter</label>
          <select name="waiter_id" id="waiter-filter" class="form-control">
            <option value="all">All Waiters</option>
            @foreach($waiters as $w)
              <option value="{{ $w->id }}" {{ request('waiter_id') == $w->id ? 'selected' : '' }}>{{ $w->full_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3 mb-2 mb-md-0">
          <label class="font-weight-bold smallest text-uppercase text-muted mb-1"><i class="fa fa-info-circle"></i> By Status</label>
          <select name="status" id="status-filter" class="form-control">
            <option value="all">All Statuses</option>
            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="served" {{ request('status') == 'served' ? 'selected' : '' }}>Served</option>
            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
          </select>
        </div>
        <div class="col-md-2 d-flex">
          <button type="submit" class="btn btn-primary flex-grow-1 mr-2">
            <i class="fa fa-filter"></i> Filter
          </button>
          <a href="{{ route('bar.counter.waiter-orders') }}" class="btn btn-secondary">
            <i class="fa fa-refresh"></i> Reset
          </a>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="tile">
      <div class="tile-title-w-btn">
        <h3 class="title">All Waiter Orders</h3>
        <div class="btn-group">
           <!-- Filtering handled by the new filter row above -->
        </div>
      </div>
      <div class="tile-body">
        <div class="table-responsive">
          <table class="table table-hover table-bordered" id="orders-table">
            <thead>
              <tr>
                <th>Order #</th>
                <th>Waiter</th>
                <th>Source</th>
                <th>Items</th>
                <th>Total</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="orders-table-body">
               @include('bar.counter.partials._waiter_orders_table_body')
            </tbody>
          </table>
        </div>
      </div></div>
    </div>
  </div>
</div>

<!-- Correct served quantity -->
<div class="modal fade" id="adjust-qty-modal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
      <div class="modal-header text-white" style="background: #940000; border-radius: 12px 12px 0 0;">
        <h5 class="modal-title font-weight-bold"><i class="fa fa-pencil"></i> Correct Served Quantity</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body p-4">
        <div class="bg-light p-3 rounded mb-3 text-center border" id="adjust-bill-box">
          <small class="text-muted d-block text-uppercase font-weight-bold">New bill</small>
          <h2 class="mb-0 font-weight-bold" id="adjust-total-display" style="color: #940000;">TSh 0</h2>
          <small class="text-muted" id="adjust-order-label"></small>
        </div>
        <input type="hidden" id="adjust-order-id" value="">
        <div id="adjust-lines"></div>
      </div>
      <div class="modal-footer border-0 p-4 pt-0">
        <button type="button" class="btn btn-primary btn-lg btn-block font-weight-bold py-3 shadow-sm" id="adjust-qty-save">
          <i class="fa fa-check"></i> SAVE QUANTITY
        </button>
      </div>
    </div>
  </div>
</div>
<template id="adjust-line-template">
  <div class="adjust-line d-flex align-items-center justify-content-between border rounded p-3 mb-2 bg-white">
    <div class="pr-3">
      <div class="font-weight-bold adjust-line-name"></div>
      <small class="text-muted adjust-line-served"></small>
    </div>
    <div style="width: 110px;">
      <input type="number" class="form-control form-control-lg text-center font-weight-bold adjust-qty-input" min="0">
    </div>
  </div>
</template>

<!-- Order Details Modal -->
<div class="modal fade" id="order-details-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Order Details</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body" id="order-details-content">
        <!-- Order details will be loaded here -->
      </div>
    </div>
  </div>
</div>

<!-- Checkout / Payment Modal -->
<div class="modal fade" id="checkoutModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title font-weight-bold"><i class="fa fa-credit-card"></i> Process Payment</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body p-4">
        <div class="bg-light p-3 rounded mb-3 text-center border">
          <small class="text-muted d-block text-uppercase font-weight-bold">Remaining Balance</small>
          <h2 class="mb-0 text-primary font-weight-bold" id="checkout-total-display">TSh 0</h2>
          <input type="hidden" id="checkout-order-id" value="">
          <input type="hidden" id="checkout-original-total" value="0">
        </div>

        <div class="form-group mb-4">
          <label class="font-weight-bold text-success"><i class="fa fa-money"></i> Amount to Pay Now</label>
          <div class="input-group input-group-lg">
            <div class="input-group-prepend">
              <span class="input-group-text bg-success text-white font-weight-bold">TSh</span>
            </div>
            <input type="number" id="payment-amount-input" class="form-control font-weight-bold" placeholder="0.00" step="100">
          </div>
          <small class="text-muted mt-1 d-block">You can enter a partial amount for split payments.</small>
        </div>

        <div class="form-group">
          <label class="font-weight-bold">Select Payment Mode</label>
          <div class="btn-group btn-group-toggle d-flex flex-wrap" data-toggle="buttons">
            <label class="btn btn-outline-success flex-fill active p-3">
              <input type="radio" name="payment_method" value="cash" checked>
              <i class="fa fa-money fa-2x mb-2 d-block"></i> CASH
            </label>
            <label class="btn btn-outline-info flex-fill p-3">
              <input type="radio" name="payment_method" value="mobile_money">
              <i class="fa fa-mobile fa-3x mb-2 d-block"></i> MOBILE MONEY
            </label>
            <label class="btn btn-outline-primary flex-fill p-3">
              <input type="radio" name="payment_method" value="bank">
              <i class="fa fa-university fa-2x mb-2 d-block"></i> BANK
            </label>
            <label class="btn btn-outline-dark flex-fill p-3">
              <input type="radio" name="payment_method" value="card">
              <i class="fa fa-credit-card fa-2x mb-2 d-block"></i> CARD
            </label>
          </div>
        </div>

        {{-- Mobile Money --}}
        <div id="mobile-money-details" style="display: none;" class="mt-3 p-3 bg-light border-info border rounded">
          <div class="form-group">
            <label class="font-weight-bold small">MM Provider</label>
            <select class="form-control" id="mobile-money-provider">
              <option value="Tigo Pesa">Tigo Pesa</option>
              <option value="M-Pesa">M-Pesa</option>
              <option value="Airtel Money">Airtel Money</option>
              <option value="HaloPesa">HaloPesa</option>
              <option value="MIXX BY YAS">MIXX BY YAS</option>
            </select>
          </div>
          <div class="form-group mb-0">
            <label class="font-weight-bold small">Transaction Reference / Receipt #</label>
            <input type="text" id="mobile-money-ref" class="form-control" placeholder="Enter Reference ID">
          </div>
        </div>

        {{-- Bank Transfer --}}
        <div id="bank-details" style="display: none;" class="mt-3 p-3 bg-light border-primary border rounded">
          <div class="form-group">
            <label class="font-weight-bold small">Bank Name</label>
            <select class="form-control" id="bank-provider">
              <option value="CRDB Bank">CRDB Bank</option>
              <option value="NMB Bank">NMB Bank</option>
              <option value="NBC Bank">NBC Bank</option>
              <option value="Stanbic Bank">Stanbic Bank</option>
              <option value="Equity Bank">Equity Bank</option>
              <option value="Absa Bank">Absa Bank</option>
              <option value="DTB Bank">DTB Bank</option>
              <option value="KCB Bank">KCB Bank</option>
              <option value="Exim Bank">Exim Bank</option>
              <option value="Azania Bank">Azania Bank</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="form-group mb-0">
            <label class="font-weight-bold small">Bank Slip / Reference #</label>
            <input type="text" id="bank-ref" class="form-control" placeholder="Enter bank slip or reference number">
          </div>
        </div>

        {{-- Card Payment --}}
        <div id="card-details" style="display: none;" class="mt-3 p-3 bg-light border-dark border rounded">
          <div class="form-group">
            <label class="font-weight-bold small">Card Type</label>
            <select class="form-control" id="card-provider">
              <option value="Visa">Visa</option>
              <option value="Mastercard">Mastercard</option>
              <option value="Amex">American Express</option>
              <option value="UnionPay">UnionPay</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="form-group mb-0">
            <label class="font-weight-bold small">Card Approval Code</label>
            <input type="text" id="card-ref" class="form-control" placeholder="Enter approval / authorization code">
          </div>
        </div>

      </div>
      <div class="modal-footer border-0 p-4 pt-0">
          <button type="button" class="btn btn-success btn-lg btn-block font-weight-bold py-3 shadow-sm" id="btn-place-order-final">
              <i class="fa fa-check-circle"></i> COMPLETE & PROCESS PAYMENT
          </button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Existing Order Actions...
    
    // Background Refresh (Real-time Waiter Order Visibility)
    let isTyping = false;
    let typingTimer;
    let knownOrderIds = new Set();

    function collectOrderIds() {
        const ids = new Set();
        $('#orders-table-body .order-data-row').each(function() {
            ids.add(String($(this).data('order-id')));
        });
        return ids;
    }

    function playNewOrderTone() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.value = 0.05;
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.18);
        } catch (e) {}
    }

    knownOrderIds = collectOrderIds();

    function refreshOrdersTable() {
        // Don't refresh if user is typing or if any modal is open
        if (isTyping || $('.modal.show').length > 0) return;

        const url = new URL(window.location.href);
        const params = new URLSearchParams(url.search);
        
        // Ensure manual filters are preserved in polling
        if ($('#order-search').val()) params.set('search', $('#order-search').val());
        if ($('#waiter-filter').val() !== 'all') params.set('waiter_id', $('#waiter-filter').val());
        if ($('#status-filter').val() !== 'all') params.set('status', $('#status-filter').val());

        $.ajax({
            url: url.pathname + '?' + params.toString(),
            method: 'GET',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            success: function(html) {
                if (html && html.trim().length > 0) {
                    const previous = knownOrderIds;
                    $('#orders-table-body').html(html);
                    const fresh = [];
                    $('#orders-table-body .order-data-row').each(function() {
                        const id = String($(this).data('order-id'));
                        if (!previous.has(id) && $(this).data('status') === 'pending') {
                            fresh.push(this);
                            $(this).css('background', '#fff3cd');
                        }
                    });
                    knownOrderIds = collectOrderIds();
                    if (fresh.length) {
                        playNewOrderTone();
                        if (typeof showToast === 'function') {
                            showToast('info', fresh.length === 1 ? 'A waiter just placed an order.' : fresh.length + ' new waiter orders just came in.', 'New order');
                        }
                    }
                    // Re-apply client-side filter if search/dropdowns have values
                    filterOrders();
                }
            },
            error: function() {
                console.error("Orders background refresh failed.");
            }
        });
    }

    // Check often so a new kiosk ticket shows without a manual reload
    setInterval(refreshOrdersTable, 8000);

    // Track typing to pause refresh
    $('#order-search').on('keydown', function() {
        isTyping = true;
        clearTimeout(typingTimer);
    });
    $('#order-search').on('keyup', function() {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => { isTyping = false; }, 2000);
    });

    // Real-time Client-side Filtering
    function filterOrders() {
        const searchText = $('#order-search').val().toLowerCase();
        const waiterId = $('#waiter-filter').val();
        const status = $('#status-filter').val();

        $('.order-data-row').each(function() {
            const row = $(this);
            const rowText = row.text().toLowerCase();
            const rowWaiterId = row.data('waiter-id').toString();
            const rowStatus = row.data('status');

            const matchesSearch = rowText.indexOf(searchText) !== -1;
            const matchesWaiter = (waiterId === 'all' || rowWaiterId === waiterId);
            const matchesStatus = (status === 'all' || rowStatus === status);

            if (matchesSearch && matchesWaiter && matchesStatus) {
                row.show();
            } else {
                row.hide();
            }
        });

        // Toggle visibility of shift headers based on whether they have visible children
        $('.shift-header').each(function() {
            const header = $(this);
            const group = header.data('shift-group');
            let hasVisibleOrders = false;
            
            // Find all following rows until the next header
            let next = header.next();
            while (next.length && !next.hasClass('shift-header')) {
                if (next.hasClass('order-data-row') && next.is(':visible')) {
                    hasVisibleOrders = true;
                    break;
                }
                next = next.next();
            }
            
            if (hasVisibleOrders) {
                header.show();
            } else {
                header.hide();
            }
        });
    }

    // Attach events for real-time filtering
    $('#order-search').on('keyup', function() {
        filterOrders();
    });

    $('#waiter-filter, #status-filter').on('change', function() {
        filterOrders();
    });

    // View Details
    $(document).on('click', '.view-order-btn', function() {
        const orderId = $(this).data('order-id');
        $('#order-details-content').html('<div class="text-center p-5"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');
        $('#order-details-modal').modal('show');

        $.ajax({
            url: '{{ route("bar.orders.details", ":id") }}'.replace(':id', orderId),
            method: 'GET',
            success: function(response) {
                const order = response.order;
                let itemsHtml = `
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-right">Price</th></tr></thead>
                            <tbody>`;
                
                order.items.forEach(item => {
                    // Logic for portion labels
                    let label = '';
                    if (item.sell_type === 'tot') {
                        const cat = (item.category || '').toLowerCase();
                        let pName = 'Tot';
                        if (cat.includes('wine')) pName = 'Glass';
                        else if (cat.includes('spirit') || cat.includes('whiskey') || cat.includes('vodka') || cat.includes('gin')) pName = 'Shot';
                        
                        // Simple pluralization
                        if (item.quantity > 1) {
                            label = (pName === 'Glass' ? 'Glasses' : pName + 's') + ' of ';
                        } else {
                            label = pName + ' of ';
                        }
                    }

                    // Avoid redundant variant display
                    const variantText = (item.variant && item.product_name.indexOf(item.variant.split(' - ')[0]) === -1) 
                        ? `<small class="text-muted">(${item.variant})</small>` 
                        : '';
                        
                    itemsHtml += `<tr>
                        <td>${label}<strong>${item.product_name}</strong> ${variantText}</td>
                        <td class="text-center font-weight-bold">${item.quantity}</td>
                        <td class="text-right font-weight-bold">TSh ${parseInt(item.total_price).toLocaleString()}</td>
                    </tr>`;
                });

                itemsHtml += `</tbody></table></div>`;

                // Payment Info
                let paymentHtml = '';
                if (order.payments && order.payments.length > 0) {
                    paymentHtml = '<div class="mt-2 pt-2 border-top"><p class="mb-1 font-weight-bold small">Payment History:</p>';
                    order.payments.forEach(p => {
                        let method = p.method ? p.method.replace('_', ' ').toUpperCase() : 'PAID';
                        let provider = p.provider ? ` (${p.provider})` : '';
                        let ref = p.reference ? ` [Ref: ${p.reference}]` : '';
                        paymentHtml += `<p class="mb-0 text-success small" style="line-height: 1.2;">
                            <i class="fa fa-check-circle"></i> ${method}${provider}${ref}: 
                            <strong>TSh ${parseInt(p.amount).toLocaleString()}</strong>
                        </p>`;
                    });
                    paymentHtml += '</div>';
                } else if (order.payment_status === 'paid' || order.payment_method) {
                    let methodLabel = order.payment_method ? order.payment_method.replace('_', ' ').toUpperCase() : 'PAID';
                    let provider = order.mobile_money_number ? ` - ${order.mobile_money_number}` : '';
                    let reference = order.transaction_reference ? ` (Ref: ${order.transaction_reference})` : '';
                    paymentHtml = `<p class="mb-1 text-success small"><strong>Payment:</strong> ${methodLabel}${provider}${reference}</p>`;
                }

                const counterTotal = order.items.reduce((sum, item) => sum + (parseFloat(item.total_price) || 0), 0);
                const content = `
                    <div class="row mb-3">
                        <div class="col-6">
                            <h4 class="text-primary">#${order.order_number}</h4>
                            <p class="mb-1"><strong>Waiter:</strong> ${order.waiter_name || 'N/A'}</p>
                            <p class="mb-1"><strong>Table:</strong> ${order.table ? 'Table ' + order.table.table_number : 'Walk-in'}</p>
                            ${paymentHtml}
                        </div>
                        <div class="col-6 text-right">
                             <span class="badge badge-info">${order.status.toUpperCase()}</span>
                             <span class="badge badge-success">${order.payment_status.toUpperCase()}</span>
                             <p class="mt-2 mb-0 small text-muted">${order.created_at}</p>
                        </div>
                    </div>
                    ${itemsHtml}
                    <div class="mt-3 p-3 bg-light rounded text-right">
                        <h6>Counter Total (Drinks)</h6>
                        <h3 class="text-primary mb-0">TSh ${counterTotal.toLocaleString()}</h3>
                    </div>
                `;
                $('#order-details-content').html(content);
            }
        });
    });

    function refreshAdjustBill() {
        let total = 0;
        $('#adjust-lines .adjust-qty-input').each(function() {
            const qty = parseInt($(this).val(), 10);
            const price = parseFloat($(this).data('price')) || 0;
            if (!isNaN(qty) && qty > 0) {
                total += qty * price;
            }
        });
        $('#adjust-total-display').text('TSh ' + Math.round(total).toLocaleString());
    }

    $(document).on('input', '.adjust-qty-input', refreshAdjustBill);

    $(document).on('click', '.adjust-qty-btn', function() {
        const orderId = $(this).data('order-id');
        $('#adjust-order-id').val(orderId);
        $('#adjust-order-label').text('');
        $('#adjust-total-display').text('TSh 0');
        $('#adjust-bill-box').show();
        $('#adjust-lines').html('<div class="text-center p-4"><i class="fa fa-spinner fa-spin fa-2x" style="color:#940000;"></i></div>');
        $('#adjust-qty-save').prop('disabled', true);
        $('#adjust-qty-modal').modal('show');

        $.ajax({
            url: '{{ route("bar.counter.served-adjust-lines", ":id") }}'.replace(':id', orderId),
            method: 'GET',
            success: function(res) {
                const template = document.getElementById('adjust-line-template');
                const holder = document.getElementById('adjust-lines');
                holder.innerHTML = '';
                if (!res.lines || !res.lines.length) {
                    $('#adjust-bill-box').hide();
                    holder.innerHTML = '<p class="text-muted mb-0">This ticket has no lines to change.</p>';
                    return;
                }
                $('#adjust-order-label').text('#' + res.order_number);
                res.lines.forEach(function(line) {
                    const node = template.content.cloneNode(true);
                    node.querySelector('.adjust-line-name').textContent = line.name;
                    node.querySelector('.adjust-line-served').textContent = 'Served ' + line.quantity;
                    const input = node.querySelector('.adjust-qty-input');
                    input.value = line.quantity;
                    input.dataset.type = line.type;
                    input.dataset.id = line.id;
                    input.dataset.original = line.quantity;
                    input.dataset.price = line.unit_price;
                    holder.appendChild(node);
                });
                refreshAdjustBill();
                $('#adjust-qty-save').prop('disabled', false);
            },
            error: function(xhr) {
                $('#adjust-bill-box').hide();
                $('#adjust-lines').html('<p class="text-danger mb-0">' + ((xhr.responseJSON && xhr.responseJSON.error) || 'Could not load this ticket.') + '</p>');
            }
        });
    });

    $('#adjust-qty-save').on('click', function() {
        const orderId = $('#adjust-order-id').val();
        const lines = [];
        let changed = false;
        $('#adjust-lines .adjust-qty-input').each(function() {
            const input = $(this);
            const quantity = parseInt(input.val(), 10);
            const original = parseInt(input.data('original'), 10);
            if (isNaN(quantity) || quantity < 0) {
                changed = 'invalid';
                return false;
            }
            if (quantity !== original) {
                changed = true;
            }
            lines.push({ type: input.data('type'), id: input.data('id'), quantity: quantity });
        });
        if (changed === 'invalid') {
            Swal.fire('Check the quantity', 'Enter a quantity of 0 or more.', 'warning');
            return;
        }
        if (changed !== true) {
            Swal.fire('No change', 'Change at least one quantity before saving.', 'info');
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true);
        $.ajax({
            url: '{{ route("bar.counter.adjust-served-quantity", ":id") }}'.replace(':id', orderId),
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', lines: lines },
            success: function(res) {
                $('#adjust-qty-modal').modal('hide');
                if (typeof showToast === 'function') {
                    showToast('success', res.message || 'Quantity updated.', 'Updated');
                }
                setTimeout(function() { location.reload(); }, 800);
            },
            error: function(xhr) {
                btn.prop('disabled', false);
                Swal.fire('Error', (xhr.responseJSON && xhr.responseJSON.error) || 'Failed to update the quantity', 'error');
            }
        });
    });

    // Update Status
    $(document).on('click', '.update-status-btn', function() {
        const orderId = $(this).data('order-id');
        const status = $(this).data('status');
        const action = status === 'served' ? 'Mark Served' : 'Cancel';
        
        if (status === 'cancelled') {
            Swal.fire({
                title: 'Cancel Order?',
                text: 'Please provide a reason for cancelling this order:',
                input: 'textarea',
                inputPlaceholder: 'Type your reason here...',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, cancel it!',
                confirmButtonColor: '#d33',
                inputValidator: (value) => {
                    if (!value) {
                        return 'You need to write a reason!'
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '{{ route("bar.counter.update-order-status", ":id") }}'.replace(':id', orderId),
                        method: 'POST',
                        data: { 
                            _token: '{{ csrf_token() }}', 
                            status: 'cancelled',
                            reason: result.value
                        },
                        success: function(res) {
                            if (typeof showToast === 'function') {
                                showToast('success', 'Order cancelled successfully.', 'Cancelled!');
                            }
                            setTimeout(() => location.reload(), 1200);
                        },
                        error: function(xhr) {
                            Swal.fire('Error', xhr.responseJSON.error || 'Failed to cancel order', 'error');
                        }
                    });
                }
            });
        } else {
            Swal.fire({
                title: action + ' Order?',
                text: `Are you sure you want to mark this order as ${status}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, proceed'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '{{ route("bar.counter.update-order-status", ":id") }}'.replace(':id', orderId),
                        method: 'POST',
                        data: { _token: '{{ csrf_token() }}', status: status },
                        success: function(res) {
                            if (typeof showToast === 'function') {
                                showToast('success', 'Order status updated successfully.', 'Updated!');
                            }
                            setTimeout(() => location.reload(), 1200);
                        },
                        error: function(xhr) {
                            Swal.fire('Error', xhr.responseJSON.error || 'Failed to update order', 'error');
                        }
                    });
                }
            });
        }
    });

    // Pay Order
    $(document).on('click', '.pay-order-btn', function() {
        const orderId = $(this).data('order-id');
        const total = $(this).data('total');
        $('#checkout-order-id').val(orderId);
        $('#checkout-original-total').val(total);
        $('#checkout-total-display').text('TSh ' + parseInt(total).toLocaleString());
        $('#payment-amount-input').val(total); // Default to full amount
        $('#checkoutModal').modal('show');
    });

    $('#btn-place-order-final').on('click', function() {
        const btn = $(this);
        const orderId = $('#checkout-order-id').val();
        const method = $('input[name="payment_method"]:checked').val();
        const amount = $('#payment-amount-input').val();
        
        if (!amount || amount <= 0) {
            Swal.fire('Error', 'Please enter a valid payment amount.', 'warning');
            return;
        }

        // Validation for Reference #s
        if (method !== 'cash') {
            const ref = (method === 'mobile_money') ? $('#mobile-money-ref').val() : 
                        (method === 'bank') ? $('#bank-ref').val() : $('#card-ref').val();
            if (!ref) {
                Swal.fire('Required', 'Please enter a reference number.', 'warning');
                return;
            }
        }

        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

        $.ajax({
            url: '{{ url("bar/counter/record-payment") }}/' + orderId,
            method: 'POST',
            data: {
                payment_method: method,
                amount: amount,
                transaction_reference: (method === 'mobile_money') ? $('#mobile-money-ref').val() : 
                                    (method === 'bank') ? $('#bank-ref').val() : 
                                    (method === 'card') ? $('#card-ref').val() : null,
                mobile_money_number: (method === 'mobile_money') ? $('#mobile-money-provider').val() : 
                                    (method === 'bank') ? $('#bank-provider').val() : 
                                    (method === 'card') ? $('#card-provider').val() : null,
                _token: '{{ csrf_token() }}'
            },
            success: function() {
                showToast('success', 'Payment noted and order completed.', 'Paid!');
                setTimeout(() => location.reload(), 1200);
            },
            error: function(xhr) {
                btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> COMPLETE & PROCESS PAYMENT');
                Swal.fire('Error', xhr.responseJSON.error || 'Payment failed', 'error');
            }
        });
    });

    // Modal behavior for payment methods
    $('input[name="payment_method"]').on('change', function() {
        const val = $(this).val();
        $('#mobile-money-details, #bank-details, #card-details').slideUp();
        if (val === 'mobile_money') $('#mobile-money-details').slideDown();
        else if (val === 'bank') $('#bank-details').slideDown();
        else if (val === 'card') $('#card-details').slideDown();
    });
});
</script>
@endpush


