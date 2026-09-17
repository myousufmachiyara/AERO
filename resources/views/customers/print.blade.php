@extends('layouts.app')

@section('title', 'Print — ' . $customer->name)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header">
                <h2 class="card-title">Customer Profile</h2>
            </header>
            <div class="card-body">
                <h3>{{ $customer->name }}</h3>
                <p class="text-muted">Code: {{ $customer->code }} &middot; Type: {{ ucfirst($customer->customer_type) }}</p>
                <table class="table table-bordered">
                    <tr><th style="width:220px;">Contact Person</th><td>{{ $customer->contact_person ?: '—' }}</td></tr>
                    <tr><th>Phone</th><td>{{ $customer->phone ?: '—' }}</td></tr>
                    <tr><th>Email</th><td>{{ $customer->email ?: '—' }}</td></tr>
                    <tr><th>Address</th><td>{{ $customer->address ?: '—' }}</td></tr>
                    <tr><th>CNIC/Passport</th><td>{{ $customer->cnic_passport ?: '—' }}</td></tr>
                    <tr><th>Credit Limit</th><td>{{ number_format($customer->credit_limit, 2) }}</td></tr>
                    <tr><th>Credit Days</th><td>{{ $customer->credit_days }}</td></tr>
                    <tr><th>Ledger Account</th><td>{{ $customer->account->account_code ?? '—' }}</td></tr>
                    <tr><th>Current Receivables</th><td>{{ number_format($customer->account->receivables ?? 0, 2) }}</td></tr>
                </table>
                <button class="btn btn-primary no-print" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
            </div>
        </section>
    </div>
</div>
@endsection
