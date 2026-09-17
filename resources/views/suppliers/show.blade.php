@extends('layouts.app')

@section('title', 'Supplier — ' . $supplier->name)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">{{ $supplier->name }} <small class="text-muted">({{ $supplier->code }})</small></h2>
                <div>
                    @can('suppliers.print')
                    <a href="{{ route('suppliers.print', $supplier->id) }}" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                    @endcan
                    @can('suppliers.edit')
                    <a href="{{ route('suppliers.edit', $supplier->id) }}" class="btn btn-primary"><i class="fa fa-edit"></i> Edit</a>
                    @endcan
                </div>
            </header>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2"><strong>Type:</strong> {{ ucwords(str_replace('_',' ',$supplier->type)) }}</div>
                    <div class="col-md-4 mb-2"><strong>Contact Person:</strong> {{ $supplier->contact_person ?: '—' }}</div>
                    <div class="col-md-4 mb-2"><strong>Phone:</strong> {{ $supplier->phone ?: '—' }}</div>
                    <div class="col-md-4 mb-2"><strong>Email:</strong> {{ $supplier->email ?: '—' }}</div>
                    <div class="col-md-4 mb-2"><strong>Address:</strong> {{ $supplier->address ?: '—' }}</div>
                    <div class="col-md-4 mb-2"><strong>License No.:</strong> {{ $supplier->license_no ?: '—' }}</div>
                    <div class="col-md-4 mb-2"><strong>Credit Limit:</strong> {{ number_format($supplier->credit_limit, 2) }}</div>
                    <div class="col-md-4 mb-2"><strong>Credit Days:</strong> {{ $supplier->credit_days }}</div>
                    <div class="col-md-4 mb-2">
                        <strong>Status:</strong>
                        <span class="badge {{ $supplier->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</span>
                        @if($supplier->is_flagged)
                            <span class="badge bg-danger">High Complaints — {{ $supplier->flagged_reason }}</span>
                        @endif
                    </div>
                </div>
                <hr>
                <h5>Ledger Account</h5>
                @if($supplier->account)
                <div class="row">
                    <div class="col-md-4 mb-2"><strong>Account Code:</strong> {{ $supplier->account->account_code }}</div>
                    <div class="col-md-4 mb-2"><strong>Payables:</strong> {{ number_format($supplier->account->payables, 2) }}</div>
                    <div class="col-md-4 mb-2">
                        @can('coa.index')
                        <a href="{{ route('coa.index') }}">View in Chart of Accounts</a>
                        @endcan
                    </div>
                </div>
                @else
                <p class="text-muted">No ledger account linked yet.</p>
                @endif
                @if($supplier->remarks)
                <hr><strong>Remarks:</strong> {{ $supplier->remarks }}
                @endif

                <hr>
                <h5>
                    Complaint History
                    @php($rate = $supplier->complaintRatePercent())
                    <small class="text-muted">({{ $supplier->recentComplaintsCount() }} in last {{ config('travel.complaint_threshold.period_days') }} days, threshold {{ $supplier->effectiveComplaintThreshold() }}{{ $rate !== null ? ' — ' . $rate . '% of delivered lines' : '' }})</small>
                    @can('vendor_complaints.create')
                    <a href="{{ route('vendor_complaints.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-sm btn-outline-danger float-end">+ Log Complaint</a>
                    @endcan
                </h5>
                <table class="table table-bordered table-sm">
                    <thead><tr><th>Date</th><th>Service</th><th>Severity</th><th>Status</th><th>Description</th></tr></thead>
                    <tbody>
                        @forelse($supplier->complaints as $c)
                        <tr>
                            <td>{{ $c->complaint_date->format('d/m/Y') }}</td>
                            <td>{{ ucfirst($c->service_type) }}</td>
                            <td>{{ ucfirst($c->severity) }}</td>
                            <td>
                                @php($sbadge = ['open' => 'bg-danger', 'resolved' => 'bg-success', 'dismissed' => 'bg-secondary'][$c->status] ?? 'bg-secondary')
                                <span class="badge {{ $sbadge }}">{{ ucfirst($c->status) }}</span>
                            </td>
                            <td>{{ \Illuminate\Support\Str::limit($c->description, 80) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted">No complaints logged.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
