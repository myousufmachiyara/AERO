@extends('layouts.app')

@section('title', 'Print — Payment #' . $payment->id)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header"><h2 class="card-title">{{ ucfirst($payment->direction) }} Voucher</h2></header>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Party:</strong> {{ $payment->customer->name ?? $payment->supplier->name ?? '—' }}</div>
                    <div class="col-md-4"><strong>Date:</strong> {{ $payment->payment_date->format('d/m/Y') }}</div>
                    <div class="col-md-4"><strong>Amount:</strong> {{ number_format($payment->amount, 2) }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Mode:</strong> {{ ucfirst($payment->payment_mode) }}</div>
                    <div class="col-md-4"><strong>Reference:</strong> {{ $payment->reference ?: '—' }}</div>
                    <div class="col-md-4"><strong>Invoice:</strong> {{ $payment->travelInvoice->invoice_no ?? '—' }}</div>
                </div>
                @if($payment->remarks)<p><strong>Remarks:</strong> {{ $payment->remarks }}</p>@endif
                <button class="btn btn-primary no-print" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
            </div>
        </section>
    </div>
</div>
@endsection
