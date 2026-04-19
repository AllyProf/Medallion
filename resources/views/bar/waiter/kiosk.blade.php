@extends('layouts.dashboard')

@section('title', 'Waiter Kiosk POS')

@section('content')
<style>
/* Reset and Fullscreen */
.app-header, .app-sidebar { display: none !important; }
.app-content { margin: 0 !important; padding: 0 !important; overflow: hidden; height: 100vh; display: flex; flex-direction: column; }
/* Colors */
:root {
  --bg-main: #f5f6fa;
  --bg-darker: #e9ecef;
  --bg-surface: #ffffff;
  --bg-card: #ffffff;
  --bg-input: #f4f6f9;
  --text-main: #333333;
  --text-muted: #6c757d;
  --border-color: #dee2e6;
  --border-dark: #ced4da;
  
  --accent-green: #28a745;
  --accent-yellow: #ffb822;
  --accent-red: #dc3545;
  --accent-cyan: #17a2b8;
}

[data-theme="dark"] {
  --bg-main: #1e1e1e;
  --bg-darker: #121212;
  --bg-surface: #242424;
  --bg-card: #2a2a2a;
  --bg-input: #111111;
  --text-main: #ffffff;
  --text-muted: #aaaaaa;
  --border-color: #333333;
  --border-dark: #000000;
}

body, html { background-color: var(--bg-main) !important; color: var(--text-main) !important; font-family: "Century Gothic", sans-serif; height: 100vh; overflow: hidden; }

/* Layout */
.pos-wrapper { display: flex; height: 100vh; width: 100vw; flex-direction: column; background-color: var(--bg-main); }

/* Top Navigation */
.pos-topbar { height: 50px; background-color: var(--bg-darker); display: flex; padding: 0 15px; align-items: center; gap: 8px; border-bottom: 2px solid var(--border-dark); }
.top-btn { padding: 6px 15px; border-radius: 4px; font-weight: 600; font-size: 0.85rem; border: none; cursor: pointer; color: #fff; text-transform: uppercase; }
.top-btn-home { background-color: var(--accent-green); padding: 6px 15px; }
.top-btn-ongoing { background-color: #007bff; }
.top-btn-kitchen { background-color: var(--accent-green); }
.top-btn-my { background-color: #6f42c1; }
.network-indicator {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-main);
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
}
.network-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    display: inline-block;
    background: #28a745;
    box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
    animation: pulse-online 1.8s infinite;
}
.network-indicator.offline .network-dot { background: #dc3545; }
.network-indicator.offline #network-status-text { color: #dc3545; }
.network-indicator.offline .network-dot {
    animation: none;
    box-shadow: none;
}
.network-status-sub {
    font-size: 0.65rem;
    text-transform: none;
    font-weight: 600;
    color: var(--text-muted);
    margin-left: 2px;
}
@keyframes pulse-online {
    0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.55); }
    70% { box-shadow: 0 0 0 8px rgba(40, 167, 69, 0); }
    100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
}
.brand-logo { margin-left: auto; color: var(--accent-yellow); font-size: 1.2rem; font-weight: bold; text-transform: uppercase; }
.kiosk-branding {
    flex-shrink: 0;
    color: #940000;
    font-size: 0.72rem;
    border-left: 1px solid var(--border-color);
    padding-left: 10px;
    margin-left: 6px;
    white-space: nowrap;
    font-weight: 700;
}

/* Main Content Area */
.pos-body { display: flex; flex: 1; overflow: hidden; min-height: 0; }


/* Left Sidebar (Categories) */
.pos-sidebar { width: 230px; background-color: var(--bg-surface); border-right: 2px solid var(--border-dark); display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
.cat-header { background-color: var(--accent-green); color: white; padding: 15px; font-weight: bold; text-align: center; font-size: 1.1rem; border-bottom: 1px solid var(--border-color); cursor: pointer; flex-shrink: 0; }
.cat-list { flex: 1; overflow-y: auto; overflow-x: hidden; min-height: 0; }
.cat-list::-webkit-scrollbar { width: 4px; }
.cat-list::-webkit-scrollbar-track { background: var(--bg-darker); }
.cat-list::-webkit-scrollbar-thumb { background: var(--accent-green); border-radius: 4px; }
.cat-list::-webkit-scrollbar-thumb:hover { background: #1e9e4c; }
.cat-item { padding: 14px 20px; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 12px; font-size: 0.9rem; color: var(--text-main); }
.cat-item i { width: 20px; text-align: center; font-size: 1.1rem; }
.cat-item:hover, .cat-item.active { background-color: var(--bg-card); border-left: 4px solid var(--accent-green); padding-left: 16px; color: var(--text-main); font-weight: bold; }
.cat-item:hover i, .cat-item.active i { color: var(--accent-green) !important; }

/* Middle Content (Products) */
.pos-products { flex: 1; display: flex; flex-direction: column; background-color: var(--bg-main); min-height: 0; overflow: hidden; }

.product-search-bar { flex-shrink: 0; padding: 15px; border-bottom: 2px solid var(--border-dark); background-color: var(--bg-surface); }
.product-search-bar input { width: 100%; background-color: var(--bg-input); border: 1px solid var(--border-color); color: var(--text-main); padding: 10px 15px; border-radius: 4px; outline: none; font-size: 0.9rem; }
.product-search-bar input::placeholder { color: var(--text-muted); }
.product-grid { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 15px; display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); grid-auto-rows: max-content; gap: 12px; align-content: start; min-height: 0; }

.prod-card { min-height: 250px; background-color: var(--bg-card); border-radius: 6px; overflow: hidden; cursor: pointer; border: 1px solid var(--border-color); display: flex; flex-direction: column; transition: border 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
.prod-card:hover { border-color: var(--accent-green); }
.prod-img { flex-shrink: 0; height: 110px; background-color: var(--bg-darker); width: 100%; object-fit: cover; border-bottom: 1px solid var(--border-color); }
.prod-info { padding: 10px 8px 12px 8px; text-align: center; display: flex; flex-direction: column; flex: 1; }
.prod-title { font-size: 0.85rem; font-weight: 600; margin-bottom: 4px; color: var(--text-main); line-height: 1.3; }
.prod-price { font-size: 0.9rem; color: var(--accent-green); font-weight: bold; margin-top: auto; padding-top: 8px; }

/* Right Sidebar (Cart) */
.pos-cart { width: 380px; background-color: var(--bg-surface); border-left: 2px solid var(--border-dark); display: flex; flex-direction: column; min-height: 0; overflow: hidden; }

.cart-top-form { padding: 15px; border-bottom: 2px solid var(--border-dark); display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.form-group-dark label { font-size: 0.7rem; color: var(--text-muted); margin-bottom: 4px; display: block; text-transform: capitalize; }
.form-control-dark { width: 100%; height: 35px; background-color: var(--bg-input); border: 1px solid var(--border-color); color: var(--text-main); padding: 5px 10px; border-radius: 4px; outline: none; font-size: 0.85rem; }
.form-control-dark:focus { border-color: var(--accent-green); }

.cart-table-wrapper { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 10px; min-height: 0; }

.cart-table { width: 100%; font-size: 0.8rem; color: var(--text-muted); border-collapse: separate; border-spacing: 0 4px; }
.cart-table th { background-color: var(--bg-input); padding: 10px; text-align: left; font-weight: 600; color: var(--text-muted); border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); }
.cart-table th:first-child { border-left: 1px solid var(--border-color); border-top-left-radius: 4px; border-bottom-left-radius: 4px; }
.cart-table th:last-child { border-right: 1px solid var(--border-color); border-top-right-radius: 4px; border-bottom-right-radius: 4px; }
.cart-table td { padding: 8px 10px; background-color: var(--bg-main); vertical-align: middle; border-top: 1px solid var(--bg-card); border-bottom: 1px solid var(--bg-card); }
.cart-table td:first-child { border-left: 1px solid var(--bg-card); }
.cart-table td:last-child { border-right: 1px solid var(--bg-card); }
.cart-table .item-name { font-weight: bold; color: var(--text-main); margin-bottom: 2px; }

/* Qty Controls replicating the dark UI pill */
.qty-controls { display: inline-flex; align-items: center; background: var(--bg-darker); border-radius: 20px; padding: 2px; border: 1px solid var(--border-color); }
.qty-btn { background: var(--accent-green); color: white; border: none; width: 22px; height: 22px; border-radius: 50%; display: flex; justify-content: center; align-items: center; cursor: pointer; font-weight: bold; font-size: 16px; padding: 0; line-height: 1; }
.qty-btn.minus { background: transparent; color: var(--text-main); }
.qty-val { width: 20px; text-align: center; font-size: 0.8rem; font-weight: bold; color: var(--text-main); }

.btn-trash-row { background: var(--accent-red); color: white; border: none; width: 26px; height: 26px; border-radius: 4px; display: flex; justify-content: center; align-items: center; cursor: pointer; }

.cart-bottom { padding: 15px; border-top: 2px solid var(--border-dark); background-color: var(--bg-surface); }
.calc-row { display: flex; justify-content: space-between; margin-bottom: 10px; gap: 10px; }
.calc-box { flex: 1; background-color: var(--bg-input); border: 1px solid var(--border-color); padding: 8px 15px; border-radius: 4px; display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-muted); }

.grand-total-box { background-color: var(--accent-green); color: white; padding: 15px; text-align: center; font-size: 1.25rem; font-weight: bold; border-radius: 4px; margin-bottom: 15px; }

.action-buttons { display: flex; gap: 10px; height: 45px; }
.btn-action { border: none; border-radius: 4px; font-weight: bold; cursor: pointer; color: white; display: flex; align-items: center; justify-content: center; text-transform: uppercase; font-size: 0.9rem; }
.btn-refresh { flex: 0 0 50px; background-color: #1f618d; font-size: 1.2rem; }
.btn-trash { flex: 0 0 50px; background-color: var(--accent-red); font-size: 1.2rem; }
.btn-quick { flex: 1; background-color: var(--accent-green); }
.btn-place { flex: 1; background-color: var(--accent-yellow); color: #000; }

    .low-stock-badge {
        position: absolute;
        top: 5px;
        right: 5px;
        background: #dc3545;
        color: white;
        font-size: 10px;
        padding: 2px 5px;
        border-radius: 4px;
        z-index: 5;
        font-weight: bold;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .kitchen-badge.ready {
        background-color: #28a745 !important;
        color: white !important;
        animation: pulse-green 2s infinite;
    }

    @keyframes pulse-green {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
        70% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
    }

    .pulse-glow {
        animation: pulse-yellow 0.8s infinite;
        border: 2px solid #fff !important;
    }

    @keyframes pulse-yellow {
        0% { box-shadow: 0 0 0 0 rgba(255, 184, 34, 0.7); }
        70% { box-shadow: 0 0 0 15px rgba(255, 184, 34, 0); }
        100% { box-shadow: 0 0 0 0 rgba(255, 184, 34, 0); }
    }

    .history-stats {
        display: flex;
        justify-content: space-around;
        background: var(--bg-surface);
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid var(--border-color);
    }
    
    .history-stat-box { text-align: center; }
    .history-stat-val { font-size: 1.2rem; font-weight: bold; color: var(--accent-green); }
    .history-stat-label { font-size: 0.8rem; color: var(--text-muted); }
    
    /* ── Kiosk Extra Chips Grid ── */
    #m-extras-list {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 10px !important;
    }

    .extra-chip {
        background: var(--bg-input);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 12px 10px;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        justify-content: center;
        transition: all 0.2s;
        min-height: 70px;
        position: relative;
        overflow: hidden;
    }

    .extra-chip.active {
        background: rgba(40, 167, 69, 0.15);
        border-color: var(--accent-green);
        box-shadow: 0 0 10px rgba(40, 167, 69, 0.2);
    }

    .extra-chip.active::after {
        content: '\f058';
        font-family: FontAwesome;
        position: absolute;
        top: 4px;
        right: 4px;
        color: var(--accent-green);
        font-size: 0.9rem;
    }

    .extra-chip-name {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-main);
        word-break: break-word;
        line-height: 1.2;
    }

    .extra-chip-price {
        font-size: 0.75rem;
        margin-top: 5px;
        font-weight: bold;
    }

    .price-free { color: var(--accent-yellow); text-transform: uppercase; }
    .price-paid { color: var(--accent-green); }

::-webkit-scrollbar { width: 8px; }
::-webkit-scrollbar-track { background: var(--bg-main); }
::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

/* ========================================
   LARGE TOUCH SCREEN SCALING (HP POS)
   Screens 1280px+ get bigger everything
   ======================================== */
@media (min-width: 1280px) {
    /* Topbar */
    .pos-topbar { height: 68px; padding: 0 24px; gap: 14px; }
    .top-btn { padding: 12px 26px; font-size: 1.1rem; border-radius: 8px; }
    .brand-logo { font-size: 1.7rem; }

    /* Sidebar */
    .pos-sidebar { width: 300px; }
    .cat-header { padding: 22px 24px; font-size: 1.4rem; }
    .cat-item { padding: 22px 28px; font-size: 1.15rem; gap: 16px; }
    .cat-item i { font-size: 1.4rem; width: 26px; }

    /* Product grid */
    .product-search-bar { padding: 22px 24px; }
    .product-search-bar input { font-size: 1.25rem; padding: 14px 22px; height: 54px; }
    .product-grid { padding: 22px; gap: 18px; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); grid-auto-rows: max-content; }
    .prod-card { border-radius: 10px; height: 100%; min-height: 280px; }
    .prod-img { height: 160px; }
    .prod-info { padding: 14px 12px; }
    .prod-title { font-size: 1.1rem; margin-bottom: 8px; }
    .prod-price { font-size: 1.2rem; margin-top: 8px; }

    /* Cart */
    .pos-cart { width: 500px; }
    .cart-top-form { padding: 22px; gap: 14px; }
    .form-group-dark label { font-size: 0.95rem; margin-bottom: 8px; }
    .form-control-dark { height: 48px; font-size: 1.1rem; padding: 8px 16px; }
    .cart-table-wrapper { padding: 18px; }
    .cart-table { font-size: 1.05rem; }
    .cart-table th { padding: 14px 12px; font-size: 1rem; }
    .cart-table td { padding: 12px 12px; }
    .cart-table .item-name { font-size: 1.1rem; }

    /* Qty controls */
    .qty-btn { width: 36px; height: 36px; font-size: 20px; }
    .qty-val { width: 32px; font-size: 1.15rem; }
    .btn-trash-row { width: 40px; height: 40px; font-size: 1.1rem; }

    /* Cart bottom */
    .grand-total-box { font-size: 1.7rem; padding: 22px; }
    .action-buttons { height: 64px; gap: 14px; }
    .btn-action { font-size: 1.15rem; border-radius: 8px; }
    .btn-refresh, .btn-trash { flex: 0 0 70px; font-size: 1.6rem; }

    /* Empty cart message */
    #empty-cart-msg { padding: 40px; font-size: 1.1rem; }

    /* ── SweetAlert2 Modals Scaling ── */
    body.swal2-shown { padding-right: 0px !important; }
    .swal2-popup {
        width: 680px !important;
        padding: 3rem !important;
        font-size: 1.25rem !important;
        border-radius: 16px !important;
    }
    .swal2-title { font-size: 2.2rem !important; margin-bottom: 1.2rem !important; }
    .swal2-html-container { font-size: 1.2rem !important; line-height: 1.7 !important; }
    .swal2-input, .swal2-select, .swal2-textarea {
        font-size: 1.2rem !important;
        height: 56px !important;
        padding: 12px 20px !important;
    }
    .swal2-textarea { height: 160px !important; }
    .swal2-confirm, .swal2-cancel, .swal2-deny {
        font-size: 1.15rem !important;
        padding: 14px 32px !important;
        min-width: 150px !important;
    }
    .swal2-icon { width: 6rem !important; height: 6rem !important; margin-bottom: 2rem !important; }
    .swal2-icon .swal2-icon-content { font-size: 3.5rem !important; }

    /* ── Sleek Toast Scaling ── */
    .swal2-popup.swal2-toast {
        width: 480px !important;
        padding: 28px !important;
        font-size: 1.2rem !important;
        border-radius: 12px !important;
    }
    .swal2-toast .swal2-title { font-size: 1.2rem !important; margin: 0 0.8rem !important; }
    .swal2-toast .swal2-icon { width: 2.5rem !important; height: 2.5rem !important; margin: 0 !important; }

    /* ── Standard / Bootstrap Modals ── */
    .modal-dialog { max-width: 780px !important; margin-top: 50px !important; }
    .modal-lg { max-width: 1000px !important; }
    .modal-content { border-radius: 14px !important; }
    .modal-header { padding: 1.5rem 2rem !important; }
    .modal-title { font-size: 1.5rem !important; }
    .modal-body { padding: 2rem !important; font-size: 1.15rem !important; }
    .modal-footer { padding: 1.25rem 2rem !important; }
    
    .form-control-lg { height: 54px !important; font-size: 1.2rem !important; }
    .item-name { font-size: 1.15rem !important; }
    .modal-body input {
        font-size: 1rem !important;
        height: 46px !important;
        padding: 8px 14px !important;
        border-radius: 6px !important;
    }
    .modal-body label { font-size: 0.9rem !important; font-weight: 600; }
    .modal-body table { font-size: 0.95rem !important; }
    .modal-body table th, .modal-body table td { padding: 10px 12px !important; }
}

@media (min-width: 1600px) {
    .pos-sidebar { width: 300px; }
    .pos-cart { width: 500px; }
    .product-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
    .prod-img { height: 160px; }
    .prod-title { font-size: 1.1rem; }
    .cart-table { font-size: 1rem; }
    .grand-total-box { font-size: 1.7rem; }
    .action-buttons { height: 62px; }

    /* ── SweetAlert2 ── */
    .swal2-popup { width: 680px !important; font-size: 1.2rem !important; }
    .swal2-title { font-size: 1.8rem !important; }
    .swal2-input, .swal2-select { height: 52px !important; font-size: 1.1rem !important; }
    .swal2-confirm, .swal2-cancel { font-size: 1.1rem !important; padding: 14px 32px !important; }

    /* ── Bootstrap / Custom Modals ── */
    .modal-dialog { max-width: 720px !important; }
    .modal-lg { max-width: 900px !important; }
    .modal-title { font-size: 1.4rem !important; }
    .modal-body { font-size: 1.1rem !important; }
    .modal-body .form-control,
    .modal-body select,
    .modal-body input { font-size: 1.05rem !important; height: 50px !important; }
    .modal-footer .btn, .modal-body .btn {
        font-size: 1.05rem !important;
        padding: 12px 26px !important;
        min-height: 48px !important;
    }
}
</style>

<!-- Toast CSS (SweetAlert handles toasts automatically, but we can style if needed. SweetAlert2 is loaded in dashboard layout.) -->

<div class="pos-wrapper">
    <input type="hidden" id="kiosk-owner-id" value="{{ $ownerId ?? '' }}">
    <!-- Top Bar -->
    <div class="pos-topbar">
        <button class="top-btn top-btn-home" onclick="window.location.reload()"><i class="fa fa-home"></i></button>
        <button class="top-btn top-btn-ongoing" onclick="promptWaiterAuth('ongoing')">Ongoing Order</button>
        <button class="top-btn top-btn-kitchen" onclick="promptWaiterAuth('kitchen_status')">Kitchen Status <span class="badge badge-light ml-1 kitchen-badge @if(($kitchenReadyCount ?? 0) > 0) ready @endif" style="color: black;">{{ $kitchenReadyCount ?? 0 }}</span></button>
        <button class="top-btn top-btn-my" onclick="promptWaiterAuth('my_order')">My Order</button>
        
        <button class="top-btn ml-3" style="background:var(--bg-card); color:var(--text-main); border:1px solid var(--border-color);" onclick="toggleTheme()"><i class="fa fa-moon-o" id="theme-icon"></i> Mode</button>
        <button class="top-btn" style="background:var(--bg-card); color:var(--text-main); border:1px solid var(--border-color);" onclick="toggleLang()">EN | SW</button>

        <button class="top-btn ml-auto" style="background:#dc3545; color:white; border:none; display:flex; align-items:center; gap:8px;" onclick="$('#attendanceModal').modal('show')">
            <i class="fa fa-clock-o"></i> SIGN ATTENDANCE
        </button>

        <div class="brand-logo">
            {{ \App\Models\User::first()->business_name ?? 'RESTAURANT POS' }}
        </div>

        <div class="kiosk-branding">
            Powered By EmCa Tech LTD - www.emca.tech
        </div>
    </div>

    <div class="pos-body">
        <!-- Left Sidebar (Categories) -->
        <div class="pos-sidebar">
            <div class="cat-header active category-pill" data-category="all">
                All Items
            </div>
            <div class="cat-list">
                @php 
                    $drinkCats = collect($variants)->flatMap(function($v) {
                        // Preserve '&' as per user preference for "Soda & Water"
                        return preg_split('/[,|\/]+/', $v['category']);
                    })->map(fn($c) => trim($c))->unique()->filter()->values(); 
                    $foodCats = collect($foodItems)->map(function($f) { return trim($f->category); })->unique()->filter()->values();
                    
                    $icons = ['fa-glass', 'fa-coffee', 'fa-lemon-o', 'fa-beer', 'fa-flask', 'fa-tint'];
                    $foodIcons = ['fa-cutlery', 'fa-fire', 'fa-birthday-cake', 'fa-leaf', 'fa-heart', 'fa-apple'];
                    $colors = ['#ffb822', '#17a2b8', '#6f42c1', '#28a745', '#fd7e14', '#007bff'];
                    $foodColors = ['#dc3545', '#e74c3c', '#d35400', '#c0392b', '#ff4757', '#e84118'];

                    $catColorMap = [];
                    foreach($drinkCats as $i => $cat) {
                        $catColorMap[$cat] = $colors[$i % count($colors)];
                    }
                    foreach($foodCats as $i => $cat) {
                        $catColorMap[$cat] = $foodColors[$i % count($foodColors)];
                    }
                @endphp
                
                @if($drinkCats->count() > 0)
                    <div style="padding: 12px 20px; font-weight: 800; font-size: 0.75rem; letter-spacing: 1.5px; color: var(--accent-green); background: var(--bg-input); border-bottom: 1px solid var(--border-color); text-transform: uppercase;">DRINK ITEMS</div>
                    <div id="drink-cat-list">
                        @foreach($drinkCats as $i => $cat)
                            @php
                                $cIcon = $icons[$i % count($icons)];
                                $lowCat = strtolower($cat);
                                if(str_contains($lowCat, 'beer')) $cIcon = 'fa-beer';
                                if(str_contains($lowCat, 'spirit')) $cIcon = 'fa-glass';
                                if(str_contains($lowCat, 'wine')) $cIcon = 'fa-flask';
                                if(str_contains($lowCat, 'soda') || str_contains($lowCat, 'drink')) $cIcon = 'fa-coffee';
                                if(str_contains($lowCat, 'water')) $cIcon = 'fa-tint';
                                if(str_contains($lowCat, 'energy')) $cIcon = 'fa-bolt';
                                if(str_contains($lowCat, 'gin')) $cIcon = 'fa-flask';
                                if(str_contains($lowCat, 'local')) $cIcon = 'fa-glass';
                            @endphp
                            <div class="cat-item category-pill" data-category="cat-{{ \Illuminate\Support\Str::slug($cat) }}">
                                <i class="fa {{ $cIcon }}" style="color: {{ $colors[$i % count($colors)] }};"></i> 
                                <span style="display:inline-block; vertical-align:middle; color:var(--text-main) !important;">{{ $cat ?: 'Uncategorized' }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($foodCats->count() > 0 || count($foodItems) > 0)
                    <div style="padding: 12px 20px; font-weight: 800; font-size: 0.75rem; letter-spacing: 1.5px; color: #dc3545; background: var(--bg-input); border-bottom: 1px solid var(--border-color); text-transform: uppercase; margin-top: 10px;">FOOD ITEMS</div>
                    @if(count($foodItems) > 0)
                        <div class="cat-item category-pill" data-category="cat-food">
                            <i class="fa fa-fire" style="color: #dc3545;"></i> <span>All Kitchen</span>
                        </div>
                    @endif
                    
                    @if($foodCats->count() > 0)
                        <div id="food-cat-list">
                            @foreach($foodCats as $i => $cat)
                                <div class="cat-item category-pill" data-category="cat-{{ \Illuminate\Support\Str::slug($cat) }}">
                                    <i class="fa {{ $foodIcons[$i % count($foodIcons)] }}" style="color: {{ $foodColors[$i % count($foodColors)] }};"></i> 
                                    <span style="color:var(--text-main) !important;">{{ $cat ?: 'Kitchen' }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </div>

        <!-- Middle Content (Products) -->
        <div class="pos-products">
            <div class="product-search-bar">
                <input type="text" id="product-search" placeholder="Item Name">
            </div>
            
            <div class="product-grid" id="pos-items-grid">
                <!-- Drinks -->
                @foreach($variants as $v)
                @php $vFullName = $v['product_name']; @endphp
                @php 
                    $categories = preg_split('/[,|\/]+/', $v['category']);
                    $catClasses = collect($categories)->map(fn($c) => 'cat-' . \Illuminate\Support\Str::slug(trim($c)))->implode(' ');
                @endphp
                <div class="prod-card pos-item cat-drinks {{ $catClasses }}" 
                     data-id="{{ $v['id'] }}" 
                     data-name="{{ $vFullName }}" 
                     data-variant="{{ $v['variant'] }}"
                     data-portion-label="{{ $v['portion_label'] }}"
                     data-unit-label="{{ $v['unit'] }}"
                     data-total-tots="{{ $v['total_tots'] }}"
                     data-open-tots="{{ $v['open_tots'] ?? 0 }}"
                     data-price="{{ $v['selling_price'] }}"
                     data-price-tot="{{ $v['selling_price_per_tot'] }}"
                     data-can-tot="{{ $v['can_sell_in_tots'] ? 'true' : 'false' }}"
                     data-low-stock="{{ $v['low_stock'] ? 'true' : 'false' }}"
                     data-available="{{ $v['quantity'] }}"
                     data-type="drink"> 
                    
                    @if($v['low_stock'])
                        <span class="low-stock-badge">Low Stock: {{ $v['quantity'] }}</span>
                    @endif
                    @if($v['product_image'])
                        <img src="{{ asset('storage/' . $v['product_image']) }}" class="prod-img">
                    @else
                        <div class="prod-img" style="display:flex; align-items:center; justify-content:center; font-size:2.5rem; color:{{ $catColorMap[$v['category']] ?? '#555' }};"><i class="fa fa-glass"></i></div>
                    @endif
                    <div class="prod-info">
                        <div class="prod-title">{{ $vFullName }}</div>
                        @if($v['variant'])
                            <div style="font-weight:normal; color:#aaa; font-size:0.75rem;">({{ $v['variant'] }})</div>
                        @else
                            <div style="font-weight:normal; color:#aaa; font-size:0.75rem;">&nbsp;</div>
                        @endif
                        <div class="prod-price d-flex flex-column">
                            <span style="font-size: 0.85rem;">TSh {{ number_format($v['selling_price'], 0) }} <small class="text-muted">({{ $v['unit'] === 'btl' ? 'Btl' : 'Full' }})</small></span>
                            @if($v['can_sell_in_tots'] && $v['selling_price_per_tot'] > 0)
                                <small style="font-size: 0.7rem; color: var(--accent-green); font-weight: bold; margin-top: 1px;">
                                    TSh {{ number_format($v['selling_price_per_tot'], 0) }} <span style="color: var(--text-muted); font-weight: normal;">({{ $v['portion_label'] ?: 'Glass' }})</span>
                                </small>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach

                <!-- Food Items -->
                @foreach($foodItems as $f)
                @php $fCatClass = 'cat-' . \Illuminate\Support\Str::slug($f->category ?: 'food'); @endphp
                <div class="prod-card pos-item cat-food {{ $fCatClass }}" 
                     data-id="{{ $f->id }}" 
                     data-name="{{ $f->name }}" 
                     data-variant="{{ $f->variant_name }}"
                     data-price="{{ $f->price }}"
                     data-extras="{{ json_encode($f->extras ?? []) }}"
                     data-food-category="{{ $f->category }}"
                     data-type="food">
                    @if($f->image)
                        <img src="{{ asset('storage/' . $f->image) }}" class="prod-img">
                    @else
                        <div class="prod-img" style="display:flex; align-items:center; justify-content:center; font-size:2.5rem; color:{{ $catColorMap[$f->category] ?? '#dc3545' }};"><i class="fa fa-cutlery"></i></div>
                    @endif
                    <div class="prod-info">
                        <div class="prod-title">{{ $f->name }}</div>
                        <div style="font-weight:normal; color:#aaa; font-size:0.75rem;">@if($f->variant_name) ({{ $f->variant_name }}) @else &nbsp; @endif</div>
                        <div class="prod-price">TSh {{ number_format($f->price, 0) }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Right Sidebar (Cart) -->
        <div class="pos-cart">
            <div class="cart-top-form">
                <div class="form-group-dark" style="grid-column: span 2;">
                    <label>Enter Waiter PIN*</label>
                    <input type="password" class="form-control-dark font-weight-bold" id="form-waiter-pin" placeholder="1234" inputmode="numeric" pattern="\d*" maxlength="4" required autocomplete="new-password" style="font-size: 1.5rem; text-align:center; -webkit-text-security: disc;">
                    <div id="form-waiter-name-display" class="mt-1" style="font-size: 0.95rem; color: var(--accent-green); font-weight: bold; min-height: 1.5rem; text-align: center;"></div>
                    <input type="hidden" id="form-waiter-id">
                </div>
                <div class="form-group-dark" style="grid-column: span 2;">
                    <label>Customer Name (Optional)</label>
                    <input type="text" class="form-control-dark" id="form-customer-name" placeholder="Walk-in">
                </div>
                <div class="form-group-dark">
                    <label>Phone Number (Optional)</label>
                    <input type="text" class="form-control-dark" id="form-customer-phone" placeholder="07...">
                </div>
                <div class="form-group-dark">
                    <label>Table (Optional)</label>
                    <select class="form-control-dark" id="form-order-table">
                        <option value="">No Table (Walk-in)</option>
                        @foreach($tables as $table)
                            <option value="{{ $table->id }}">Table {{ $table->table_number }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="cart-table-wrapper">
                <div class="text-center p-4 text-muted" id="empty-cart-msg">
                    <i class="fa fa-shopping-basket fa-3x mb-2" style="color: #444;"></i>
                    <p style="font-size: 0.85rem;">Ticket is Empty</p>
                </div>
                <table class="cart-table" id="cart-table" style="display: none;">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Variant</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th style="padding-left:0; padding-right:0; text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="cart-tbody">
                        <!-- Items injected here -->
                    </tbody>
                </table>
            </div>

            <div class="cart-bottom">
                
                <div class="grand-total-box" id="cart-total-display">
                    Grand Total : 0
                </div>

                <div class="action-buttons">
                    <button class="btn-action btn-refresh" title="Refresh Products" onclick="window.location.reload();"><i class="fa fa-refresh"></i></button>
                    <button class="btn-action btn-trash" id="btn-clear-cart"><i class="fa fa-trash-o"></i></button>
                    <button class="btn-action btn-place" id="btn-finish-order" disabled>Place Order</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Numpad Styles (Kept identical) -->
<style>
.kiosk-num-btn {
    width: 100%; height: 60px; font-size: 1.5rem; font-weight: bold; border-radius: 8px; background: #f8f9fa; border: 1px solid #ccc; margin-bottom: 8px; color: #333;
}
.kiosk-num-btn:active { background: #e2e6ea; }
</style>

<!-- Add Item Modal overlay -->
<div class="modal fade" id="addItemModal" tabindex="-1" role="dialog" aria-hidden="true" style="background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content border-0" style="background:var(--bg-card); color:var(--text-main); border-radius:8px; border: 1px solid var(--border-color);">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title font-weight-bold" style="color:var(--accent-green);"><i class="fa fa-plus-circle"></i> Add Item</h6>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-3 text-center">
                <input type="hidden" id="m-id">
                <input type="hidden" id="m-type">
                <input type="hidden" id="m-price">
                <input type="hidden" id="m-price-tot">
                <input type="hidden" id="m-portion-label">
                <input type="hidden" id="m-total-tots">
                <input type="hidden" id="m-open-tots">
                <input type="hidden" id="m-unit-label">
                
                <h6 id="m-name" class="font-weight-bold mb-0"></h6>
                <small id="m-variant" style="color:#aaa;" class="d-block mb-1"></small>
                <div id="m-price-display" class="font-weight-bold" style="color:var(--accent-green); font-size:1.1rem;"></div>
                <div id="m-stock-display" style="font-size: 0.8rem; color: #ffb822; margin-top: 5px; font-weight: bold;"></div>
                
                <div id="m-sell-group" class="mb-3 mt-3" style="display: none;">
                    <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                        <label class="btn btn-outline-success btn-sm active sell-type-label" style="font-weight:bold;">
                            <input type="radio" name="m_sell_type" value="unit" checked> <span id="m-unit-text">Bottle</span>
                        </label>
                        <label class="btn btn-outline-success btn-sm sell-type-label" id="m-label-tot" style="display: none; font-weight:bold;">
                            <input type="radio" name="m_sell_type" value="tot"> <span id="m-portion-text">Shot</span>
                        </label>
                    </div>
                </div>

                <div class="form-group text-left mt-3">
                    <label style="font-size:0.75rem; color:#aaa;">Special Instructions (Optional)</label>
                    <input type="text" id="m-note" class="form-control form-control-sm" placeholder="e.g. No ice, Spicy..." style="background:#111; color:white; border:1px solid #444;">
                </div>

                <div id="m-extras-container" class="text-left mt-3" style="display: none;">
                    <label style="font-size:0.75rem; color:#aaa;">Add Extras</label>
                    <div id="m-extras-list">
                        <!-- Extras chips injected here via JS -->
                    </div>
                </div>

                <div class="input-group mt-3" style="border: 1px solid #444; border-radius: 6px; overflow: hidden; background: #111;">
                    <div class="input-group-prepend">
                        <button class="btn" style="background:#333; color:white; border-radius:0; width: 50px; font-weight:bold; font-size:1.2rem;" id="m-minus">-</button>
                    </div>
                    <input type="number" class="form-control text-center font-weight-bold" id="m-quantity" value="1" min="1" style="background:#111; color:white; border:none; font-size: 1.5rem; height: 50px; outline: none; box-shadow: none;">
                    <div class="input-group-append">
                        <button class="btn" style="background:var(--accent-green); color:white; border-radius:0; border:none; width: 50px; font-weight:bold; font-size:1.2rem;" id="m-plus">+</button>
                    </div>
                </div>
                
                <div class="mt-2" style="display:flex; justify-content:center; gap:8px;">
                    <button class="btn btn-sm" style="background:#333; color:white; border:1px solid #555; width:45px;" onclick="updateModalQty(2)">+2</button>
                    <button class="btn btn-sm" style="background:#333; color:white; border:1px solid #555; width:45px;" onclick="updateModalQty(5)">+5</button>
                    <button class="btn btn-sm" style="background:#333; color:white; border:1px solid #555; width:45px;" onclick="updateModalQty(10)">+10</button>
                </div>

                <button type="button" class="btn btn-block font-weight-bold py-2 mt-4" style="background:var(--accent-yellow); color:#000; font-size: 1.1rem;" id="btn-add-confirm">ADD TO TICKET</button>
            </div>
        </div>
    </div>
</div>

<!-- Ongoing Orders Modal (For Waiter to view their active tickets) -->
<div class="modal fade" id="kioskOrdersModal" tabindex="-1" role="dialog" aria-hidden="true" style="background: rgba(0,0,0,0.8);">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden; background:var(--bg-surface);">
            <div class="text-white p-3 text-center" style="background:var(--accent-cyan); display:flex; justify-content: space-between; align-items:center;">
                <h5 class="m-0"><i class="fa fa-list"></i> <span id="kiosk-orders-modal-title">Your Ongoing Orders</span></h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0" style="max-height: 70vh; overflow-y:auto;">
                <div id="kioskOrdersList" class="p-3" style="background:var(--bg-main);">
                    <!-- Dynamic orders injected here -->
                </div>
            </div>
            <div class="p-3 border-top" style="background:var(--bg-card); display:flex; justify-content: flex-end;">
                <button class="btn font-weight-bold" style="background: #333; color: #fff;" data-dismiss="modal">Close Window</button>
            </div>
        </div>
    </div>
</div>

<!-- Action Authentication Modal (For specific buttons like My Orders) -->
<div class="modal fade" id="actionAuthModal" tabindex="-1" role="dialog" aria-hidden="true" style="background: rgba(0,0,0,0.8);">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 380px;">
        <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden; background:#242424;">
            <div class="text-white p-3 text-center" style="background:var(--accent-cyan);">
                <h5 class="m-0"><i class="fa fa-lock"></i> Authorize</h5>
            </div>
            <div class="modal-body p-3" style="background:#1a1a1a;">
                <form id="action-auth-form">
                    <input type="hidden" id="auth-action-type">
                    <div class="mb-3 text-center" style="display:none;">
                        <div id="action-waiter-name-display" class="mb-2"></div>
                        <input type="hidden" id="action-waiter-id">
                    </div>
                    <div class="mb-3">
                        <input type="password" class="form-control text-center shadow-sm font-weight-bold" id="action-pin" 
                               name="pin" placeholder="PIN" inputmode="numeric" pattern="\d*" maxlength="4"
                               autocomplete="new-password"
                               style="font-size: 2rem; height: 60px; background:#111; color:var(--accent-yellow); border:1px solid #333;">
                    </div>
                    <!-- Number Pad -->
                    <div class="row m-0 mx-n1">
                        @for($i=1; $i<=9; $i++)
                            <div class="col-4 px-1 py-1"><button type="button" class="kiosk-num-btn shadow-sm" onclick="actionPressKey('{{$i}}')">{{$i}}</button></div>
                        @endfor
                        <div class="col-4 px-1 py-1"><button type="button" class="kiosk-num-btn shadow-sm" style="color:var(--accent-red);" onclick="actionClearPIN()">C</button></div>
                        <div class="col-4 px-1 py-1"><button type="button" class="kiosk-num-btn shadow-sm" onclick="actionPressKey('0')">0</button></div>
                        <div class="col-4 px-1 py-1"><button type="submit" class="kiosk-num-btn shadow-sm text-white border-0" style="background:var(--accent-green);"><i class="fa fa-sign-in"></i></button></div>
                    </div>
                </form>
                <div id="action-auth-error" class="alert alert-danger mt-3 mb-0 p-2 text-center" style="display: none; font-size: 0.85rem; background:rgba(220,53,69,0.2); border:none; color:#ff8080;"></div>
            </div>
            <div class="p-2 text-center border-top border-dark">
                <button type="button" class="btn btn-link text-muted p-0" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Clock In/Out Modal -->
<div class="modal fade" id="attendanceModal" tabindex="-1" role="dialog" aria-hidden="true" style="background: rgba(0,0,0,0.9);">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 400px;">
        <div class="modal-content shadow-lg border-0" style="border-radius: 16px; overflow: hidden; background:#1a1a1a; border: 1px solid #333;">
            <div class="text-white p-4 text-center" style="background:linear-gradient(45deg, #dc3545, #940000);">
                <h4 class="m-0 font-weight-bold"><i class="fa fa-clock-o"></i> STAFF ATTENDANCE</h4>
                <p class="small mb-0 opacity-75">Sign In or Sign Out</p>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4 text-center">
                    <input type="password" class="form-control text-center font-weight-bold" id="attendance-pin" 
                           placeholder="ENTER PIN" inputmode="numeric" pattern="\d*" maxlength="4"
                           style="font-size: 2.5rem; height: 80px; background:#000; color:#ffb822; border:2px solid #444; border-radius:12px; letter-spacing: 12px; -webkit-text-security: disc;">
                </div>

                <!-- Numpad for Touch Screens -->
                <div class="row no-gutters mb-3">
                    @foreach([1,2,3,4,5,6,7,8,9] as $num)
                    <div class="col-4 p-1">
                        <button class="kiosk-num-btn" onclick="appendAttendancePin('{{ $num }}')">{{ $num }}</button>
                    </div>
                    @endforeach
                    <div class="col-4 p-1">
                        <button class="kiosk-num-btn text-danger" onclick="$('#attendance-pin').val('')"><i class="fa fa-times"></i></button>
                    </div>
                    <div class="col-4 p-1">
                        <button class="kiosk-num-btn" onclick="appendAttendancePin('0')">0</button>
                    </div>
                    <div class="col-4 p-1">
                        <button class="kiosk-num-btn text-warning" onclick="backspaceAttendancePin()"><i class="fa fa-long-arrow-left"></i></button>
                    </div>
                </div>

                <button class="btn btn-block btn-lg font-weight-bold py-3" 
                        style="background: #28a745; color:#fff; border-radius:12px; font-size:1.3rem;"
                        onclick="submitAttendance()">
                    <i class="fa fa-check-circle"></i> SIGN NOW
                </button>
                <button class="btn btn-block btn-link text-muted mt-2" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let cart = [];

    function foodCategoryIsBeverage(cat) {
        if (!cat || typeof cat !== 'string') return false;
        const c = cat.toLowerCase();
        const keywords = ['beverage', 'drink', 'alcohol', 'beer', 'wine', 'spirit', 'liquor', 'vodka', 'whiskey', 'whisky', 'gin', 'rum', 'soda', 'water', 'juice', 'cocktail', 'coffee', 'tea', 'smoothie'];
        return keywords.some(kw => c.includes(kw));
    }
    let editingOrderId = null; // Track if we are adding to an existing order

    // Search
    $('#product-search').on('keyup', function() {
        const val = $(this).val().toLowerCase();
        $('.pos-item').each(function() {
            const name = $(this).data('name').toLowerCase();
            const variant = ($(this).data('variant') || '').toLowerCase();
            if (name.includes(val) || variant.includes(val)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // Categories (Delegated for dynamic items)
    $(document).on('click', '.category-pill', function() {
        $('.category-pill').removeClass('active active-glow');
        $(this).addClass('active active-glow');
        const cat = $(this).data('category');
        if (cat === 'all') {
            $('.pos-item').show();
        } else {
            $('.pos-item').hide();
            $('.pos-item.' + cat).show();
        }
    });

    // Item Selection (Delegated for dynamic items)
    $(document).on('click', '.pos-item', function() {
        const d = $(this).data();
        const canSellTot = (d.canTot == "true" || d.canTot === true);
        
        $('#m-id').val(d.id);
        $('#m-type').val(d.type);
        $('#m-id').data('foodCategory', d.type === 'food' ? String(d.foodCategory || '') : '');
        $('#m-price').val(d.price);
        $('#m-price-tot').val(canSellTot ? d.priceTot : "");
        $('#m-portion-label').val(d.portionLabel || 'Tot');
        $('#m-total-tots').val(d.totalTots || 1);
        $('#m-open-tots').val(d.openTots || 0);
        $('#m-unit-label').val(d.unitLabel || 'btl');
        
        $('#m-name').text(d.name);
        $('#m-variant').text(d.variant || "");
        $('#m-unit-text').text(d.unitLabel === 'btl' ? 'Bottle' : 'Piece');
        $('#m-portion-text').text(d.portionLabel || 'Tot');
        
        $('#m-price-display').text('TSh ' + parseFloat(d.price).toLocaleString(undefined, {maximumFractionDigits: 0}));
        $('#m-quantity').val(1);
        
        const available = d.type === 'drink' ? d.available : 9999;
        $('#m-id').data('available', available);
        if (d.type === 'drink') {
            const unit = d.unitLabel === 'btl' ? 'Bottle' : 'Piece';
            const plural = unit === 'Bottle' ? 's' : 's';
            $('#m-stock-display').text('Stock: ' + available + ' ' + unit + plural).show();
        } else {
            $('#m-stock-display').hide();
        }
        
        if (d.type === 'drink') {
            $('#m-extras-container').hide();
            $('#m-sell-group').show();
            if (canSellTot) {
                $('#m-label-tot').show();
            } else {
                $('#m-label-tot').hide();
                $('input[name="m_sell_type"][value="unit"]').prop('checked', true).change().parent().addClass('active').siblings().removeClass('active');
            }
        } else {
            $('#m-sell-group').hide();
            
            // Handle extras for food
            let extras = d.extras || [];
            if (typeof extras === 'string') {
                try { extras = JSON.parse(extras); } catch (e) { extras = []; }
            }
            
            if (extras.length > 0) {
                let extrasHtml = '';
                extras.forEach((ext, idx) => {
                    const price = parseFloat(ext.price);
                    const isFree = (price === 0);
                    const priceLabel = isFree ? '<span class="price-free">Free</span>' : '<span class="price-paid">+TSh ' + price.toLocaleString() + '</span>';
                    
                    extrasHtml += `
                        <div class="extra-chip m-extra-toggle" data-name="${ext.name}" data-price="${price}">
                            <div class="extra-chip-name">${ext.name}</div>
                            <div class="extra-chip-price">${priceLabel}</div>
                        </div>
                    `;
                });
                $('#m-extras-list').html(extrasHtml);
                $('#m-extras-container').show();
            } else {
                $('#m-extras-container').hide();
                $('#m-extras-list').empty();
            }
        }

        // Recalculate price when extras change (Using Chip Selection)
        $(document).off('click', '.m-extra-toggle').on('click', '.m-extra-toggle', function() {
            $(this).toggleClass('active');
            
            let basePrice = parseFloat($('#m-price').val());
            let extrasTotal = 0;
            $('.m-extra-toggle.active').each(function() {
                extrasTotal += parseFloat($(this).data('price'));
            });
            $('#m-price-display').text('TSh ' + (basePrice + extrasTotal).toLocaleString(undefined, {maximumFractionDigits: 0}));
        });

        $('#addItemModal').modal('show');
    });

    // Quantity Modal Controls
    $('#m-minus').on('click', () => { let v = parseInt($('#m-quantity').val()) || 1; if (v > 1) $('#m-quantity').val(v-1); });
    $('#m-plus').on('click', () => { 
        let v = parseInt($('#m-quantity').val()) || 1; 
        const availableBottles = parseInt($('#m-id').data('available')) || 9999;
        const sellType = $('input[name="m_sell_type"]:checked').val() || 'unit';
        const totalTots = parseInt($('#m-total-tots').val()) || 1;
        const openTots = parseInt($('#m-open-tots').val()) || 0;
        const effectiveAvailable = (sellType === 'tot') ? (availableBottles * totalTots) + openTots : availableBottles;
        if (v < effectiveAvailable) {
            $('#m-quantity').val(v+1); 
        } else {
            KioskToast.fire({ icon: 'warning', title: 'Cannot exceed available stock (' + effectiveAvailable + ')' });
        }
    });

    window.updateModalQty = function(val) {
        let v = parseInt($('#m-quantity').val()) || 1;
        const availableBottles = parseInt($('#m-id').data('available')) || 9999;
        const sellType = $('input[name="m_sell_type"]:checked').val() || 'unit';
        const totalTots = parseInt($('#m-total-tots').val()) || 1;
        const openTots = parseInt($('#m-open-tots').val()) || 0;
        const effectiveAvailable = (sellType === 'tot') ? (availableBottles * totalTots) + openTots : availableBottles;
        if (v + val <= effectiveAvailable) {
            $('#m-quantity').val(v + val);
        } else {
            $('#m-quantity').val(effectiveAvailable);
            KioskToast.fire({ icon: 'warning', title: 'Adjusted to maximum available stock (' + effectiveAvailable + ')' });
        }
    };

    function addToCartFast(id, type, name, variant, price, sellType, qty, notes, available, foodCategory) {
        qty = parseInt(qty) || 1;
        available = parseInt(available) || 9999;
        const existingIdx = cart.findIndex(i => (type === 'food' ? i.food_item_id == id : i.variant_id == id) && i.sell_type === sellType);
        
        if (existingIdx > -1) {
            const newQty = cart[existingIdx].quantity + qty;
            if (newQty <= available) {
                cart[existingIdx].quantity = newQty;
                if(notes) cart[existingIdx].notes = notes;
            } else {
                cart[existingIdx].quantity = available;
                KioskToast.fire({ icon: 'warning', title: 'Adjusted to max available stock for ' + name });
            }
        } else {
            if (qty > available) {
                qty = available;
                KioskToast.fire({ icon: 'warning', title: 'Adjusted to max available stock for ' + name });
            }
            const row = { 
                name, 
                variant, 
                price, 
                quantity: qty, 
                sell_type: sellType, 
                portion_label: $('#m-portion-label').val() || 'Tot',
                notes: notes, 
                available: available, 
                type: type 
            };
            if (type === 'food') {
                row.food_item_id = id;
                row.food_category = foodCategory || '';
            } else {
                row.variant_id = id;
            }
            cart.push(row);
        }
        updateCart();
    }

    $('input[name="m_sell_type"]').on('change', function() {
        $(this).parent().addClass('active').siblings().removeClass('active');
        const sellType = $(this).val();
        const price = sellType === 'tot' ? parseFloat($('#m-price-tot').val()) : parseFloat($('#m-price').val());
        $('#m-price-display').text('TSh ' + price.toLocaleString(undefined, {maximumFractionDigits: 0}));
        
        const availableBottles = parseFloat($('#m-id').data('available')) || 0;
        const totalTots = parseFloat($('#m-total-tots').val()) || 1;
        const portionLabel = $('#m-portion-label').val() || 'Tot';
        const unitLabel = $('#m-unit-label').val() === 'btl' ? 'Bottle' : 'Piece';
        
        if (sellType === 'tot') {
            const openTots = parseInt($('#m-open-tots').val()) || 0;
            const totalPortions = Math.floor(availableBottles * totalTots) + openTots;
            const pLabel = portionLabel === 'Glass' ? 'Glasses' : portionLabel + 's';
            $('#m-stock-display').html('<i class="fa fa-glass"></i> Stock: ' + totalPortions.toLocaleString(undefined, {maximumFractionDigits: 0}) + ' ' + pLabel);
        } else {
            $('#m-stock-display').html('<i class="fa fa-database"></i> Stock: ' + availableBottles.toLocaleString(undefined, {maximumFractionDigits: 0}) + ' ' + unitLabel + 's');
        }
    });

    $('#btn-add-confirm').on('click', function() {
        const id = $('#m-id').val();
        const type = $('#m-type').val();
        const name = $('#m-name').text();
        const variant = $('#m-variant').text();
        const sellType = type === 'drink' ? $('input[name="m_sell_type"]:checked').val() : 'unit';
        
        const basePrice = sellType === 'tot' ? parseFloat($('#m-price-tot').val()) : parseFloat($('#m-price').val());
        let finalPrice = basePrice;
        let selectedExtras = [];
        
        if (type === 'food') {
            $('.m-extra-toggle.active').each(function() {
                finalPrice += parseFloat($(this).data('price'));
                selectedExtras.push($(this).data('name'));
            });
        }
        
        let notes = $('#m-note').val();
        if (selectedExtras.length > 0) {
            let extrasText = 'Extras: ' + selectedExtras.join(', ');
            notes = notes ? notes + ' | ' + extrasText : extrasText;
        }

        const qty = parseInt($('#m-quantity').val()) || 1;
        const availableBottles = parseInt($('#m-id').data('available')) || 9999;
        const totalTots = parseInt($('#m-total-tots').val()) || 1;
        const openTots = parseInt($('#m-open-tots').val()) || 0;
        // For tot/glass sales, effective stock is bottles × tots-per-bottle + any already opened parts
        const effectiveAvailable = (sellType === 'tot') ? (availableBottles * totalTots) + openTots : availableBottles;

        if (qty > effectiveAvailable) {
            KioskToast.fire({ icon: 'error', title: 'Cannot add more than available stock (' + effectiveAvailable + ')' });
            return;
        }

        const foodCategory = type === 'food' ? ($('#m-id').data('foodCategory') || '') : '';
        addToCartFast(id, type, name, variant, finalPrice, sellType, qty, notes, effectiveAvailable, foodCategory);
        $('#addItemModal').modal('hide');
        $('#m-note').val(''); // Clear it
    });

    window.updateQty = function(idx, action) {
        if(action === 'add') {
            const item = cart[idx];
            if (item.quantity < item.available) {
                cart[idx].quantity += 1;
            } else {
                KioskToast.fire({ icon: 'warning', title: 'Maximum available stock reached' });
            }
        } else if(action === 'sub') {
            if(cart[idx].quantity > 1) {
                cart[idx].quantity -= 1;
            } else {
                cart.splice(idx, 1);
            }
        }
        updateCart();
    }

    window.removeItem = function(idx) { cart.splice(idx, 1); updateCart(); }
    
    window.editItemNote = function(idx) {
        const item = cart[idx];
        if(!item) return;

        Swal.fire({
            title: 'Add Special Note',
            input: 'textarea',
            inputLabel: 'Instructions for ' + item.name,
            inputValue: item.notes || '',
            inputPlaceholder: 'e.g. No ice, Extra spicy...',
            showCancelButton: true,
            confirmButtonColor: 'var(--accent-green)',
            cancelButtonColor: '#6c757d',
            background: 'var(--bg-surface)',
            color: 'var(--text-main)',
            confirmButtonText: 'Save Note'
        }).then((result) => {
            if (result.isConfirmed) {
                cart[idx].notes = result.value;
                updateCart();
            }
        });
    };
    
    $('#btn-clear-cart').on('click', () => { 
        if(cart.length === 0) return;
        Swal.fire({
            title: 'Clear entire ticket?',
            text: "All items will be removed.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--accent-red)',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, clear it!',
            background: 'var(--bg-surface)',
            color: 'var(--text-main)'
        }).then((result) => {
            if (result.isConfirmed) {
                cart = []; 
                editingOrderId = null;
                $('#btn-finish-order').text('Place Order').css('background', '').css('color', '');
                updateCart(); 
                $('#form-order-table').val('');
            }
        });
    });

    function updateCart() {
        const container = $('#cart-tbody');
        container.empty();
        let total = 0;

        if (cart.length === 0) {
            $('#empty-cart-msg').show();
            $('#cart-table').hide();
            $('#btn-finish-order').prop('disabled', true);
            $('#cart-total-display').text('Grand Total : 0.00');
            return;
        }

        $('#empty-cart-msg').hide();
        $('#cart-table').show();
        $('#btn-finish-order').prop('disabled', false);

        cart.forEach((item, idx) => {
            const lineTotal = item.price * item.quantity;
            total += lineTotal;
            // Matches image layout: Item name, Variant, Price, Qty (- +), Action (Trash)
            container.append(`
                <tr>
                    <td>
                        <div class="item-name">${item.name}</div>
                        ${item.notes ? `<div style="font-size:0.7rem; color:var(--accent-yellow); font-style:italic;">Note: ${item.notes}</div>` : ''}
                    </td>
                    <td>${item.variant || '-'} ${item.sell_type === 'tot' ? '<small>(' + (item.portion_label || 'Shot') + ')</small>' : ''}</td>
                    <td>${item.price.toLocaleString(undefined, {maximumFractionDigits: 0})}</td>
                    <td>
                        <div class="qty-controls">
                            <button class="qty-btn minus" onclick="updateQty(${idx}, 'sub')"><i class="fa fa-minus"></i></button>
                            <div class="qty-val">${item.quantity}</div>
                            <button class="qty-btn" onclick="updateQty(${idx}, 'add')"><i class="fa fa-plus"></i></button>
                        </div>
                    </td>
                    <td style="text-align:center;">
                        <div style="display:flex; gap:5px; justify-content:center; align-items:center;">
                            <button class="btn btn-sm btn-outline-info" onclick="editItemNote(${idx})"><i class="fa fa-pencil"></i></button>
                            <button class="btn-trash-row" onclick="removeItem(${idx})"><i class="fa fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
            `);
        });

        $('#cart-total-display').text('Grand Total : ' + total.toLocaleString(undefined, {maximumFractionDigits: 0}));
    }

    // Toast Notification helper (Renamed to avoid conflict with layout's Toast)
    const KioskToast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        background: '#242424',
        color: '#fff',
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    let identifyXhr = null;
    let lastIdentifyPin = '';
    $(document).on('keydown', '#form-waiter-pin', function(e) {
        if(e.which === 13) {
            e.preventDefault();
            // Optional: focus the place order button instead of clicking it
            $('#btn-finish-order').focus();
            return false;
        }
    });

    $(document).on('input keyup paste change', '#form-waiter-pin', function() {
        const pin = this.value.replace(/[^0-9]/g, '').slice(0, 4);
        if (this.value !== pin) this.value = pin;
        
        if (pin === lastIdentifyPin) return;
        lastIdentifyPin = pin;

        const nameDisplay = $('#form-waiter-name-display');
        const idInput = $('#form-waiter-id');
        const ownerId = $('#kiosk-owner-id').val();
        
        if (identifyXhr) identifyXhr.abort();

        if (pin.length > 0) {
            nameDisplay.html('<i class="fa fa-spinner fa-spin"></i>').css('color', 'var(--text-muted)');
            identifyXhr = $.ajax({
                url: '{{ route("bar.kiosk.identify") }}',
                method: 'POST',
                data: { pin: pin, user_id: ownerId, _token: '{{ csrf_token() }}' },
                success: function(res) {
                    if (res.success) {
                        nameDisplay.html('<i class="fa fa-check-circle"></i> ' + res.waiter.name).css('color', 'var(--accent-green)');
                        idInput.val(res.waiter.id);
                        
                        // Auto-highlight button if 4 digits AND cart has items (Optional: Visual cue instead of click)
                        if (pin.length === 4 && cart.length > 0) {
                            $('#btn-finish-order').addClass('pulse-glow');
                            setTimeout(() => $('#btn-finish-order').removeClass('pulse-glow'), 2000);
                        }
                    }
                },
                error: function(xhr, status) {
                    if (status === 'abort') return;
                    if (pin.length >= 4) {
                        nameDisplay.text('Not Found').css('color', 'var(--accent-red)');
                    } else {
                        nameDisplay.text(''); // Show nothing for partial
                    }
                    idInput.val('');
                }
            });
        } else {
            nameDisplay.text('');
            idInput.val('');
        }
    });

    // --- PLACE ORDER (Confirmation Flow) ---
    $('#btn-finish-order').on('click', () => {
        const waiterId = $('#form-waiter-id').val();
        const pin = $('#form-waiter-pin').val();
        
        if (!pin || pin.length < 4) {
            KioskToast.fire({ icon: 'warning', title: 'Valid PIN required' });
            $('#form-waiter-pin').focus().css('border-color', 'var(--accent-red)');
            setTimeout(() => { $('#form-waiter-pin').css('border-color', '#333'); }, 2000);
            return;
        }

        if (cart.length === 0) {
            KioskToast.fire({ icon: 'warning', title: 'Your cart is empty!' });
            return;
        }

        const waiterName = $('#form-waiter-name-display').text().replace('✓ ', '').trim() || 'Staff';
        let itemsSummary = '<div style="text-align:left; font-size:0.95rem; max-height:220px; overflow-y:auto; background:#2d2d2d; color:#ffffff; padding:12px; border-radius:10px; border:1px solid #555; box-shadow: inset 0 1px 0 rgba(255,255,255,0.06);">';
        let total = 0;
        cart.forEach(item => {
            const rowTotal = item.price * item.quantity;
            total += rowTotal;
            const variantBit = (item.variant && String(item.variant).trim()) ? ` <span style="color:#c8c8c8;font-size:0.85em;font-weight:400;">(${String(item.variant).trim()})</span>` : '';
            itemsSummary += `<div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:6px; border-bottom:1px solid #4a4a4a; padding-bottom:6px;">
                <div style="flex:1; min-width:0; color:#ffffff; text-align:left;">
                    <span style="font-weight:600;">${item.quantity}× ${item.name}</span>${variantBit}
                </div>
                <span style="color:#8ef0a0; white-space:nowrap; font-weight:700;">TSh ${rowTotal.toLocaleString(undefined, {maximumFractionDigits: 0})}</span>
            </div>`;
        });
        itemsSummary += `</div><div style="text-align:right; font-size:1.2rem; font-weight:bold; margin-top:12px; color:#ffd666;">Total: TSh ${total.toLocaleString(undefined, {maximumFractionDigits: 0})}</div>`;

        Swal.fire({
            title: 'Confirm Order Placement',
            html: `
                <div style="text-align:center; margin-bottom:15px;">
                    <span class="badge badge-info" style="font-size:1rem; padding:8px 15px;">Waiter: ${waiterName}</span>
                </div>
                ${itemsSummary}
                <div class="mt-3" style="font-size:0.8rem; color:#cccccc; line-height:1.4;">Kitchen docket prints for food only. Print the full receipt (food + drinks) from <strong style="color:#fff;">My Orders</strong>.</div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fa fa-check"></i> PLACE ORDER',
            cancelButtonText: 'Review Changes',
            confirmButtonColor: 'var(--accent-green)',
            cancelButtonColor: '#444',
            background: 'var(--bg-surface)',
            color: 'var(--text-main)',
            width: '450px'
        }).then((result) => {
            if (result.isConfirmed) {
                executeOrderSubmission(waiterId, pin);
            }
        });
    });

    function executeOrderSubmission(waiterId, pin) {
        const btn = $('#btn-finish-order');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

        $.ajax({
            url: '{{ route("bar.kiosk.login") }}',
            method: 'POST',
            data: { 
                waiter_id: waiterId, 
                pin: pin, 
                user_id: $('#kiosk-owner-id').val(),
                _token: '{{ csrf_token() }}' 
            },
            success: function(res) {
                // Determine URL and Method based on mode
                let url = editingOrderId ? '{{ url("/bar/kiosk/add-items") }}/' + editingOrderId : '{{ route("bar.kiosk.create-order") }}';
                
                $.ajax({
                    url: url,
                    method: 'POST',
                    data: {
                        items: cart,
                        table_id: $('#form-order-table').val(),
                        customer_name: $('#form-customer-name').val(),
                        customer_phone: $('#form-customer-phone').val(),
                        order_source: 'kiosk',
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(orderRes) {
                        if (orderRes.success) {
                            // 1. Immediately close the Confirmation modal to prevent stacking
                            Swal.close();
                            
                            btn.prop('disabled', false).html('Place Order');
                            
                            KioskToast.fire({
                                icon: 'success',
                                title: editingOrderId ? 'Items added to Ticket ' + orderRes.order.order_number : 'Ticket ' + orderRes.order.order_number + ' Sent successfully!'
                            });

                            const isUpdating = !!editingOrderId;
                            const orderIdStr = orderRes.order.id;
                            const needsKitchenDocket = cart.some(i => i.type === 'food' && !(i.food_category && foodCategoryIsBeverage(i.food_category)));
                            const waiterName = $('#form-waiter-name-display').text().replace('✓ ', '').trim() || '';

                            // 2. Clear Cart and UI immediately
                            cart = [];
                            editingOrderId = null;
                            $('#btn-finish-order').text('Place Order').removeClass('btn-info').addClass('btn-yellow');
                            updateCart();
                            $('#form-order-table').val('');
                            $('#form-customer-name').val('');
                            $('#form-customer-phone').val('');
                            $('#form-waiter-name-display').text('');

                            // 3. Play Thank You voice
                            try {
                                const utter = new SpeechSynthesisUtterance('Thank you ' + waiterName + '. Welcome back.');
                                const voices = window.speechSynthesis.getVoices();
                                const femaleVoice = voices.find(v => (v.name.includes('Female') || v.name.includes('Samantha') || v.name.includes('Zira') || v.name.includes('Google US English')));
                                if (femaleVoice) utter.voice = femaleVoice;
                                utter.lang = 'en-US';
                                utter.rate = 1.0;
                                utter.pitch = 1.3;
                                window.speechSynthesis.cancel();
                                window.speechSynthesis.speak(utter);
                            } catch(e) {}

                            const finalizeOrderSuccessUi = () => {
                                // Close any remaining modals/backdrops
                                $('#addItemModal, #actionAuthModal, #kioskOrdersModal').modal('hide');
                                $('body').removeClass('modal-open');
                                $('.modal-backdrop').remove();

                                // Clear remaining inputs
                                $('#form-waiter-pin').val('');
                                $('#form-waiter-id').val('');
                                
                                // Refresh background data
                                fetchOngoingOrders(null, true);
                                refreshKioskData();

                                // 4. Handle Kitchen Docket Printing AFTER the modal is gone
                                // This prevents focus theft from "freezing" the main window
                                if (needsKitchenDocket) {
                                    const docketUrl = '{{ url("/bar/kiosk/print-docket") }}/' + orderIdStr;
                                    window.open(docketUrl, '_blank', 'width=420,height=700');
                                }
                            };

                            // 5. Show Success Alert
                            // For food: Use a button to ensure the popup isn't blocked and focus isn't stolen mid-animation
                            // For drinks: Use automatic timer
                            Swal.fire({
                                icon: 'success',
                                title: isUpdating ? 'Ticket Updated!' : '✅ Order Dispatched!',
                                html: '<b>Ticket: ' + orderRes.order.order_number + '</b><br><span style="font-size:0.9rem; color:#aaa;">Your order has been sent to the counter.</span>',
                                showConfirmButton: needsKitchenDocket, 
                                confirmButtonText: 'OK (Print Docket)',
                                timer: needsKitchenDocket ? null : 2000,
                                timerProgressBar: !needsKitchenDocket,
                                background: '#1e1e1e',
                                color: '#fff',
                                allowOutsideClick: false,
                                returnFocus: false,
                                didClose: function() {
                                    finalizeOrderSuccessUi();
                                }
                            });


                        }
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).html('Place Order');
                        let msg = 'Failed sending ticket.';
                        if (xhr.responseJSON && xhr.responseJSON.errors) {
                            msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
                        } else if (xhr.responseJSON && xhr.responseJSON.error) {
                            msg = xhr.responseJSON.error;
                        }
                        KioskToast.fire({ icon: 'error', title: msg });
                    }
                });
            },
            error: function(xhr) {
                btn.prop('disabled', false).html('Place Order');
                KioskToast.fire({ icon: 'error', title: xhr.responseJSON?.error || 'Auth failed' });
                $('#form-waiter-pin').val('').focus();
            }
        });
    }

    window.refreshKioskData = function() {
        const ownerId = $('#kiosk-owner-id').val();
        $.ajax({
            url: '{{ route("bar.kiosk.products-json") }}',
            method: 'POST',
            data: { user_id: ownerId, _token: '{{ csrf_token() }}' },
            success: function(res) {
                if (res.success) {
                    renderProductGrid(res.variants, res.foodItems);
                    
                    // Update kitchen badge
                    const badge = $('.kitchen-badge');
                    badge.text(res.kitchenReadyCount);
                    if(res.kitchenReadyCount > 0) badge.addClass('ready');
                    else badge.removeClass('ready');
                }
            }
        });
    };

    function renderProductGrid(variants, foodItems) {
        const grid = $('#pos-items-grid');
        const activeCategory = $('.category-pill.active').data('category') || 'all';
        grid.empty();

        const icons = ['fa-glass', 'fa-coffee', 'fa-lemon-o', 'fa-beer', 'fa-flask', 'fa-tint'];
        const foodIcons = ['fa-cutlery', 'fa-fire', 'fa-birthday-cake', 'fa-leaf', 'fa-heart', 'fa-apple'];
        const colors = ['#ffb822', '#17a2b8', '#6f42c1', '#28a745', '#fd7e14', '#007bff'];
        const foodColors = ['#dc3545', '#e74c3c', '#d35400', '#c0392b', '#ff4757', '#e84118'];

        // Map categories for colors and icons (replicating PHP logic temporarily)
        const normalizeCategory = (value) => (value ?? '').toString().trim();
        const drinkCatsRaw = variants.flatMap(v => (v.category ?? '').toString().split(/[,|\/]+/));
        const drinkCats = [...new Set(drinkCatsRaw.map(c => c.trim()).filter(Boolean))];
        const foodCats = [...new Set(foodItems.map(f => normalizeCategory(f.category)).filter(Boolean))];

        const getSlug = (str) => normalizeCategory(str).toLowerCase().replace(/ /g, '-').replace(/[^\w-]+/g, '') || 'food';

        // Render Drinks
        variants.forEach(v => {
            const normalizedDrinkCategory = normalizeCategory(v.category);
            const categories = normalizedDrinkCategory.split(/[,|\/]+/);
            const catClasses = categories.map(c => 'cat-' + getSlug(c.trim())).join(' ');
            
            // For icon/color mapping, use the first category assigned
            const primaryCat = categories[0] ? categories[0].trim() : '';
            const catIdx = drinkCats.indexOf(primaryCat);
            const catColor = colors[catIdx % colors.length] || '#555';
            const catIcon = icons[catIdx % icons.length] || 'fa-glass';

            let imgHtml = v.product_image 
                ? `<img src="/storage/${v.product_image}" class="prod-img">`
                : `<div class="prod-img" style="display:flex; align-items:center; justify-content:center; font-size:2.5rem; color:${catColor};"><i class="fa ${catIcon}"></i></div>`;

            let lowStockHtml = v.low_stock 
                ? `<span class="low-stock-badge">Low Stock: ${v.quantity}</span>`
                : '';

            grid.append(`
                <div class="prod-card pos-item cat-drinks ${catClasses}" 
                     data-id="${v.id}" 
                     data-name="${v.product_name}" 
                     data-variant="${v.variant}"
                     data-portion-label="${v.portion_label}"
                     data-unit-label="${v.unit}"
                     data-total-tots="${v.total_tots}"
                     data-open-tots="${v.open_tots || 0}"
                     data-price="${v.selling_price}"
                     data-price-tot="${v.selling_price_per_tot}"
                     data-can-tot="${v.can_sell_in_tots ? 'true' : 'false'}"
                     data-low-stock="${v.low_stock ? 'true' : 'false'}"
                     data-available="${v.quantity}"
                     data-type="drink"> 
                    ${lowStockHtml}
                    ${imgHtml}
                    <div class="prod-info">
                        <div class="prod-title">${v.product_name}</div>
                        <div style="font-weight:normal; color:#aaa; font-size:0.75rem;">${v.variant ? '(' + v.variant + ')' : '&nbsp;'}</div>
                        <div class="prod-price d-flex flex-column">
                            <span style="font-size: 0.85rem;">TSh ${parseFloat(v.selling_price).toLocaleString(undefined, {maximumFractionDigits: 0})} <small class="text-muted">(${v.unit === 'btl' ? 'Btl' : 'Full'})</small></span>
                            ${v.can_sell_in_tots && v.selling_price_per_tot > 0 ? 
                                `<small style="font-size: 0.7rem; color: var(--accent-green); font-weight: bold; margin-top: 1px;">
                                    TSh ${parseFloat(v.selling_price_per_tot).toLocaleString(undefined, {maximumFractionDigits: 0})} <span style="color: var(--text-muted); font-weight: normal;">(${v.portion_label || 'Glass'})</span>
                                </small>` : '' 
                            }
                        </div>
                    </div>
                </div>
            `);
        });

        // Render Food
        foodItems.forEach(f => {
            const normalizedFoodCategory = normalizeCategory(f.category);
            const catSlug = normalizedFoodCategory ? getSlug(normalizedFoodCategory) : 'food';
            const catIdx = normalizedFoodCategory ? foodCats.indexOf(normalizedFoodCategory) : 0;
            const catColor = foodColors[catIdx % foodColors.length] || '#dc3545';
            const catIcon = foodIcons[catIdx % foodIcons.length] || 'fa-cutlery';

            let imgHtml = f.image 
                ? `<img src="/storage/${f.image}" class="prod-img">`
                : `<div class="prod-img" style="display:flex; align-items:center; justify-content:center; font-size:2.5rem; color:${catColor};"><i class="fa ${catIcon}"></i></div>`;

            grid.append(`
                <div class="prod-card pos-item cat-food cat-${catSlug}" 
                     data-id="${f.id}" 
                     data-name="${f.name}" 
                     data-variant="${f.variant_name || ''}"
                     data-price="${f.price}"
                     data-extras='${JSON.stringify(f.extras || [])}'
                     data-food-category="${String(f.category || '').replace(/"/g, '&quot;')}"
                     data-type="food">
                    ${imgHtml}
                    <div class="prod-info">
                        <div class="prod-title">${f.name}</div>
                        <div style="font-weight:normal; color:#aaa; font-size:0.75rem;">${f.variant_name ? '(' + f.variant_name + ')' : '&nbsp;'}</div>
                        <div class="prod-price">TSh ${parseFloat(f.price).toLocaleString(undefined, {maximumFractionDigits: 0})}</div>
                    </div>
                </div>
            `);
        });

        // Dynamic Sidebar Food Category Update
        const sideContainer = $('#food-cat-list');
        if (sideContainer.length > 0) {
            sideContainer.empty();
            if (foodCats.length === 0 && foodItems.length > 0) {
                sideContainer.append(`
                    <div class="cat-item category-pill" data-category="cat-food">
                        <i class="fa fa-cutlery" style="color: #dc3545;"></i>
                        <span style="color:var(--text-main) !important;">Kitchen</span>
                    </div>
                `);
            }

            foodCats.forEach((cat, i) => {
                const catSlug = getSlug(cat);
                const catColor = foodColors[i % foodColors.length] || '#dc3545';
                const catIcon = foodIcons[i % foodIcons.length] || 'fa-cutlery';
                sideContainer.append(`
                    <div class="cat-item category-pill" data-category="cat-${catSlug}">
                        <i class="fa ${catIcon}" style="color: ${catColor};"></i> 
                        <span style="color:var(--text-main) !important;">${cat || 'Kitchen'}</span>
                    </div>
                `);
            });
        }

        // Keep user's current category selection after auto-refresh
        if ($(`.category-pill[data-category="${activeCategory}"]`).length) {
            $(`.category-pill[data-category="${activeCategory}"]`).trigger('click');
        } else {
            $('.category-pill[data-category="all"]').trigger('click');
        }
    }

    window.fetchOngoingOrders = function(filterType = null, silent = false) {
        if (!silent) {
            Swal.fire({ title: 'Loading...', background: 'var(--bg-surface)', color: 'var(--text-main)', allowOutsideClick: false });
            Swal.showLoading();
        }

        $.ajax({
            url: '{{ route("bar.kiosk.orders") }}',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                if (!silent) Swal.close();
                if (res.success) {
                    if (filterType === 'food') {
                        res.orders = res.orders.filter(o => o.kitchen_order_items && o.kitchen_order_items.length > 0);
                    }

                    renderOrders(res.orders);

                    // Update kitchen badge with ALL active food items (pending+preparing+ready)
                    let activeCount = 0;
                    res.orders.forEach(o => {
                        if(o.kitchen_order_items) {
                            o.kitchen_order_items.forEach(i => {
                                if(i.status !== 'cancelled' && i.status !== 'completed') activeCount++;
                            });
                        }
                    });

                    const badge = $('.kitchen-badge');
                    badge.text(activeCount);
                    if(activeCount > 0) badge.addClass('ready');
                    else badge.removeClass('ready');

                    if (!silent) $('#kioskOrdersModal').modal('show');
                }
            },
            error: function(xhr) {
                if (!silent) {
                    Swal.fire({ icon: 'error', title: 'Oops', text: 'Failed to fetch orders', background: 'var(--bg-surface)', color: 'var(--text-main)' });
                }
            }
        });
    }

    function renderOrders(orders) {
        const container = $('#kioskOrdersList');
        container.empty();

        if (orders.length === 0) {
            container.html('<div class="text-center p-5 text-muted"><i class="fa fa-folder-open-o" style="font-size:3rem; margin-bottom:10px;"></i><br><h4>No Active Orders</h4></div>');
            return;
        }

        orders.forEach(order => {
            let brColor = 'var(--border-color)';
            if(order.status === 'pending') brColor = '#007bff';
            if(order.status === 'preparing') brColor = 'var(--accent-yellow)';
            if(order.status === 'ready') brColor = 'var(--accent-green)';
            
            let itemHtml = '';
            // Drinks
            if(order.items && order.items.length > 0) {
                order.items.forEach(item => {
                    let itemName = item.product_variant ? (item.product_variant.display_name || item.product_variant.product?.name) : 'Drink';
                    itemHtml += `<div style="display:flex; justify-content:space-between; font-size:0.85rem; border-bottom:1px solid var(--bg-main); padding:4px 0; color:var(--text-muted);">
                        <span>${item.quantity}x ${itemName}</span>
                        <span>TSh ${parseFloat(item.total_price).toLocaleString(undefined, {maximumFractionDigits: 0})}</span>
                    </div>`;
                });
            }
            // Food Items
            if(order.kitchen_order_items && order.kitchen_order_items.length > 0) {
                order.kitchen_order_items.forEach(item => {
                    const foodOff = item.status === 'cancelled';
                    const statusLabel = foodOff
                        ? `<span class="badge badge-secondary" style="font-size:0.65rem;">FOOD OFF</span>`
                        : `<span class="badge ${item.status === 'ready' ? 'badge-success' : 'badge-warning'}" style="font-size:0.65rem;">${item.status.toUpperCase()}</span>`;
                    const canRemoveFood = order.status !== 'cancelled' && !foodOff && item.status !== 'completed';
                    const removeFoodBtn = canRemoveFood
                        ? `<button type="button" class="btn btn-link btn-sm p-0 ml-1 align-baseline text-danger" style="font-size:0.72rem;vertical-align:middle;" onclick="event.preventDefault();event.stopPropagation();cancelKioskFoodItem(${item.id})" title="Remove this food only (drinks stay on the ticket)"><i class="fa fa-times-circle"></i></button>`
                        : '';
                    const lineStyle = foodOff ? 'opacity:0.75;text-decoration:line-through;' : '';
                    itemHtml += `<div style="display:flex; justify-content:space-between; font-size:0.85rem; border-bottom:1px solid var(--bg-main); padding:4px 0; color:var(--text-muted);${lineStyle}">
                        <span>${item.quantity}x ${item.food_item_name} ${statusLabel}${removeFoodBtn}</span>
                        <span>TSh ${parseFloat(item.total_price).toLocaleString(undefined, {maximumFractionDigits: 0})}</span>
                    </div>`;
                });
            }

            container.append(`
                <div class="card mb-3 order-selection-card" style="background:var(--bg-card); border-color:${brColor}; border-width: 2px; position:relative;" data-id="${order.id}">
                    <div style="position:absolute; top:12px; left:12px; z-index:10;">
                        <input type="checkbox" class="order-cb" value="${order.id}" style="width:24px; height:24px; cursor:pointer;" onclick="event.stopPropagation()">
                    </div>
                    <div class="card-body p-3" style="padding-left:45px !important;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                            <div>
                                <h5 style="color:var(--text-main); margin-bottom:4px;"><i class="fa fa-hashtag"></i> ${order.order_number}</h5>
                                <div style="font-size:0.8rem; color:var(--text-muted);">
                                    <i class="fa fa-clock-o"></i> ${new Date(order.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})} | ${order.table ? 'Table ' + order.table.table_number : 'Walk-in'}
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="badge" style="background:${brColor}; color:#fff; font-size:0.85rem; padding:5px 10px;">${order.status.toUpperCase()}</span>
                                <h5 class="mt-2 text-success">TSh ${parseFloat(order.total_amount).toLocaleString(undefined, {maximumFractionDigits: 0})}</h5>
                            </div>
                        </div>
                        <div class="mt-2" style="background:var(--bg-input); padding:8px; border-radius:4px;">
                            ${itemHtml}
                        </div>
                        <div class="mt-3" style="display:flex; flex-wrap: wrap; gap:10px; align-items:center;">
                            <button class="btn btn-sm btn-outline-primary" style="flex:1; min-width: 100px;" onclick="prepareAddItem(${order.id}, '${order.order_number}')"><i class="fa fa-plus"></i> Add</button>
                            <button class="btn btn-sm btn-info" style="flex:1; min-width: 100px;" onclick="printKioskOrder(${order.id})"><i class="fa fa-print"></i> Receipt</button>
                            ${(order.kitchen_docket_item_count && order.kitchen_docket_item_count > 0) ? 
                                `<button class="btn btn-sm btn-warning" style="flex:1; min-width: 100px;" onclick="printKioskDocket(${order.id})"><i class="fa fa-fire"></i> Docket</button>` : ''
                            }
                            ${order.status !== 'cancelled' && !(order.items && order.items.length) ? 
                                `<button class="btn btn-sm btn-danger" style="flex:1; min-width: 100px;" onclick="cancelKioskOrder(${order.id})"><i class="fa fa-ban"></i> Void ticket (food only)</button>` : ''
                            }
                            ${order.status !== 'cancelled' && order.items && order.items.length ? 
                                `<div class="small text-muted" style="flex-basis:100%; line-height:1.35;"><i class="fa fa-info-circle"></i> Drinks are voided or adjusted at the <strong>counter</strong> only. Use <i class="fa fa-times-circle text-danger"></i> on a line to remove <strong>food</strong> only.</div>` : ''
                            }
                        </div>
                    </div>
                </div>
            `);
        });

        // Add Multi-Print Button
        container.prepend(`
            <div class="mb-3 d-flex justify-content-between align-items-center bg-dark p-2 rounded">
                 <span class="text-white font-weight-bold ml-2"><i class="fa fa-check-square-o"></i> Combined Receipt?</span>
                 <button class="btn btn-warning font-weight-bold px-4" onclick="printCombinedOrders()"><i class="fa fa-print"></i> PRINT SELECTED</button>
            </div>
        `);

    }

    window.prepareAddItem = function(orderId, orderNumber) {
        editingOrderId = orderId;
        $('#kioskOrdersModal').modal('hide');
        
        KioskToast.fire({
            icon: 'info',
            title: 'Adding Items to #' + orderNumber,
            timer: 5000
        });

        $('#btn-finish-order').text('Update Ticket #' + orderNumber).css('background', '#17a2b8').css('color', '#fff');
        
        // Scroll cart into view on mobile
        if(window.innerWidth < 768) {
            $('.pos-cart')[0].scrollIntoView({ behavior: 'smooth' });
        }
    };

    window.fetchOrderHistory = function(period = 'today') {
        Swal.fire({ title: 'Loading History...', background: 'var(--bg-surface)', color: 'var(--text-main)', allowOutsideClick: false });
        Swal.showLoading();

        $.ajax({
            url: '{{ route("bar.kiosk.history") }}',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', period: period },
            success: function(res) {
                Swal.close();
                if (res.success) {
                    renderHistory(res.orders, res.stats, period);
                    $('#kioskOrdersModal').modal('show');
                }
            },
            error: function(xhr) {
                Swal.fire({ icon: 'error', title: 'Oops', text: 'Failed to fetch history', background: 'var(--bg-surface)', color: 'var(--text-main)' });
            }
        });
    };

    function renderHistory(orders, stats, currentPeriod) {
        const container = $('#kioskOrdersList');
        container.empty();

        const todayBtnClass = currentPeriod === 'today' ? 'btn-info' : 'btn-outline-info';
        const weekBtnClass = currentPeriod === 'week' ? 'btn-info' : 'btn-outline-info';

        if (stats) {
            container.append(`
                <div class="history-stats">
                    <div class="history-stat-box">
                        <div class="history-stat-val">TSh ${parseFloat(stats.total_sales).toLocaleString(undefined, {maximumFractionDigits: 0})}</div>
                        <div class="history-stat-label">${stats.period_label || 'Period'} Sales</div>
                    </div>
                    <div class="history-stat-box">
                        <div class="history-stat-val">${stats.total_tickets}</div>
                        <div class="history-stat-label">Tickets Paid</div>
                    </div>
                </div>
            `);
        }

        container.append(`
            <div class="mb-3">
                <div class="btn-group w-100 mb-3" role="group">
                    <button type="button" class="btn ${todayBtnClass} py-2 font-weight-bold" onclick="fetchOrderHistory('today')">Today</button>
                    <button type="button" class="btn ${weekBtnClass} py-2 font-weight-bold" onclick="fetchOrderHistory('week')">Last 7 Days</button>
                </div>
                <div class="history-search">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text" style="background:var(--bg-input); border:1px solid var(--border-color); color:var(--text-muted);"><i class="fa fa-search"></i></span>
                        </div>
                        <input type="text" id="history-search-input" class="form-control" placeholder="Search by Order # or Table..." style="background:var(--bg-input); border:1px solid var(--border-color); color:var(--text-main);">
                    </div>
                </div>
            </div>
        `);


        container.append('<div class="d-flex justify-content-between align-items-center bg-dark p-2 rounded mb-3"><span class="text-white font-weight-bold ml-2"><i class="fa fa-check-square-o"></i> Combined Receipt?</span><button class="btn btn-warning font-weight-bold px-4" onclick="printCombinedOrders()"><i class="fa fa-print"></i> PRINT SELECTED</button></div>');
        container.append('<div id="history-items-container"></div>');
        renderHistoryItems(orders);

        // Bind search event
        $('#history-search-input').on('keyup', function() {
            const query = $(this).val().toLowerCase();
            const filtered = orders.filter(o => 
                o.order_number.toLowerCase().includes(query) || 
                (o.table && o.table.table_number.toString().includes(query))
            );
            renderHistoryItems(filtered);
        });
    }

    function renderHistoryItems(orders) {
        const container = $('#history-items-container');
        container.empty();

        if (orders.length === 0) {
            container.html('<div class="text-center p-4 text-muted">No matching orders found</div>');
            return;
        }

        orders.forEach(order => {
            let statusColor = order.status === 'cancelled' ? '#dc3545' : '#28a745';
            
            // Build item list summary
            let itemSummary = [];
            if (order.items && order.items.length > 0) {
                order.items.forEach(it => {
                    itemSummary.push(`${it.quantity}x ${it.product_variant.display_name || it.product_variant.product.name}`);
                });
            }
            if (order.kitchen_order_items && order.kitchen_order_items.length > 0) {
                order.kitchen_order_items.forEach(it => {
                    itemSummary.push(`${it.quantity}x ${it.food_item_name}`);
                });
            }

            // Payment method display
            let payMethodHtml = '';
            if (order.payment_status === 'paid' || (order.order_payments && order.order_payments.length > 0)) {
                let methods = [];
                if (order.order_payments && order.order_payments.length > 0) {
                    order.order_payments.forEach(p => {
                        let methodText = p.payment_method.replace('_', ' ').toUpperCase();
                        if (p.mobile_money_provider) methodText += ` (${p.mobile_money_provider})`;
                        else if (p.payment_method === 'mobile_money' && p.transaction_reference) {
                             // Detect provider from reference or number if possible, or just show reference
                             methodText += ` - ${p.transaction_reference}`;
                        }
                        methods.push(methodText);
                    });
                } else if (order.payment_method) {
                    methods.push(order.payment_method.replace('_', ' ').toUpperCase());
                }
                
                if (methods.length > 0) {
                    payMethodHtml = `<div style="font-size:0.75rem; color:var(--accent-green); margin-top:2px;"><i class="fa fa-credit-card"></i> ${[...new Set(methods)].join(', ')}</div>`;
                }
            }

            container.append(`
                <div class="card mb-2" style="background:var(--bg-card); border-left: 4px solid ${statusColor}; position:relative;">
                    <div style="position:absolute; top:8px; left:8px; z-index:10;">
                        <input type="checkbox" class="order-cb" value="${order.id}" style="width:20px; height:20px; cursor:pointer;" onclick="event.stopPropagation()">
                    </div>
                    <div class="card-body p-2" style="display:flex; align-items:center; gap:12px; padding-left:35px !important;">
                        <div style="flex:1;">
                            <div class="font-weight-bold">#${order.order_number}</div>
                            <div style="font-size:0.75rem; color:var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 250px;">
                                ${itemSummary.join(', ')}
                            </div>
                            <small class="text-muted">${new Date(order.created_at).toLocaleDateString()} ${new Date(order.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</small>
                            ${payMethodHtml}
                        </div>
                        <div class="text-right" style="margin-right:12px;">
                            <div class="font-weight-bold text-success">TSh ${parseFloat(order.total_amount).toLocaleString(undefined, {maximumFractionDigits: 0})}</div>
                            <div class="d-flex flex-column align-items-end" style="gap:4px;">
                                <span class="badge" style="background:${statusColor}; color:#fff; font-size:0.65rem; width:fit-content;">${order.status.toUpperCase()}</span>
                                <span class="badge" style="background:${order.payment_status === 'paid' ? 'var(--accent-green)' : '#dc3545'}; color:#fff; font-size:0.65rem; width:fit-content;">${(order.payment_status || 'unpaid').toUpperCase()}</span>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="printKioskOrder(${order.id})"><i class="fa fa-print"></i></button>
                    </div>
                </div>
            `);
        });

    }

    window.printKioskOrder = function(orderId) {
        const receiptUrl = '{{ url("bar/kiosk/print-receipt") }}/' + orderId;
        const printWindow = window.open(receiptUrl, '_blank', 'width=400,height=600');
        if (printWindow) {
            printWindow.onload = function() { printWindow.print(); };
        } else {
            KioskToast.fire({ icon: 'error', title: 'Pop-up blocked. Please allow pop-ups for this site.' });
        }
    };

    window.printCombinedOrders = function() {
        const selectedIds = [];
        $('.order-cb:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            KioskToast.fire({ icon: 'warning', title: 'Please select at least one order to print.' });
            return;
        }

        const idsParam = selectedIds.join(',');
        const receiptUrl = '{{ url("bar/kiosk/print-combined-receipt") }}?ids=' + idsParam;
        
        const printWindow = window.open(receiptUrl, '_blank', 'width=400,height=600');
        if (printWindow) {
            printWindow.onload = function() { printWindow.print(); };
        } else {
            KioskToast.fire({ icon: 'error', title: 'Pop-up blocked. Please authorize pop-ups for combined printing.' });
        }
    };


    window.printKioskDocket = function(orderId) {
        const docketUrl = '{{ url("bar/kiosk/print-docket") }}/' + orderId;
        const printWindow = window.open(docketUrl, '_blank', 'width=400,height=600');
        if (printWindow) {
            printWindow.onload = function() { printWindow.print(); };
        } else {
            KioskToast.fire({ icon: 'error', title: 'Pop-up blocked. Please allow pop-ups for this site.' });
        }
    };

    window.cancelKioskFoodItem = function(kitchenItemId) {
        Swal.fire({
            title: 'Remove this food?',
            html: '<p class="text-left mb-0">This only cancels the <strong>kitchen line</strong>. Drinks on the same ticket stay — the order stays open for the counter.</p>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: 'var(--accent-red)',
            confirmButtonText: 'Yes, remove food',
            cancelButtonText: 'Back',
            background: 'var(--bg-surface)',
            color: 'var(--text-main)'
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.ajax({
                url: '{{ url("bar/kiosk/cancel-food-item") }}/' + kitchenItemId,
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', reason: 'Waiter removed from kiosk' },
                success: function(res) {
                    if (res.success) {
                        KioskToast.fire({ icon: 'success', title: 'Food removed from ticket' });
                        fetchOngoingOrders(null, true);
                    }
                },
                error: function(xhr) {
                    KioskToast.fire({ icon: 'error', title: xhr.responseJSON?.error || 'Could not remove food' });
                }
            });
        });
    };

    window.cancelKioskOrder = function(orderId) {
        Swal.fire({
            title: 'Void this food-only ticket?',
            html: '<p class="text-left mb-0">This ticket has <strong>no drinks</strong>. Voiding will cancel all remaining kitchen (food) lines and close the ticket. Tickets that include drinks must be voided at the <strong>counter</strong>.</p>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--accent-red)',
            confirmButtonText: 'Yes, void ticket',
            cancelButtonText: 'Back',
            background: 'var(--bg-surface)',
            color: 'var(--text-main)'
        }).then((result) => {
            if (result.isConfirmed) {
                // Submit cancel
                $.ajax({
                    url: '{{ url("bar/kiosk/cancel-order") }}/' + orderId,
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(res) {
                        if (res.success) {
                            KioskToast.fire({ icon: 'success', title: 'Order Cancelled' });
                            fetchOngoingOrders(null, true); // refresh list silently
                            refreshKioskData(); // refresh stock
                        }
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.error || 'Failed to cancel';
                        KioskToast.fire({ icon: 'error', title: errorMsg });
                    }
                });
            }
        });
    }

    // --- SECONDARY ACTION AUTHORIZATION LOGIC ---
    window.promptWaiterAuth = function(actionType) {
        window.pendingAuthAction = actionType;
        $('#auth-action-type').val(actionType);
        $('#action-pin').val('');
        $('#action-waiter-id').val('');
        $('#action-auth-error').hide();
        $('#actionAuthModal').modal('show');
    };

    let actionAuthXhr = null;
    function performActionIdentify(pin) {
        const idInput = $('#action-waiter-id');
        const ownerId = $('#kiosk-owner-id').val();
        
        if (actionAuthXhr) actionAuthXhr.abort();

        if (pin.length > 0) {
            actionAuthXhr = $.ajax({
                url: '{{ route("bar.kiosk.identify") }}',
                method: 'POST',
                data: { pin: pin, user_id: ownerId, _token: '{{ csrf_token() }}' },
                success: function(res) {
                    if (res.success) {
                        idInput.val(res.waiter.id);
                        if (pin.length === 4) {
                            $('#action-auth-form').submit();
                        }
                    }
                },
                error: function(xhr, status) {
                    if (status === 'abort') return;
                    idInput.val('');
                }
            });
        } else {
            idInput.val('');
        }
    }

    $(document).on('input keyup paste change', '#action-pin', function() {
        const pin = this.value.replace(/[^0-9]/g, '').slice(0, 4);
        if (this.value !== pin) this.value = pin;
        performActionIdentify(pin);
    });

    window.actionPressKey = function(key) {
        let pinInput = $('#action-pin');
        let pin = pinInput.val();
        if (pin.length < 4) {
            pin += key;
            pinInput.val(pin);
            performActionIdentify(pin);
        }
    }
    window.actionClearPIN = function() { 
        $('#action-pin').val(''); 
        $('#action-waiter-name-display').text('');
        $('#action-waiter-id').val('');
    }

    $('#action-auth-form').on('submit', function(e) {
        e.preventDefault();
        const actionType = $('#auth-action-type').val();
        const waiterId = $('#action-waiter-id').val();
        const pin = $('#action-pin').val();
        const err = $('#action-auth-error');

        if (pin.length < 4) {
            err.text('Enter 4-digit PIN').show();
            return;
        }

        const submitBtn = $(this).find('button[type="submit"]');

        $.ajax({
            url: '{{ route("bar.kiosk.login") }}',
            method: 'POST',
            data: { 
                waiter_id: waiterId, 
                pin: pin, 
                user_id: $('#kiosk-owner-id').val(),
                _token: '{{ csrf_token() }}' 
            },
            success: function(res) {
                // Clear PIN immediately for security
                $('#action-pin').val('');
                
                if (res.success) {
                    $('#actionAuthModal').modal('hide');
                    $('#action-pin').val('');
                    
                    if (window.pendingAuthAction === 'ongoing') {
                        $('#kiosk-orders-modal-title').text('Your Ongoing Orders');
                        fetchOngoingOrders();
                    } else if (window.pendingAuthAction === 'kitchen_status') {
                        $('#kiosk-orders-modal-title').text('Kitchen Food Orders');
                        fetchOngoingOrders('food');
                    } else if (window.pendingAuthAction === 'my_order') {
                        $('#kiosk-orders-modal-title').text("Today's Full Order History");
                        fetchOrderHistory();
                    }
                }
            },
            error: function(xhr) {
                $('#action-pin').val(''); // Clear on error too
                err.text(xhr.responseJSON?.error || 'Auth failed').show();
                actionClearPIN();
            }
        });
    });

    // viewKitchenStatus is now handled via promptWaiterAuth('kitchen_status')
    window.viewKitchenStatus = function() {
        promptWaiterAuth('kitchen_status');
    };

    // --- THEME & LANGUAGE TOGGLING ---
    window.toggleTheme = function() {
        const bd = document.body;
        const icon = document.getElementById('theme-icon');
        if(bd.getAttribute('data-theme') === 'dark') {
            bd.removeAttribute('data-theme');
            icon.className = 'fa fa-moon-o'; // Sun icon for switching back to dark? Actually moon means switch to dark. Sun means switch to light.
        } else {
            bd.setAttribute('data-theme', 'dark');
            icon.className = 'fa fa-sun-o';
        }
    };

    window.toggleLang = function() {
        const currentLang = document.body.getAttribute('data-lang') || 'en';
        const newLang = currentLang === 'en' ? 'sw' : 'en';
        document.body.setAttribute('data-lang', newLang);
        
        $('.lang-text').each(function() {
            $(this).text($(this).data(newLang));
        });

        // Toggle buttons text 
        if(newLang === 'sw') {
            $('.top-btn-ongoing').text('Oda Zinazoendelea');
            $('.top-btn-kitchen').html('Hali ya Jikoni <span class="badge badge-light ml-1" style="color: black;">0</span>');
            $('.top-btn-my').text('Oda Zangu');
            $('#btn-finish-order').text('Tuma Oda');
            $('#btn-add-confirm').text('ONGEZA KWENYE TIKETI');
        } else {
            $('.top-btn-ongoing').text('Ongoing Order');
            $('.top-btn-kitchen').html('Kitchen Status <span class="badge badge-light ml-1" style="color: black;">0</span>');
            $('.top-btn-my').text('My Order');
            $('#btn-finish-order').text('Place Order');
            $('#btn-add-confirm').text('ADD TO TICKET');
        }
    };

    // --- STAFF ATTENDANCE LOGIC ---
    window.appendAttendancePin = function(n) {
        const pin = $('#attendance-pin');
        if(pin.val().length < 4) pin.val(pin.val() + n);
    };
    window.backspaceAttendancePin = function() {
        const pin = $('#attendance-pin');
        pin.val(pin.val().slice(0, -1));
    };

    window.submitAttendance = function() {
        const pin = $('#attendance-pin').val();
        if(pin.length < 4) {
            KioskToast.fire({ icon: 'warning', title: 'Please enter your 4-digit PIN' });
            return;
        }

        const btn = $('#attendanceModal button.btn-success');
        const originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

        $.ajax({
            url: '{{ route("bar.kiosk.attendance.toggle") }}',
            method: 'POST',
            data: { pin: pin, _token: '{{ csrf_token() }}' },
            success: function(res) {
                btn.prop('disabled', false).html(originalHtml);
                $('#attendanceModal').modal('hide');
                $('#attendance-pin').val('');

                // 1. Show Success Message
                Swal.fire({
                    icon: 'success',
                    title: res.status === 'in' ? 'Welcome, ' + res.staff_name : 'Goodbye, ' + res.staff_name,
                    text: res.message,
                    timer: 4000,
                    background: '#1a1a1a',
                    color: '#fff',
                    showConfirmButton: false
                });

                // 2. VOICE FEEDBACK: "Thank you [Name]"
                speakAttendanceGreeting(res.staff_name, res.status);
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(originalHtml);
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Invalid PIN';
                KioskToast.fire({ icon: 'error', title: msg });
                $('#attendance-pin').val('');
            }
        });
    };

    function speakAttendanceGreeting(name, status) {
        try {
            const text = status === 'in' ? `Thank you ${name}. Successfully signed in.` : `Thank you ${name}. Successfully signed out. Have a good rest.`;
            const utter = new SpeechSynthesisUtterance(text);
            utter.lang = 'en-US';
            utter.rate = 0.9;
            utter.pitch = 1.1;
            
            // Try to find a professional female voice
            const voices = window.speechSynthesis.getVoices();
            const preferred = voices.find(v => v.name.includes('Female') || v.name.includes('Google US English') || v.name.includes('Samantha'));
            if(preferred) utter.voice = preferred;

            window.speechSynthesis.cancel();
            window.speechSynthesis.speak(utter);
        } catch(e) { console.error("Speech error", e); }
    }
    $(document).ready(function() {
        $('#form-waiter-pin').val('');
        $('#form-waiter-name-display').text('');
        $('#form-waiter-id').val('');
        $('#form-customer-name').val('');
        $('#form-customer-phone').val('');

        // Auto-refresh every 60 seconds
        setInterval(refreshKioskData, 60000);
    });
</script>
@endpush

