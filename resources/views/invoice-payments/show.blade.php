@extends('layouts.app')

@section('title', 'Payment #' . $payment->id)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">
                    {{ ucfirst($payment->direction) }} — {{ $payment->customer->name ?? $payment->supplier->name ?? '—' }}
                </h2>
                <div>
                    @can('invoice_payments.print')
                    <a href="{{ route('invoice_payments.print', $payment->id) }}" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                    @endcan
                </div>
            </header>
            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                <div class="row">
                    <div class="col-md-3 mb-2"><strong>Date:</strong> {{ $payment->payment_date->format('d/m/Y') }}</div>
                    <div class="col-md-3 mb-2"><strong>Amount:</strong> {{ number_format($payment->amount, 2) }}</div>
                    <div class="col-md-3 mb-2"><strong>Mode:</strong> {{ ucfirst($payment->payment_mode) }}</div>
                    <div class="col-md-3 mb-2"><strong>Reference:</strong> {{ $payment->reference ?: '—' }}</div>
                    @if($payment->travelInvoice)
                    <div class="col-md-4 mb-2">
                        <strong>Invoice:</strong>
                        <a href="{{ route(($payment->travelInvoice->invoice_type === 'sale' ? 'ticket_invoices' : 'tour_invoices') . '.show', $payment->travelInvoice->id) }}">
                            {{ $payment->travelInvoice->invoice_no }}
                        </a>
                        (Outstanding after this: {{ number_format($payment->travelInvoice->outstandingAmount(), 2) }})
                    </div>
                    @endif
                    <div class="col-md-4 mb-2">
                        <strong>Voucher:</strong>
                        {{ ucfirst($payment->voucher->voucher_type ?? '—') }} — Dr {{ $payment->voucher->debitAccount->name ?? '—' }} / Cr {{ $payment->voucher->creditAccount->name ?? '—' }}
                    </div>
                    <div class="col-md-4 mb-2"><strong>Recorded By:</strong> {{ $payment->creator->name ?? '—' }}</div>
                </div>
                @if($payment->remarks)
                <hr><strong>Remarks:</strong> {{ $payment->remarks }}
                @endif
            </div>
        </section>
    </div>
</div>
@endsection
