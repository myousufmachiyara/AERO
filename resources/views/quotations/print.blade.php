@extends('layouts.app')

@section('title', 'Print — ' . $quotation->quotation_no)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header"><h2 class="card-title">Quotation — {{ $quotation->quotation_no }}</h2></header>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Date:</strong> {{ $quotation->quotation_date->format('d/m/Y') }}</div>
                    <div class="col-md-4"><strong>Valid Until:</strong> {{ optional($quotation->valid_until)->format('d/m/Y') ?: '—' }}</div>
                    <div class="col-md-4"><strong>Customer:</strong> {{ $quotation->customer->name ?? '—' }}</div>
                </div>

                @if($quotation->passengers->isNotEmpty())
                <h5>Passengers</h5>
                <table class="table table-bordered">
                    <thead><tr><th>Name</th><th>Passport/NIC</th><th>Type</th></tr></thead>
                    <tbody>
                        @foreach($quotation->passengers as $pax)
                        <tr><td>{{ $pax->name }}</td><td>{{ $pax->passport_no_nic }}</td><td>{{ ucfirst($pax->pax_type) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
                @endif

                <h5>Services Quoted</h5>
                <table class="table table-bordered">
                    <thead><tr><th>Service</th><th>Description</th><th>Amount</th></tr></thead>
                    <tbody>
                        @foreach($quotation->serviceLines as $line)
                        <tr>
                            <td>{{ ucfirst($line->service_type) }}</td>
                            <td>{{ $line->description }}</td>
                            <td>{{ $line->currency }} {{ number_format($line->receivable_l_amount, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold"><td colspan="2" class="text-end">Total</td><td>{{ number_format($quotation->total_receivable, 2) }}</td></tr>
                    </tfoot>
                </table>
                @if($quotation->remarks)<p><strong>Remarks:</strong> {{ $quotation->remarks }}</p>@endif
                <button class="btn btn-primary no-print" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
            </div>
        </section>
    </div>
</div>
@endsection
