@extends('layouts.app')

@section('title', 'Travel Payments')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">Travel Payments</h2>
                @can('invoice_payments.create')
                <div>
                    <a href="{{ route('invoice_payments.create', ['direction' => 'receipt']) }}" class="btn btn-success"><i class="fas fa-plus"></i> Receipt from Customer</a>
                    <a href="{{ route('invoice_payments.create', ['direction' => 'payment']) }}" class="btn btn-warning"><i class="fas fa-plus"></i> Payment to Supplier</a>
                </div>
                @endcan
            </header>
            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

                <form method="GET" class="row mb-3 g-2">
                    <div class="col-md-2">
                        <select name="direction" class="form-control">
                            <option value="all">All</option>
                            <option value="receipt" @selected(request('direction') === 'receipt')>Receipts</option>
                            <option value="payment" @selected(request('direction') === 'payment')>Payments</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="customer_id" class="form-control select2-js">
                            <option value="">All Customers</option>
                            @foreach($customers as $c)<option value="{{ $c->id }}" @selected(request('customer_id') == $c->id)>{{ $c->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="supplier_id" class="form-control select2-js">
                            <option value="">All Suppliers</option>
                            @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-default w-100" type="submit"><i class="fas fa-search"></i> Filter</button>
                    </div>
                </form>

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr><th>Date</th><th>Direction</th><th>Party</th><th>Invoice</th><th>Amount</th><th>Mode</th><th>Reference</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $p)
                            <tr>
                                <td>{{ $p->payment_date->format('d/m/Y') }}</td>
                                <td><span class="badge {{ $p->direction === 'receipt' ? 'bg-success' : 'bg-warning text-dark' }}">{{ ucfirst($p->direction) }}</span></td>
                                <td>{{ $p->customer->name ?? $p->supplier->name ?? '—' }}</td>
                                <td>{{ $p->travelInvoice->invoice_no ?? '—' }}</td>
                                <td>{{ number_format($p->amount, 2) }}</td>
                                <td>{{ ucfirst($p->payment_mode) }}</td>
                                <td>{{ $p->reference ?: '—' }}</td>
                                <td>
                                    @can('invoice_payments.index')
                                    <a href="{{ route('invoice_payments.show', $p->id) }}" class="text-info" title="View"><i class="fa fa-eye"></i></a>
                                    @endcan
                                    @can('invoice_payments.delete')
                                    <form action="{{ route('invoice_payments.destroy', $p->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <a href="javascript:void(0)" onclick="if(confirm('Reverse this payment and remove it from the ledger?')) this.closest('form').submit();" class="text-danger" title="Reverse"><i class="fa fa-trash-alt"></i></a>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center text-muted">No payments recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $payments->links() }}</div>
            </div>
        </section>
    </div>
</div>
@endsection
