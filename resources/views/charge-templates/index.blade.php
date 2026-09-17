@extends('layouts.app')

@section('title', 'Charge Templates')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">Charge Templates</h2>
                @can('charge_templates.create')
                <a href="{{ route('charge_templates.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Add Template</a>
                @endcan
            </header>
            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                <p class="text-muted">A dated rate card per service category — pick one on an invoice/quotation line to pre-fill its exchange rate and standard charges (WHT, COM, PSF, taxes, ...) instead of re-typing them.</p>

                <form method="GET" class="row mb-3 g-2">
                    <div class="col-md-3">
                        <select name="service_category" class="form-control" onchange="this.form.submit()">
                            <option value="all">All Categories</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}" @selected(request('service_category') === $cat)>{{ ucfirst($cat) }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0">
                        <thead><tr><th>Name</th><th>Category</th><th>Effective Date</th><th>Default Rate</th><th># Charges</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            @forelse ($templates as $t)
                            <tr>
                                <td>{{ $t->name }}</td>
                                <td>{{ ucfirst($t->service_category) }}</td>
                                <td>{{ $t->effective_date->format('d/m/Y') }}</td>
                                <td>{{ $t->default_currency }} @ {{ $t->default_exchange_rate }}</td>
                                <td>{{ $t->items_count }}</td>
                                <td><span class="badge {{ $t->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $t->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td>
                                    @can('charge_templates.index')
                                    <a href="{{ route('charge_templates.show', $t->id) }}" class="text-info" title="View"><i class="fa fa-eye"></i></a>
                                    @endcan
                                    @can('charge_templates.edit')
                                    <a href="{{ route('charge_templates.edit', $t->id) }}" class="text-primary" title="Edit"><i class="fa fa-edit"></i></a>
                                    @endcan
                                    @can('charge_templates.delete')
                                    <form action="{{ route('charge_templates.destroy', $t->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <a href="javascript:void(0)" onclick="if(confirm('Are you sure?')) this.closest('form').submit();" class="text-danger" title="Delete"><i class="fa fa-trash-alt"></i></a>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted">No charge templates found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
