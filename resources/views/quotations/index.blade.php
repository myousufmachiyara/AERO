@extends('layouts.app')

@section('title', 'Quotations')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">Quotations</h2>
                @can('quotations.create')
                <a href="{{ route('quotations.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> New Quotation</a>
                @endcan
            </header>
            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

                <form method="GET" class="row mb-3 g-2">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Search quotation # or customer..." value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-control">
                            <option value="all">All Statuses</option>
                            @foreach($statuses as $st)<option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="customer_id" class="form-control select2-js">
                            <option value="">All Customers</option>
                            @foreach($customers as $c)<option value="{{ $c->id }}" @selected(request('customer_id') == $c->id)>{{ $c->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-default w-100" type="submit"><i class="fas fa-search"></i> Filter</button>
                    </div>
                </form>

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr><th>Quotation #</th><th>Date</th><th>Customer</th><th>Package</th><th>Income</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($quotations as $q)
                            <tr>
                                <td>{{ $q->quotation_no }}</td>
                                <td>{{ $q->quotation_date->format('d/m/Y') }}</td>
                                <td>{{ $q->customer->name ?? '—' }}</td>
                                <td>{{ $q->package->name ?? '—' }}</td>
                                <td>{{ number_format($q->total_income, 2) }}</td>
                                <td>
                                    @php($badge = ['draft' => 'bg-secondary', 'sent' => 'bg-info', 'approved' => 'bg-success', 'rejected' => 'bg-danger', 'expired' => 'bg-warning text-dark', 'converted' => 'bg-primary'][$q->status] ?? 'bg-secondary')
                                    <span class="badge {{ $badge }}">{{ ucfirst($q->status) }}</span>
                                </td>
                                <td>
                                    @can('quotations.index')
                                    <a href="{{ route('quotations.show', $q->id) }}" class="text-info" title="View"><i class="fa fa-eye"></i></a>
                                    @endcan
                                    @can('quotations.edit')
                                        @if($q->isEditable())
                                        <a href="{{ route('quotations.edit', $q->id) }}" class="text-primary" title="Edit"><i class="fa fa-edit"></i></a>
                                        @endif
                                    @endcan
                                    @can('quotations.delete')
                                        @if($q->status !== 'converted')
                                        <form action="{{ route('quotations.destroy', $q->id) }}" method="POST" style="display:inline;">
                                            @csrf @method('DELETE')
                                            <a href="javascript:void(0)" onclick="if(confirm('Are you sure?')) this.closest('form').submit();" class="text-danger" title="Delete"><i class="fa fa-trash-alt"></i></a>
                                        </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted">No quotations found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $quotations->links() }}</div>
            </div>
        </section>
    </div>
</div>
@endsection
