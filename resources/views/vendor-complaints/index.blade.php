@extends('layouts.app')

@section('title', 'Vendor Complaints')

@section('content')
@if($flaggedSuppliers->isNotEmpty())
<div class="row mb-3">
    <div class="col">
        <div class="alert alert-danger mb-0">
            <strong><i class="fa fa-triangle-exclamation"></i> Vendor Watchlist — {{ $flaggedSuppliers->count() }} flagged for high complaints:</strong>
            @foreach($flaggedSuppliers as $s)
            <a href="{{ route('suppliers.show', $s->id) }}" class="badge bg-dark text-decoration-none me-1">{{ $s->name }}</a>
            @endforeach
        </div>
    </div>
</div>
@endif

@php($sbadge = ['open' => 'bg-danger', 'resolved' => 'bg-success', 'dismissed' => 'bg-secondary'])
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">Vendor Complaints</h2>
                @can('vendor_complaints.create')
                <a href="{{ route('vendor_complaints.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Log Complaint</a>
                @endcan
            </header>
            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

                <form method="GET" class="row mb-3 g-2">
                    <div class="col-md-3">
                        <select name="supplier_id" class="form-control select2-js">
                            <option value="">All Suppliers</option>
                            @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="service_type" class="form-control">
                            <option value="">All Service Types</option>
                            @foreach($serviceTypes as $t)<option value="{{ $t }}" @selected(request('service_type') === $t)>{{ ucfirst($t) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-control">
                            <option value="all">All Statuses</option>
                            @foreach($statuses as $st)<option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-default w-100" type="submit"><i class="fas fa-search"></i> Filter</button>
                    </div>
                </form>

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr><th>Date</th><th>Supplier</th><th>Service</th><th>Customer</th><th>Severity</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($complaints as $c)
                            <tr>
                                <td>{{ $c->complaint_date->format('d/m/Y') }}</td>
                                <td>
                                    <a href="{{ route('suppliers.show', $c->supplier_id) }}">{{ $c->supplier->name ?? '—' }}</a>
                                    @if($c->supplier?->is_flagged)<span class="badge bg-danger">⚠</span>@endif
                                </td>
                                <td>{{ ucfirst($c->service_type) }}</td>
                                <td>{{ $c->customer->name ?? '—' }}</td>
                                <td>{{ ucfirst($c->severity) }}</td>
                                <td><span class="badge {{ $sbadge[$c->status] ?? 'bg-secondary' }}">{{ ucfirst($c->status) }}</span></td>
                                <td>
                                    @can('vendor_complaints.index')
                                    <a href="{{ route('vendor_complaints.show', $c->id) }}" class="text-info" title="View"><i class="fa fa-eye"></i></a>
                                    @endcan
                                    @can('vendor_complaints.edit')
                                    <a href="{{ route('vendor_complaints.edit', $c->id) }}" class="text-primary" title="Edit"><i class="fa fa-edit"></i></a>
                                    @endcan
                                    @can('vendor_complaints.delete')
                                    <form action="{{ route('vendor_complaints.destroy', $c->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <a href="javascript:void(0)" onclick="if(confirm('Are you sure?')) this.closest('form').submit();" class="text-danger" title="Delete"><i class="fa fa-trash-alt"></i></a>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted">No complaints logged.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $complaints->links() }}</div>
            </div>
        </section>
    </div>
</div>
@endsection
