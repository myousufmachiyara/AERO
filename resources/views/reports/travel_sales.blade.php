@extends('layouts.app')
@section('title', 'Travel Sales Report')

@section('content')
<style>@media print { .no-print { display: none !important; } }</style>

<div class="tabs">
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link {{ $tab==='register' ? 'active' : '' }}" href="{{ route('reports.travel_sales', ['tab'=>'register','from_date'=>$from,'to_date'=>$to]) }}">Sales Register</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab==='by_service' ? 'active' : '' }}" href="{{ route('reports.travel_sales', ['tab'=>'by_service','from_date'=>$from,'to_date'=>$to]) }}">Income by Service Type</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab==='by_customer' ? 'active' : '' }}" href="{{ route('reports.travel_sales', ['tab'=>'by_customer','from_date'=>$from,'to_date'=>$to]) }}">Customer Wise</a></li>
    </ul>

    <div class="tab-content mt-3">

        {{-- ── SALES REGISTER ──────────────────────────────────── --}}
        <div class="tab-pane fade {{ $tab==='register' ? 'show active' : '' }}">
            <form method="GET" class="no-print">
                <input type="hidden" name="tab" value="register">
                <div class="row g-3 mb-3">
                    <div class="col-md-2"><label>From Date</label><input type="date" class="form-control" name="from_date" value="{{ $from }}"></div>
                    <div class="col-md-2"><label>To Date</label><input type="date" class="form-control" name="to_date" value="{{ $to }}"></div>
                    <div class="col-md-2">
                        <label>Invoice Type</label>
                        <select name="invoice_type" class="form-control">
                            <option value="">All</option>
                            <option value="sale" @selected($invoiceType === 'sale')>Sale (Tickets)</option>
                            <option value="tour" @selected($invoiceType === 'tour')>Tour</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Customer</label>
                        <select name="customer_id" class="form-control select2-js">
                            <option value="">All Customers</option>
                            @foreach($customers as $c)<option value="{{ $c->id }}" @selected($customerId == $c->id)>{{ $c->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                        <button type="button" class="btn btn-danger" onclick="exportPDF('register-table', 'Sales Register', '{{ $from }} to {{ $to }}')"><i class="fas fa-file-pdf"></i></button>
                    </div>
                </div>
            </form>
            <div id="register-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr><th>Date</th><th>Invoice #</th><th>Type</th><th>Customer</th><th class="text-end">Receivable</th><th class="text-end">Payable</th><th class="text-end">Income</th><th class="text-end">Outstanding</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse($register as $inv)
                        <tr>
                            <td>{{ $inv->invoice_date->format('d/m/Y') }}</td>
                            <td>
                                <a href="{{ route(($inv->invoice_type === 'sale' ? 'ticket_invoices' : 'tour_invoices') . '.show', $inv->id) }}" class="ref-link" target="_blank">{{ $inv->invoice_no }}</a>
                            </td>
                            <td>{{ $inv->invoice_type === 'sale' ? 'Sale (Tickets)' : 'Tour' }}</td>
                            <td>{{ $inv->customer->name ?? '—' }}</td>
                            <td class="text-end">{{ number_format($inv->total_receivable, 2) }}</td>
                            <td class="text-end">{{ number_format($inv->total_payable, 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($inv->total_income, 2) }}</td>
                            <td class="text-end">{{ number_format($inv->outstandingAmount(), 2) }}</td>
                            <td>{{ ucfirst($inv->status) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center text-muted">No invoices in this period.</td></tr>
                        @endforelse
                    </tbody>
                    @if($register->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end">Totals</td>
                            <td class="text-end">{{ number_format($register->sum('total_receivable'), 2) }}</td>
                            <td class="text-end">{{ number_format($register->sum('total_payable'), 2) }}</td>
                            <td class="text-end">{{ number_format($register->sum('total_income'), 2) }}</td>
                            <td class="text-end">{{ number_format($register->sum(fn($i) => $i->outstandingAmount()), 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- ── INCOME BY SERVICE TYPE ──────────────────────────────────── --}}
        <div class="tab-pane fade {{ $tab==='by_service' ? 'show active' : '' }}">
            <form method="GET" class="no-print">
                <input type="hidden" name="tab" value="by_service">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label>From Date</label><input type="date" class="form-control" name="from_date" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To Date</label><input type="date" class="form-control" name="to_date" value="{{ $to }}"></div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                        <button type="button" class="btn btn-danger" onclick="exportPDF('service-table', 'Income by Service Type', '{{ $from }} to {{ $to }}')"><i class="fas fa-file-pdf"></i></button>
                    </div>
                </div>
            </form>
            <div id="service-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr><th>Service Type</th><th>Lines</th><th class="text-end">Receivable</th><th class="text-end">Payable</th><th class="text-end">Income</th></tr>
                    </thead>
                    <tbody>
                        @forelse($byService as $row)
                        <tr>
                            <td>{{ ucfirst($row->service_type) }}</td>
                            <td>{{ $row->lines_count }}</td>
                            <td class="text-end">{{ number_format($row->total_receivable, 2) }}</td>
                            <td class="text-end">{{ number_format($row->total_payable, 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($row->total_income, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted">No data for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── CUSTOMER WISE ──────────────────────────────────── --}}
        <div class="tab-pane fade {{ $tab==='by_customer' ? 'show active' : '' }}">
            <form method="GET" class="no-print">
                <input type="hidden" name="tab" value="by_customer">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label>From Date</label><input type="date" class="form-control" name="from_date" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To Date</label><input type="date" class="form-control" name="to_date" value="{{ $to }}"></div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                        <button type="button" class="btn btn-danger" onclick="exportPDF('customer-table', 'Sales — Customer Wise', '{{ $from }} to {{ $to }}')"><i class="fas fa-file-pdf"></i></button>
                    </div>
                </div>
            </form>
            <div id="customer-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr><th>Customer</th><th>Invoices</th><th class="text-end">Total Receivable</th><th class="text-end">Total Income</th><th class="text-end">Outstanding</th></tr>
                    </thead>
                    <tbody>
                        @forelse($byCustomer as $row)
                        <tr>
                            <td>{{ $row->customer }}</td>
                            <td>{{ $row->invoices_count }}</td>
                            <td class="text-end">{{ number_format($row->total_receivable, 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($row->total_income, 2) }}</td>
                            <td class="text-end">{{ number_format($row->total_outstanding, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted">No data for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

@include('reports._export_pdf_script')
@endsection
