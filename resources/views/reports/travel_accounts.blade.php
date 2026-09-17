@extends('layouts.app')
@section('title', 'Travel Accounting Report')

@section('content')
<style>@media print { .no-print { display: none !important; } }</style>

<div class="tabs">
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link {{ $tab==='receivables' ? 'active' : '' }}" href="{{ route('reports.travel_accounts', ['tab'=>'receivables','from_date'=>$from,'to_date'=>$to]) }}">Outstanding Receivables</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab==='payables' ? 'active' : '' }}" href="{{ route('reports.travel_accounts', ['tab'=>'payables','from_date'=>$from,'to_date'=>$to]) }}">Outstanding Payables</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab==='payments_register' ? 'active' : '' }}" href="{{ route('reports.travel_accounts', ['tab'=>'payments_register','from_date'=>$from,'to_date'=>$to]) }}">Payments Register</a></li>
    </ul>

    <div class="tab-content mt-3">

        {{-- ── OUTSTANDING RECEIVABLES ──────────────────────────────────── --}}
        <div class="tab-pane fade {{ $tab==='receivables' ? 'show active' : '' }}">
            <form method="GET" class="no-print">
                <input type="hidden" name="tab" value="receivables">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label>From Date</label><input type="date" class="form-control" name="from_date" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To Date</label><input type="date" class="form-control" name="to_date" value="{{ $to }}"></div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                        <button type="button" class="btn btn-danger" onclick="exportPDF('recv-table', 'Outstanding Receivables', '{{ $from }} to {{ $to }}')"><i class="fas fa-file-pdf"></i></button>
                    </div>
                </div>
            </form>
            <div id="recv-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr><th>Invoice #</th><th>Date</th><th>Customer</th><th class="text-end">Total Receivable</th><th class="text-end">Outstanding</th></tr>
                    </thead>
                    <tbody>
                        @forelse($receivables as $row)
                        <tr>
                            <td><a href="{{ route(($row->invoice->invoice_type === 'sale' ? 'ticket_invoices' : 'tour_invoices') . '.show', $row->invoice->id) }}" class="ref-link" target="_blank">{{ $row->invoice->invoice_no }}</a></td>
                            <td>{{ $row->invoice->invoice_date->format('d/m/Y') }}</td>
                            <td>{{ $row->invoice->customer->name ?? '—' }}</td>
                            <td class="text-end">{{ number_format($row->invoice->total_receivable, 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($row->outstanding, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted">Nothing outstanding in this period.</td></tr>
                        @endforelse
                    </tbody>
                    @if($receivables->isNotEmpty())
                    <tfoot><tr><td colspan="4" class="text-end">Total Outstanding</td><td class="text-end">{{ number_format($receivables->sum('outstanding'), 2) }}</td></tr></tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- ── OUTSTANDING PAYABLES ──────────────────────────────────── --}}
        <div class="tab-pane fade {{ $tab==='payables' ? 'show active' : '' }}">
            <form method="GET" class="no-print">
                <input type="hidden" name="tab" value="payables">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label>From Date</label><input type="date" class="form-control" name="from_date" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To Date</label><input type="date" class="form-control" name="to_date" value="{{ $to }}"></div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                        <button type="button" class="btn btn-danger" onclick="exportPDF('pay-table', 'Outstanding Payables', '{{ $from }} to {{ $to }}')"><i class="fas fa-file-pdf"></i></button>
                    </div>
                </div>
            </form>
            <div id="pay-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr><th>Supplier</th><th class="text-end">Total Payable</th><th class="text-end">Total Paid</th><th class="text-end">Outstanding</th></tr>
                    </thead>
                    <tbody>
                        @forelse($payables as $row)
                        <tr>
                            <td><a href="{{ route('suppliers.show', $row->supplier->id) }}" class="ref-link">{{ $row->supplier->name }}</a></td>
                            <td class="text-end">{{ number_format($row->total_payable, 2) }}</td>
                            <td class="text-end">{{ number_format($row->total_paid, 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($row->outstanding, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted">Nothing outstanding in this period.</td></tr>
                        @endforelse
                    </tbody>
                    @if($payables->isNotEmpty())
                    <tfoot><tr><td colspan="3" class="text-end">Total Outstanding</td><td class="text-end">{{ number_format($payables->sum('outstanding'), 2) }}</td></tr></tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- ── PAYMENTS REGISTER ──────────────────────────────────── --}}
        <div class="tab-pane fade {{ $tab==='payments_register' ? 'show active' : '' }}">
            <form method="GET" class="no-print">
                <input type="hidden" name="tab" value="payments_register">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label>From Date</label><input type="date" class="form-control" name="from_date" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To Date</label><input type="date" class="form-control" name="to_date" value="{{ $to }}"></div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                        <button type="button" class="btn btn-danger" onclick="exportPDF('pmt-table', 'Payments Register', '{{ $from }} to {{ $to }}')"><i class="fas fa-file-pdf"></i></button>
                    </div>
                </div>
            </form>
            <div id="pmt-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr><th>Date</th><th>Direction</th><th>Party</th><th>Invoice</th><th class="text-end">Amount</th><th>Mode</th></tr>
                    </thead>
                    <tbody>
                        @forelse($paymentsRegister as $p)
                        <tr>
                            <td>{{ $p->payment_date->format('d/m/Y') }}</td>
                            <td>{{ ucfirst($p->direction) }}</td>
                            <td>{{ $p->customer->name ?? $p->supplier->name ?? '—' }}</td>
                            <td>{{ $p->travelInvoice->invoice_no ?? '—' }}</td>
                            <td class="text-end">{{ number_format($p->amount, 2) }}</td>
                            <td>{{ ucfirst($p->payment_mode) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted">No payments in this period.</td></tr>
                        @endforelse
                    </tbody>
                    @if($paymentsRegister->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end">Receipts</td>
                            <td class="text-end">{{ number_format($paymentsRegister->where('direction', 'receipt')->sum('amount'), 2) }}</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end">Payments</td>
                            <td class="text-end">{{ number_format($paymentsRegister->where('direction', 'payment')->sum('amount'), 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

    </div>
</div>

@include('reports._export_pdf_script')
@endsection
