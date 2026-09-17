@php($linesByType = $invoice->serviceLines->groupBy('service_type'))
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header"><h2 class="card-title">{{ $pageTitle }} — {{ $invoice->invoice_no }}</h2></header>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Date:</strong> {{ $invoice->invoice_date->format('d/m/Y') }}</div>
                    <div class="col-md-3"><strong>Customer:</strong> {{ $invoice->customer->name ?? '—' }}</div>
                    <div class="col-md-3"><strong>Name on Invoice:</strong> {{ $invoice->name_on_invoice ?: '—' }}</div>
                    <div class="col-md-3"><strong>Payment Mode:</strong> {{ ucfirst($invoice->payment_mode) }}</div>
                </div>

                @if($invoice->passengers->isNotEmpty())
                <h5>Passengers</h5>
                <table class="table table-bordered">
                    <thead><tr><th>Name</th><th>Passport/NIC</th><th>Type</th></tr></thead>
                    <tbody>
                        @foreach($invoice->passengers as $pax)
                        <tr><td>{{ $pax->name }}</td><td>{{ $pax->passport_no_nic }}</td><td>{{ ucfirst($pax->pax_type) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
                @endif

                <h5>Services</h5>
                <table class="table table-bordered">
                    <thead><tr><th>Type</th><th>Supplier</th><th>Currency</th><th>Receivable</th><th>Payable</th><th>Income</th></tr></thead>
                    <tbody>
                        @foreach($invoice->serviceLines as $line)
                        <tr>
                            <td>{{ ucfirst($line->service_type) }}</td>
                            <td>{{ $line->supplier->name ?? '—' }}</td>
                            <td>{{ $line->currency }}</td>
                            <td>{{ number_format($line->receivable_l_amount, 2) }}</td>
                            <td>{{ number_format($line->payable_l_amount, 2) }}</td>
                            <td>{{ number_format($line->income_l_amount, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="3" class="text-end">Totals</td>
                            <td>{{ number_format($invoice->total_receivable, 2) }}</td>
                            <td>{{ number_format($invoice->total_payable, 2) }}</td>
                            <td>{{ number_format($invoice->total_income, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
                @if($invoice->remarks)<p><strong>Remarks:</strong> {{ $invoice->remarks }}</p>@endif
                <button class="btn btn-primary no-print" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
            </div>
        </section>
    </div>
</div>
