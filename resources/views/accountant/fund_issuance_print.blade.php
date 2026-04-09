@extends('layouts.dashboard')

@section('content')
<style>
    /* PETTY CASH VOUCHER STYLE - MATCHING STOCK SHEET */
    :root {
        --report-orange: #d35400;
        --report-border: #e67e22;
        --report-text: #2c3e50;
    }

    .report-page {
        background: #fff;
        padding: 40px;
        color: var(--report-text);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        min-height: 100vh;
    }

    .report-header-center { text-align: center; margin-bottom: 25px; }
    .report-header-center img { height: 50px; margin-bottom: 5px; }
    .report-header-center h1 { font-size: 2.3rem; font-weight: 800; color: var(--report-orange); margin: 0; text-transform: uppercase; }
    .biz-contact-info { font-size: 0.9rem; color: #555; margin-top: 5px; } 

    .operations-title { color: var(--report-orange); font-weight: 700; font-size: 1.25rem; margin-top: 10px; }
    .orange-divider { height: 3px; background: var(--report-orange); margin: 15px 0; border: none; }

    .report-sub-meta { display: flex; justify-content: flex-end; font-size: 0.78rem; color: #777; gap: 15px; margin-bottom: 8px; }

    .title-area { position: relative; text-align: center; margin: 25px 0; }
    .main-report-title { font-size: 1.6rem; font-weight: 800; text-transform: uppercase; display: inline-block; border-bottom: 2px solid #555; padding-bottom: 3px; }
    .official-stamp { position: absolute; right: 20%; top: -8px; border: 4px solid #27ae60; color: #27ae60; padding: 3px 12px; font-weight: 900; font-size: 1.3rem; transform: rotate(-10deg); border-radius: 8px; opacity: 0.8; text-transform: uppercase; pointer-events: none; }

    .btn-print { background: #e67e22; color: #fff; padding: 10px 25px; border-radius: 6px; border: none; font-weight: 700; }
    .btn-print:hover { background: #d35400; color: #fff; }

    .voucher-details { border: 2px solid #333; padding: 30px; border-radius: 8px; background: #fff; margin-bottom: 40px; }
    .voucher-row { display: flex; justify-content: space-between; border-bottom: 1px dashed #ccc; padding: 15px 0; font-size: 1.1rem; }
    .voucher-label { font-weight: 800; text-transform: uppercase; color: #555; font-size: 0.85rem; }
    .voucher-value { font-weight: 800; color: #1a1a1a; }
    .amount-box { background: #fdf2e9; border: 2px solid var(--report-orange); padding: 10px 20px; font-size: 1.8rem; font-weight: 900; color: var(--report-orange); text-align: center; display: inline-block; min-width: 250px; border-radius: 5px; }

    @media print {
        .app-header, .app-sidebar, .d-print-none, .breadcrumb { display: none !important; }
        .app-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .report-page { padding: 20px; }
        .voucher-details { border: 2.5px solid #000; }
    }
</style>

@php
    $isFood = str_contains($issue->purpose, '[FOOD]');
    $cleanPurpose = str_replace('[FOOD] ', '', $issue->purpose);
    $owner = auth()->user();
    $businessName = $setting_biz_name ?? 'MEDALLION RESTAURANT';
@endphp

<div class="report-page mt-3">
    
    <div class="report-header-center">
        <img src="https://ui-avatars.com/api/?name={{ urlencode($businessName) }}&background=d35400&color=fff&size=80" alt="Logo">
        <h1>{{ $businessName }}</h1>
        <div class="biz-contact-info">
             Certified Financial Document | {{ date('d M Y') }}
        </div>
        <div class="operations-title">PETTY CASH PAYMENT VOUCHER</div>
        <hr class="orange-divider">
    </div>

    <div class="report-sub-meta">
        <span>Voucher #: PCV-{{ strtoupper($issue->fund_source[0]) }}-{{ $issue->id }}-{{ date('Ymd') }}</span>
    </div>

    <div class="title-area">
        <h2 class="main-report-title">{{ $isFood ? 'KITCHEN PROCUREMENT FUND' : 'BAR OPERATIONS FUND' }}</h2>
        <div class="official-stamp">ISSUED</div>
    </div>

    <div class="text-center mb-4 d-print-none">
        <button onclick="window.print()" class="btn btn-print shadow-sm"><i class="fa fa-print"></i> Print Voucher / PDF</button>
        <a href="{{ route('accountant.fund-issuance') }}" class="btn btn-secondary ml-2"><i class="fa fa-arrow-left"></i> Back</a>
    </div>

    <div class="voucher-details">
        <div class="voucher-row">
            <span class="voucher-label">ISSUE DATE:</span>
            <span class="voucher-value">{{ $issue->issue_date->format('l, d F Y') }}</span>
        </div>
        <div class="voucher-row">
            <span class="voucher-label">RECIPIENT NAME:</span>
            <span class="voucher-value">{{ strtoupper($issue->recipient->full_name) }}</span>
        </div>
        <div class="voucher-row">
            <span class="voucher-label">STAFF ROLE:</span>
            <span class="voucher-value">{{ $issue->recipient->role->name ?? 'Staff' }}</span>
        </div>
        <div class="voucher-row">
            <span class="voucher-label">PURPOSE / DESCRIPTION:</span>
            <span class="voucher-value">{{ $cleanPurpose }}</span>
        </div>
        <div class="voucher-row">
            <span class="voucher-label">FUND SOURCE:</span>
            <span class="voucher-value">{{ strtoupper($issue->fund_source) }}</span>
        </div>
        <div class="voucher-row">
            <span class="voucher-label">DEPARTMENT:</span>
            <span class="voucher-value bg-light px-2">{{ $isFood ? 'KITCHEN / FOOD' : 'BAR / DRINKS' }}</span>
        </div>
        <div class="text-center mt-5">
            <div class="voucher-label mb-2">Total Amount Allocated:</div>
            <div class="amount-box">
                TSh {{ number_format($issue->amount) }}/=
            </div>
            <p class="mt-2 italic small text-muted">Amount in words: ____________________________________________________________________</p>
        </div>
    </div>

    {{-- DUAL SIGNATURE AREAS (CONTRACT STYLE) --}}
    <div class="mt-5 pt-3 row">
        <div class="col-md-6 border-top pt-3">
            <small class="font-weight-bold text-uppercase d-block mb-1" style="letter-spacing:1px;">1. PAYEE'S DECLARATION (Recipient)</small>
            <p class="small text-muted italic mb-3" style="line-height:1.2;">
                I, <strong>{{ $issue->recipient->full_name }}</strong>, hereby confirm that I have accepted and received the sum of <strong>TSh {{ number_format($issue->amount) }}</strong> specifically for the purpose stated above. I acknowledge that I am fully accountable for this money and will provide accurate receipts for all expenditures.
            </p>
            <div class="mt-4 text-muted">Signature: _______________________________________</div>
        </div>
        <div class="col-md-6 border-top pt-3 text-right">
            <small class="font-weight-bold text-uppercase d-block mb-1" style="letter-spacing:1px;">2. AUTHORIZED ISSUER (Accountant)</small>
            <p class="small text-muted italic mb-3" style="line-height:1.2;">
                I have withdrawn this amount from the business vault/profit as authorized and have verified the necessity of this expenditure. I hereby approve the issuance of these funds to the employee named herein.
            </p>
            <div class="mt-4 text-muted">Signature: _______________________________________</div>
        </div>
    </div>
    
    <div class="text-center mt-5 pt-4 small text-muted border-top">
        <div class="font-weight-bold">System Certified Voucher | MauzoLink Audit Tool | {{ date('d M Y, H:i') }}</div>
        <div class="mt-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">
            Powered By <strong>EmCa Techonologies LTD</strong> - <a href="https://www.emca.tech" style="color:#d35400; text-decoration:none;">www.emca.tech</a>
        </div>
    </div>

</div>

@endsection
