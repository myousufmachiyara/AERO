@extends('layouts.app')

@section('title', 'Print — ' . $supplier->name)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header">
                <h2 class="card-title">Supplier Profile</h2>
            </header>
            <div class="card-body">
                <h3>{{ $supplier->name }}</h3>
                <p class="text-muted">Code: {{ $supplier->code }} &middot; Type: {{ ucwords(str_replace('_',' ',$supplier->type)) }}</p>
                <table class="table table-bordered">
                    <tr><th style="width:220px;">Contact Person</th><td>{{ $supplier->contact_person ?: '—' }}</td></tr>
                    <tr><th>Phone</th><td>{{ $supplier->phone ?: '—' }}</td></tr>
                    <tr><th>Email</th><td>{{ $supplier->email ?: '—' }}</td></tr>
                    <tr><th>Address</th><td>{{ $supplier->address ?: '—' }}</td></tr>
                    <tr><th>License No.</th><td>{{ $supplier->license_no ?: '—' }}</td></tr>
                    <tr><th>NTN</th><td>{{ $supplier->ntn ?: '—' }}</td></tr>
                    <tr><th>Credit Limit</th><td>{{ number_format($supplier->credit_limit, 2) }}</td></tr>
                    <tr><th>Credit Days</th><td>{{ $supplier->credit_days }}</td></tr>
                    <tr><th>Ledger Account</th><td>{{ $supplier->account->account_code ?? '—' }}</td></tr>
                    <tr><th>Current Payables</th><td>{{ number_format($supplier->account->payables ?? 0, 2) }}</td></tr>
                </table>
                <button class="btn btn-primary no-print" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
            </div>
        </section>
    </div>
</div>
@endsection
