@extends('layouts.app')

@section('title', 'Suppliers')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">Suppliers</h2>
                @can('suppliers.create')
                <a href="{{ route('suppliers.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Supplier
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
                        <input type="text" name="search" class="form-control" placeholder="Search name, code, contact..." value="{{ request('search') }}">
                    </div>
                    <div class="col-md-3">
                        <select name="type" class="form-control">
                            <option value="all">All Types</option>
                            @foreach(['airline' => 'Airline', 'hotel' => 'Hotel', 'visa_agency' => 'Visa Agency', 'transport' => 'Transport', 'other' => 'Other'] as $val => $label)
                                <option value="{{ $val }}" @selected(request('type') === $val)>{{ $label }}</option>
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
                                <th>Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Contact</th>
                                <th>Credit Limit</th>
                                <th>Payables</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($suppliers as $supplier)
                            <tr>
                                <td>{{ $supplier->code }}</td>
                                <td>
                                    {{ $supplier->name }}
                                    @if($supplier->is_flagged)
                                        <span class="badge bg-danger" title="{{ $supplier->flagged_reason }}">High Complaints</span>
                                    @endif
                                </td>
                                <td>{{ ucwords(str_replace('_', ' ', $supplier->type)) }}</td>
                                <td>{{ $supplier->contact_person }} {{ $supplier->phone ? '('.$supplier->phone.')' : '' }}</td>
                                <td>{{ number_format($supplier->credit_limit, 2) }}</td>
                                <td>{{ number_format($supplier->account->payables ?? 0, 2) }}</td>
                                <td>
                                    <span class="badge {{ $supplier->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $supplier->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    @can('suppliers.index')
                                    <a href="{{ route('suppliers.show', $supplier->id) }}" class="text-info" title="View"><i class="fa fa-eye"></i></a>
                                    @endcan
                                    @can('suppliers.edit')
                                    <a href="{{ route('suppliers.edit', $supplier->id) }}" class="text-primary" title="Edit"><i class="fa fa-edit"></i></a>
                                    @endcan
                                    @can('suppliers.delete')
                                    <form action="{{ route('suppliers.destroy', $supplier->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <a href="javascript:void(0)" onclick="if(confirm('Are you sure?')) this.closest('form').submit();" class="text-danger" title="Delete"><i class="fa fa-trash-alt"></i></a>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center text-muted">No suppliers found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $suppliers->links() }}</div>
            </div>
        </section>
    </div>
</div>
@endsection
