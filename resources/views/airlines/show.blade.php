@extends('layouts.app')

@section('title', 'Airline — ' . $airline->name)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">{{ $airline->name }} <small class="text-muted">({{ $airline->numeric_code }})</small></h2>
                <div>
                    @can('airlines.print')
                    <a href="{{ route('airlines.print', $airline->id) }}" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                    @endcan
                    @can('airlines.edit')
                    <a href="{{ route('airlines.edit', $airline->id) }}" class="btn btn-primary"><i class="fa fa-edit"></i> Edit</a>
                    @endcan
                </div>
            </header>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2"><strong>Numeric Code:</strong> {{ $airline->numeric_code }}</div>
                    <div class="col-md-4 mb-2">
                        <strong>Status:</strong>
                        <span class="badge {{ $airline->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $airline->is_active ? 'Active' : 'Inactive' }}</span>
                    </div>
                </div>
                <hr>
                <h5>Ledger Account</h5>
                @if($airline->account)
                <div class="row">
                    <div class="col-md-4 mb-2"><strong>Account Code:</strong> {{ $airline->account->account_code }}</div>
                    <div class="col-md-4 mb-2"><strong>Receivable (Commission):</strong> {{ number_format($airline->account->receivables, 2) }}</div>
                    <div class="col-md-4 mb-2">
                        @can('coa.index')
                        <a href="{{ route('coa.index') }}">View in Chart of Accounts</a>
                        @endcan
                    </div>
                </div>
                @else
                <p class="text-muted">No ledger account linked yet.</p>
                @endif
                @if($airline->remarks)
                <hr><strong>Remarks:</strong> {{ $airline->remarks }}
                @endif
            </div>
        </section>
    </div>
</div>
@endsection