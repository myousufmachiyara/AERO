@extends('layouts.app')

@section('title', 'Detailed Print — ' . $invoice->invoice_no)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header"><h2 class="card-title">Sale Invoice (Detailed) — {{ $invoice->invoice_no }}</h2></header>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Date:</strong> {{ $invoice->invoice_date->format('d/m/Y') }}</div>
                    <div class="col-md-3"><strong>Adjustment Date:</strong> {{ $invoice->effectiveAdjustmentDate()->format('d/m/Y') }}</div>
                    <div class="col-md-3"><strong>Customer:</strong> {{ $invoice->customer->name ?? '—' }}</div>
                    <div class="col-md-3"><strong>Status:</strong> {{ ucfirst($invoice->status) }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Created By:</strong> {{ $invoice->creator->name ?? '—' }}</div>
                    <div class="col-md-3"><strong>Posted By:</strong> {{ $invoice->poster->name ?? '—' }}</div>
                    <div class="col-md-3"><strong>Posted At:</strong> {{ optional($invoice->posted_at)->format('d/m/Y H:i') }}</div>
                </div>

                <div class="table-scroll">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>Passenger</th><th>Type</th><th>PNR</th><th>Ticket #</th><th>Airline</th><th>Supplier</th><th>Cities</th>
                            <th>Fare</th><th>Tax</th><th>APT %</th><th>APT</th><th>Comm %</th><th>Comm Amt</th><th>WHT</th><th>PSF %</th><th>PSF</th><th>Discount</th>
                            <th>Agent</th><th>Agent Comm %</th><th>Agent Comm</th><th>Status</th><th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->lines as $line)
                        <tr>
                            <td>{{ $line->pax_name }}</td>
                            <td>{{ ucfirst($line->pax_type) }}</td>
                            <td>{{ $line->pnr }}</td>
                            <td>{{ $line->ticket_no }}</td>
                            <td>{{ $line->airline->name ?? '—' }}</td>
                            <td>{{ $line->supplier->name ?? '—' }}</td>
                            <td>{{ $line->citiesLabel() }}</td>
                            <td>{{ number_format($line->fare_amount, 2) }}</td>
                            <td>{{ number_format($line->tax_amount, 2) }}</td>
                            <td>{{ number_format($line->apt_percent, 2) }}%</td>
                            <td>{{ number_format($line->apt_charges, 2) }}</td>
                            <td>{{ number_format($line->commission_percent, 2) }}</td>
                            <td>{{ number_format($line->commission_amount, 2) }}</td>
                            <td>{{ number_format($line->wht_amount, 2) }}</td>
                            <td>{{ number_format($line->psf_percent, 2) }}% <small class="text-muted">({{ $line->psf_basis === 'total' ? 'fare+tax+apt' : 'fare' }})</small></td>
                            <td>{{ number_format($line->psf_amount, 2) }}</td>
                            <td>{{ number_format($line->discount_amount, 2) }}</td>
                            <td>{{ $line->salesAgent->name ?? '—' }}</td>
                            <td>{{ number_format($line->agent_commission_percent, 2) }}%</td>
                            <td>{{ number_format($line->agent_commission_amount, 2) }}</td>
                            <td>{{ ucfirst($line->status) }}</td>
                            <td>{{ number_format($line->effectiveReceivable(), 2) }}</td>
                        </tr>
                        @if($line->status === 'refunded')
                        <tr class="table-light"><td colspan="22" class="text-muted">
                            Refunded {{ optional($line->refund_date)->format('d/m/Y') }} (adj. {{ optional($line->refund_adjustment_date)->format('d/m/Y') }}) —
                            refund fare {{ number_format($line->refund_fare_amount, 2) }}, refund tax {{ number_format($line->refund_tax_amount, 2) }},
                            refund charges {{ number_format($line->refund_charges, 2) }}, returned {{ number_format($line->refund_amount, 2) }}, retained profit {{ number_format($line->refund_profit, 2) }}
                        </td></tr>
                        @elseif($line->status === 'voided')
                        <tr class="table-light"><td colspan="22" class="text-muted">
                            Voided {{ optional($line->void_date)->format('d/m/Y') }} —
                            supplier deduction {{ number_format($line->void_deduction_supplier, 2) }},
                            {{ config('travel.company_name') }} deduction {{ number_format($line->void_deduction_company, 2) }},
                            total {{ number_format($line->void_total_deduction, 2) }}
                        </td></tr>
                        @endif
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="7" class="text-end">Totals</td>
                            <td>{{ number_format($invoice->total_fare, 2) }}</td>
                            <td>{{ number_format($invoice->total_tax, 2) }}</td>
                            <td></td>
                            <td>{{ number_format($invoice->total_apt, 2) }}</td>
                            <td></td>
                            <td>{{ number_format($invoice->total_commission, 2) }}</td>
                            <td>{{ number_format($invoice->total_wht, 2) }}</td>
                            <td></td>
                            <td>{{ number_format($invoice->total_psf, 2) }}</td>
                            <td>{{ number_format($invoice->total_discount, 2) }}</td>
                            <td colspan="3"></td>
                            <td></td>
                            <td>{{ number_format($invoice->total_amount, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
                </div>

                @if($invoice->remarks)<p><strong>Remarks:</strong> {{ $invoice->remarks }}</p>@endif
                <button class="btn btn-primary no-print" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
            </div>
        </section>
    </div>
</div>
@endsection
