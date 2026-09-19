@extends('layouts.app')

@section('title', 'Print — ' . $invoice->invoice_no)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header"><h2 class="card-title">Sale Invoice — {{ $invoice->invoice_no }}</h2></header>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Date:</strong> {{ $invoice->invoice_date->format('d/m/Y') }}</div>
                    <div class="col-md-4"><strong>Customer:</strong> {{ $invoice->customer->name ?? '—' }}</div>
                </div>

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Passenger</th>
                            <th>Type</th>
                            <th>PNR</th>
                            <th>Ticket #</th>
                            <th>Airline</th>
                            <th>Cities</th>
                            <th>Fare</th>
                            <th>Tax</th>
                            <th>APT</th>
                            <th>PSF</th>
                            <th>Discount</th>
                            <th>Amount</th>
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
                            <td>{{ $line->citiesLabel() }}</td>
                            @if($line->status === 'active')
                            <td>{{ number_format($line->fare_amount, 2) }}</td>
                            <td>{{ number_format($line->tax_amount, 2) }}</td>
                            <td>{{ number_format($line->apt_charges, 2) }}</td>
                            <td>{{ number_format($line->psf_amount, 2) }}</td>
                            <td>{{ number_format($line->discount_amount, 2) }}</td>
                            <td>{{ number_format($line->total_amount, 2) }}</td>
                            @elseif($line->status === 'refunded')
                            <td colspan="5" class="text-muted">Refunded {{ optional($line->refund_date)->format('d/m/Y') }} — {{ number_format($line->refund_amount, 2) }} returned</td>
                            <td>{{ number_format($line->refund_profit, 2) }}</td>
                            @else
                            <td colspan="5" class="text-muted">Voided {{ optional($line->void_date)->format('d/m/Y') }}</td>
                            <td>{{ number_format($line->void_total_deduction, 2) }}</td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="11" class="text-end">Total</td>
                            <td>{{ number_format($invoice->total_amount, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>

                @if($invoice->remarks)<p><strong>Remarks:</strong> {{ $invoice->remarks }}</p>@endif
                <button class="btn btn-primary no-print" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
            </div>
        </section>
    </div>
</div>
@endsection