@extends('layouts.app')

@section('title', 'Sale Invoice (Tickets)')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">Sale Invoice (Tickets)</h2>
                @can('ticket_invoices.create')
                <a href="{{ route('ticket_invoices.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Sale Invoice
                </a>
                @endcan
            </header>

            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <form method="GET" class="row mb-3 g-2">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Search invoice #, ticket #, PNR, customer..." value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2">
                        <select name="customer_id" class="form-control">
                            <option value="">All Customers</option>
                            @foreach($customers as $c)
                            <option value="{{ $c->id }}" @selected(request('customer_id') == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-control">
                            <option value="all">All Statuses</option>
                            @foreach($statuses as $s)
                            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-default w-100" type="submit"><i class="fas fa-search"></i> Filter</button>
                    </div>
                </form>

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Tickets</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->invoice_no }}</td>
                                <td>{{ $invoice->invoice_date->format('d/m/Y') }}</td>
                                <td>{{ $invoice->customer->name ?? '—' }}</td>
                                <td>{{ $invoice->lines_count }}</td>
                                <td>{{ number_format($invoice->total_amount, 2) }}</td>
                                <td>
                                    <span class="badge {{ $invoice->status === 'posted' ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                </td>
                                <td>
                                    @can('ticket_invoices.index')
                                    <a href="{{ route('ticket_invoices.show', $invoice->id) }}" class="text-info" title="View"><i class="fa fa-eye"></i></a>
                                    @endcan
                                    @can('ticket_invoices.edit')
                                    @if($invoice->status === 'pending')
                                    <a href="{{ route('ticket_invoices.edit', $invoice->id) }}" class="text-primary" title="Edit"><i class="fa fa-edit"></i></a>
                                    @endif
                                    @endcan
                                    @can('ticket_invoices.print')
                                    <a href="{{ route('ticket_invoices.print', $invoice->id) }}" class="text-secondary" title="Print" target="_blank"><i class="fa fa-print"></i></a>
                                    @endcan
                                    @can('ticket_invoices.delete')
                                    @if($invoice->status === 'pending')
                                    <form action="{{ route('ticket_invoices.destroy', $invoice->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <a href="javascript:void(0)" onclick="if(confirm('Are you sure?')) this.closest('form').submit();" class="text-danger" title="Delete"><i class="fa fa-trash-alt"></i></a>
                                    </form>
                                    @endif
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted">No ticket sale invoices found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $invoices->links() }}</div>
            </div>
        </section>
    </div>
</div>
@endsection