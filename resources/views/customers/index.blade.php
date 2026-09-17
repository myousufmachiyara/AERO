@extends('layouts.app')

@section('title', 'Customers')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">Customers</h2>
                @can('customers.create')
                <a href="{{ route('customers.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Customer
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
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="Search name, code, CNIC/passport..." value="{{ request('search') }}">
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
                                <th>Receivables</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($customers as $customer)
                            <tr>
                                <td>{{ $customer->code }}</td>
                                <td>{{ $customer->name }}</td>
                                <td>{{ ucfirst($customer->customer_type) }}</td>
                                <td>{{ $customer->phone }}</td>
                                <td>{{ number_format($customer->credit_limit, 2) }}</td>
                                <td>{{ number_format($customer->account->receivables ?? 0, 2) }}</td>
                                <td>
                                    <span class="badge {{ $customer->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $customer->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    @can('customers.index')
                                    <a href="{{ route('customers.show', $customer->id) }}" class="text-info" title="View"><i class="fa fa-eye"></i></a>
                                    @endcan
                                    @can('customers.edit')
                                    <a href="{{ route('customers.edit', $customer->id) }}" class="text-primary" title="Edit"><i class="fa fa-edit"></i></a>
                                    @endcan
                                    @can('customers.delete')
                                    <form action="{{ route('customers.destroy', $customer->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <a href="javascript:void(0)" onclick="if(confirm('Are you sure?')) this.closest('form').submit();" class="text-danger" title="Delete"><i class="fa fa-trash-alt"></i></a>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center text-muted">No customers found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $customers->links() }}</div>
            </div>
        </section>
    </div>
</div>
@endsection
