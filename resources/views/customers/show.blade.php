@extends('layouts.app')

@section('title', 'Customer — ' . $customer->name)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">{{ $customer->name }} <small class="text-muted">({{ $customer->code }})</small></h2>
                <div>
                    @can('customers.print')
                    <a href="{{ route('customers.print', $customer->id) }}" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                    @endcan
                    @can('customers.edit')
                    <a href="{{ route('customers.edit', $customer->id) }}" class="btn btn-primary"><i class="fa fa-edit"></i> Edit</a>
                    @endcan
                </div>
            </header>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2"><strong>Type:</strong> {{ ucfirst($customer->customer_type) }}</div>
                    <div class="col-md-4 mb-2"><strong>Contact Person:</strong> {{ $customer->contact_person ?: '—' }}</div>
                    <div class="col-md-4 mb-2"><strong>Phone:</strong> {{ $customer->phone ?: '—' }}</div>
                    <div class="col-md-4 mb-2"><strong>Email:</strong> {{ $customer->email ?: '—' }}</div>
                    <div class="col-md-4 mb-2"><strong>Address:</strong> {{ $customer->address ?: '—' }}</div>
                    <div class="col-md-4 mb-2"><strong>CNIC/Passport:</strong> {{ $customer->cnic_passport ?: '—' }}</div>
                    <div class="col-md-4 mb-2"><strong>Credit Limit:</strong> {{ number_format($customer->credit_limit, 2) }}</div>
                    <div class="col-md-4 mb-2"><strong>Credit Days:</strong> {{ $customer->credit_days }}</div>
                    <div class="col-md-4 mb-2">
                        <strong>Status:</strong>
                        <span class="badge {{ $customer->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span>
                    </div>
                </div>
                <hr>
                <h5>Ledger Account</h5>
                @if($customer->account)
                <div class="row">
                    <div class="col-md-4 mb-2"><strong>Account Code:</strong> {{ $customer->account->account_code }}</div>
                    <div class="col-md-4 mb-2"><strong>Receivables:</strong> {{ number_format($customer->account->receivables, 2) }}</div>
                    <div class="col-md-4 mb-2">
                        @can('coa.index')
                        <a href="{{ route('coa.index') }}">View in Chart of Accounts</a>
                        @endcan
                    </div>
                </div>
                @else
                <p class="text-muted">No ledger account linked yet.</p>
                @endif
                @if($customer->remarks)
                <hr><strong>Remarks:</strong> {{ $customer->remarks }}
                @endif
            </div>
        </section>
    </div>
</div>
@endsection
