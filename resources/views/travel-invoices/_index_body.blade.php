@php($badge = ['draft' => 'bg-secondary', 'confirmed' => 'bg-success', 'cancelled' => 'bg-danger'])
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">{{ $pageTitle }}</h2>
                @can($routeUri . '.create')
                <a href="{{ route($routeUri . '.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ $newLabel }}</a>
                @endcan
            </header>
            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

                <form method="GET" class="row mb-3 g-2">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Search invoice # or customer..." value="{{ request('search') }}">
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
                            <tr><th>Invoice #</th><th>Date</th><th>Customer</th><th>Quotation</th><th>Income</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($invoices as $inv)
                            <tr>
                                <td>{{ $inv->invoice_no }}</td>
                                <td>{{ $inv->invoice_date->format('d/m/Y') }}</td>
                                <td>{{ $inv->customer->name ?? '—' }}</td>
                                <td>{{ $inv->quotation->quotation_no ?? '—' }}</td>
                                <td>{{ number_format($inv->total_income, 2) }}</td>
                                <td><span class="badge {{ $badge[$inv->status] ?? 'bg-secondary' }}">{{ ucfirst($inv->status) }}</span></td>
                                <td>
                                    @can($routeUri . '.index')
                                    <a href="{{ route($routeUri . '.show', $inv->id) }}" class="text-info" title="View"><i class="fa fa-eye"></i></a>
                                    @endcan
                                    @can($routeUri . '.edit')
                                        @if($inv->isEditable())
                                        <a href="{{ route($routeUri . '.edit', $inv->id) }}" class="text-primary" title="Edit"><i class="fa fa-edit"></i></a>
                                        @endif
                                    @endcan
                                    @can($routeUri . '.delete')
                                        @if($inv->status !== 'confirmed')
                                        <form action="{{ route($routeUri . '.destroy', $inv->id) }}" method="POST" style="display:inline;">
                                            @csrf @method('DELETE')
                                            <a href="javascript:void(0)" onclick="if(confirm('Are you sure?')) this.closest('form').submit();" class="text-danger" title="Delete"><i class="fa fa-trash-alt"></i></a>
                                        </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted">No invoices found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $invoices->links() }}</div>
            </div>
        </section>
    </div>
</div>
