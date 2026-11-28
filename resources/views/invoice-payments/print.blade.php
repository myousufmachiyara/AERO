{{-- Receipt (customer) / Payment (supplier) voucher print — company header, amount in words, signatures. --}}
@php
    $isReceipt = $payment->direction === 'receipt';
    $party = $isReceipt ? ($payment->customer->name ?? '—') : ($payment->supplier->name ?? '—');
    $voucherNo = ($isReceipt ? 'RV' : 'PV') . ($payment->voucher_id ?? $payment->id);
    $cashBank = $isReceipt ? $payment->voucher?->debitAccount : $payment->voucher?->creditAccount;
@endphp
@extends('prints._document', ['docTitle' => $isReceipt ? 'PAYMENT RECEIPT' : 'PAYMENT VOUCHER', 'docNo' => $voucherNo, 'compact' => true])

@section('meta')
    <div><span>Voucher No</span> <strong>{{ $voucherNo }}</strong></div>
    <div><span>Date</span> {{ $payment->payment_date->format('d/m/Y') }}</div>
    <div><span>{{ $isReceipt ? 'Received From' : 'Paid To' }}</span> <strong>{{ $party }}</strong></div>
    <div><span>Against Invoice</span> {{ $payment->invoiceNo() ?? '—' }}</div>
    <div><span>Mode</span> {{ ucfirst($payment->payment_mode) }}{{ $cashBank ? ' — ' . $cashBank->name : '' }}</div>
    <div><span>{{ $payment->payment_mode === 'cheque' ? 'Cheque No' : 'Reference' }}</span> {{ $payment->reference ?: '—' }}</div>
@endsection

@section('body')
    <table>
        <thead><tr><th>Description</th><th class="num" style="width:160px">Amount (PKR)</th></tr></thead>
        <tbody>
            <tr>
                <td>
                    {{ $isReceipt ? 'Amount received' : 'Amount paid' }}{{ $payment->invoiceNo() ? ' against invoice ' . $payment->invoiceNo() : '' }}
                    @if($payment->remarks)<div class="muted">{{ $payment->remarks }}</div>@endif
                </td>
                <td class="num"><strong>{{ number_format((float) $payment->amount, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
    <div class="words">{{ \App\Support\AmountInWords::rupees((float) $payment->amount) }}</div>

    @if($isReceipt && $payment->invoice())
        @php($inv = $payment->invoice())
        <table class="totals">
            <tr><td>Invoice Total</td><td class="num">{!! \App\Support\Money::receivable((float) ($inv->total_receivable ?? $inv->total_amount)) !!}</td></tr>
            <tr><td>Total Received to date</td><td class="num">{{ number_format($inv->receivedAmount(), 2) }}</td></tr>
            <tr class="grand"><td>Balance Due</td><td class="num">{!! \App\Support\Money::receivable($inv->outstandingAmount()) !!}</td></tr>
        </table>
    @endif

    <div class="signs">
        <div>{{ $isReceipt ? 'Received By' : 'Prepared By' }}{{ $payment->creator ? ' — ' . $payment->creator->name : '' }}</div>
        <div>{{ $isReceipt ? 'Customer Signature' : 'Receiver Signature' }}</div>
    </div>
@endsection
