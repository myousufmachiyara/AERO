@extends('layouts.app')

@section('title', 'Complaint — ' . $complaint->supplier->name)

@section('content')
@php($sbadge = ['open' => 'bg-danger', 'resolved' => 'bg-success', 'dismissed' => 'bg-secondary'][$complaint->status] ?? 'bg-secondary')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">
                    Complaint — {{ $complaint->supplier->name }}
                    <span class="badge {{ $sbadge }}">{{ ucfirst($complaint->status) }}</span>
                </h2>
                <div>
                    @can('vendor_complaints.print')
                    <a href="{{ route('vendor_complaints.print', $complaint->id) }}" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                    @endcan
                    @can('vendor_complaints.edit')
                    <a href="{{ route('vendor_complaints.edit', $complaint->id) }}" class="btn btn-primary"><i class="fa fa-edit"></i> Edit</a>
                    @endcan
                </div>
            </header>
            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                <div class="row">
                    <div class="col-md-3 mb-2"><strong>Supplier:</strong> <a href="{{ route('suppliers.show', $complaint->supplier_id) }}">{{ $complaint->supplier->name }}</a></div>
                    <div class="col-md-2 mb-2"><strong>Service:</strong> {{ ucfirst($complaint->service_type) }}</div>
                    <div class="col-md-2 mb-2"><strong>Date:</strong> {{ $complaint->complaint_date->format('d/m/Y') }}</div>
                    <div class="col-md-2 mb-2"><strong>Severity:</strong> {{ ucfirst($complaint->severity) }}</div>
                    <div class="col-md-3 mb-2"><strong>Customer:</strong> {{ $complaint->customer->name ?? '—' }}</div>
                    <div class="col-md-3 mb-2"><strong>Reference:</strong> {{ $complaint->reference ?: '—' }}</div>
                    <div class="col-md-3 mb-2"><strong>Logged By:</strong> {{ $complaint->creator->name ?? '—' }}</div>
                </div>
                <hr>
                <strong>Description:</strong>
                <p>{{ $complaint->description }}</p>
                @if($complaint->resolution_notes)
                <strong>Resolution Notes:</strong>
                <p>{{ $complaint->resolution_notes }}</p>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection
