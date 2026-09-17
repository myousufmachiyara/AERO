@extends('layouts.app')

@section('title', 'Print — Complaint')

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header"><h2 class="card-title">Vendor Complaint — {{ $complaint->supplier->name }}</h2></header>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Service:</strong> {{ ucfirst($complaint->service_type) }}</div>
                    <div class="col-md-3"><strong>Date:</strong> {{ $complaint->complaint_date->format('d/m/Y') }}</div>
                    <div class="col-md-3"><strong>Severity:</strong> {{ ucfirst($complaint->severity) }}</div>
                    <div class="col-md-3"><strong>Status:</strong> {{ ucfirst($complaint->status) }}</div>
                </div>
                <p><strong>Description:</strong> {{ $complaint->description }}</p>
                @if($complaint->resolution_notes)<p><strong>Resolution Notes:</strong> {{ $complaint->resolution_notes }}</p>@endif
                <button class="btn btn-primary no-print" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
            </div>
        </section>
    </div>
</div>
@endsection
