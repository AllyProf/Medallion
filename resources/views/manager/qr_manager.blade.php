@extends('layouts.dashboard')

@section('title', 'QR Code Portal')

@section('content')
<div class="app-title">
    <div>
        <h1><i class="fa fa-qrcode"></i> Digital QR Portal</h1>
        <p>Generate and manage QR codes for your customers to scan.</p>
    </div>
</div>

<div class="row text-center">
    <div class="col-md-6">
        <div class="tile">
            <h3 class="tile-title">Digital Menu QR</h3>
            <p class="text-muted">Place this on tables for customers to view your Food & Drinks menu.</p>
            <div class="qr-container my-4 p-4 d-inline-block shadow-sm" style="background: white; border-radius: 20px; border: 1px solid #eee;">
                {!! $menuQr !!}
            </div>
            <div class="mt-3">
                <p class="font-weight-bold mb-2">Public Link:</p>
                <code class="d-block p-2 bg-light mb-3" style="word-break: break-all;">{{ $menuUrl }}</code>
                <a href="{{ $menuUrl }}" target="_blank" class="btn btn-info btn-sm"><i class="fa fa-external-link"></i> Preview Menu</a>
                <button onclick="printQr('menu-qr-box', 'Digital Menu QR')" class="btn btn-primary btn-sm"><i class="fa fa-print"></i> Print QR</button>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="tile">
            <h3 class="tile-title">Feedback & Suggestions QR</h3>
            <p class="text-muted">Place this for customers to rate your service and provide feedback.</p>
            <div class="qr-container my-4 p-4 d-inline-block shadow-sm" style="background: white; border-radius: 20px; border: 1px solid #eee;">
                {!! $feedbackQr !!}
            </div>
            <div class="mt-3">
                <p class="font-weight-bold mb-2">Public Link:</p>
                <code class="d-block p-2 bg-light mb-3" style="word-break: break-all;">{{ $feedbackUrl }}</code>
                <a href="{{ $feedbackUrl }}" target="_blank" class="btn btn-info btn-sm"><i class="fa fa-external-link"></i> Preview Form</a>
                <button onclick="printQr('feedback-qr-box', 'Feedback & Suggestions QR')" class="btn btn-primary btn-sm"><i class="fa fa-print"></i> Print QR</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Print Helpers -->
<div id="print-area" style="display: none;">
    <div id="menu-qr-box" style="text-align: center; padding: 50px; font-family: sans-serif;">
        <h1 style="color: #0f172a; margin-bottom: 5px;">{{ $owner->name }}</h1>
        <p style="color: #64748b; font-size: 1.2rem; margin-bottom: 20px;">Scan to view our <b>Digital Menu</b></p>
        <div style="margin: 30px 0;">{!! $menuQr !!}</div>
        <p style="color: #94a3b8; font-size: 0.8rem;">Powered by MEDALLION</p>
    </div>
    <div id="feedback-qr-box" style="text-align: center; padding: 50px; font-family: sans-serif;">
        <h1 style="color: #0f172a; margin-bottom: 5px;">{{ $owner->name }}</h1>
        <p style="color: #64748b; font-size: 1.2rem; margin-bottom: 20px;">Tell us how we did! Scan for <b>Suggestions</b></p>
        <div style="margin: 30px 0;">{!! $feedbackQr !!}</div>
        <p style="color: #94a3b8; font-size: 0.8rem;">Powered by MEDALLION</p>
    </div>
</div>

<script>
function printQr(divId, title) {
    var printContent = document.getElementById(divId).innerHTML;
    var originalContent = document.body.innerHTML;
    
    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload(); // Reload to restore scripts
}
</script>
@endsection
