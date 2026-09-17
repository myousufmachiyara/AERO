@extends('layouts.app')
@section('title', 'Vendor Report')

@section('content')
<style>@media print { .no-print { display: none !important; } }</style>

<div class="tabs">
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link {{ $tab==='summary' ? 'active' : '' }}" href="{{ route('reports.travel_vendor', ['tab'=>'summary','from_date'=>$from,'to_date'=>$to]) }}">Vendor Summary</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab==='complaints' ? 'active' : '' }}" href="{{ route('reports.travel_vendor', ['tab'=>'complaints','from_date'=>$from,'to_date'=>$to]) }}">Complaint Log</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab==='spend' ? 'active' : '' }}" href="{{ route('reports.travel_vendor', ['tab'=>'spend','from_date'=>$from,'to_date'=>$to]) }}">Spend by Service Type</a></li>
    </ul>

    <div class="tab-content mt-3">

        {{-- ── VENDOR SUMMARY ──────────────────────────────────── --}}
        <div class="tab-pane fade {{ $tab==='summary' ? 'show active' : '' }}">
            <form method="GET" class="no-print">
                <input type="hidden" name="tab" value="summary">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label>From Date</label><input type="date" class="form-control" name="from_date" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To Date</label><input type="date" class="form-control" name="to_date" value="{{ $to }}"></div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                        <button type="button" class="btn btn-danger" onclick="exportPDF('summary-table', 'Vendor Summary', '{{ $from }} to {{ $to }}')"><i class="fas fa-file-pdf"></i></button>
                    </div>
                </div>
            </form>
            <div id="summary-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr><th>Supplier</th><th>Lines Delivered</th><th class="text-end">Total Payable</th><th class="text-end">Total Paid</th><th class="text-end">Outstanding</th><th class="text-center">Complaints (period)</th><th class="text-center">Complaint Rate</th><th class="text-center no-print">Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse($summary as $row)
                        <tr>
                            <td><a href="{{ route('suppliers.show', $row->supplier->id) }}" class="ref-link">{{ $row->supplier->name }}</a></td>
                            <td>{{ $row->lines_count }}</td>
                            <td class="text-end">{{ number_format($row->total_payable, 2) }}</td>
                            <td class="text-end">{{ number_format($row->total_paid, 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($row->outstanding, 2) }}</td>
                            <td class="text-center">{{ $row->complaints_count }}</td>
                            <td class="text-center">{{ $row->complaint_rate !== null ? $row->complaint_rate . '%' : '—' }}</td>
                            <td class="text-center no-print">
                                @if($row->is_flagged)<span class="badge bg-danger">⚠ Flagged</span>@else<span class="badge bg-success">OK</span>@endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center text-muted">No suppliers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── COMPLAINT LOG ──────────────────────────────────── --}}
        <div class="tab-pane fade {{ $tab==='complaints' ? 'show active' : '' }}">
            <form method="GET" class="no-print">
                <input type="hidden" name="tab" value="complaints">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label>From Date</label><input type="date" class="form-control" name="from_date" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To Date</label><input type="date" class="form-control" name="to_date" value="{{ $to }}"></div>
                    <div class="col-md-3">
                        <label>Supplier</label>
                        <select name="supplier_id" class="form-control select2-js">
                            <option value="">All Suppliers</option>
                            @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected($supplierId == $s->id)>{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                        <button type="button" class="btn btn-danger" onclick="exportPDF('complaints-table', 'Vendor Complaint Log', '{{ $from }} to {{ $to }}')"><i class="fas fa-file-pdf"></i></button>
                    </div>
                </div>
            </form>
            <div id="complaints-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr><th>Date</th><th>Supplier</th><th>Service</th><th>Customer</th><th>Severity</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse($complaints as $c)
                        <tr>
                            <td>{{ $c->complaint_date->format('d/m/Y') }}</td>
                            <td>{{ $c->supplier->name ?? '—' }}</td>
                            <td>{{ ucfirst($c->service_type) }}</td>
                            <td>{{ $c->customer->name ?? '—' }}</td>
                            <td>{{ ucfirst($c->severity) }}</td>
                            <td>{{ ucfirst($c->status) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted">No complaints in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── SPEND BY SERVICE TYPE ──────────────────────────────────── --}}
        <div class="tab-pane fade {{ $tab==='spend' ? 'show active' : '' }}">
            <form method="GET" class="no-print">
                <input type="hidden" name="tab" value="spend">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label>From Date</label><input type="date" class="form-control" name="from_date" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To Date</label><input type="date" class="form-control" name="to_date" value="{{ $to }}"></div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                        <button type="button" class="btn btn-danger" onclick="exportPDF('spend-table', 'Vendor Spend by Service Type', '{{ $from }} to {{ $to }}')"><i class="fas fa-file-pdf"></i></button>
                    </div>
                </div>
            </form>
            <div id="spend-table">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr><th>Supplier</th><th>Service Type</th><th>Lines</th><th class="text-end">Total Payable</th></tr>
                    </thead>
                    <tbody>
                        @forelse($spendByType as $row)
                        <tr>
                            <td>{{ $row->supplier }}</td>
                            <td>{{ ucfirst($row->service_type) }}</td>
                            <td>{{ $row->lines_count }}</td>
                            <td class="text-end">{{ number_format($row->total_payable, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted">No data for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

@include('reports._export_pdf_script')
@endsection
