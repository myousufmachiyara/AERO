@extends('layouts.app')

@section('title', 'Packages')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">Packages</h2>
                @can('packages.create')
                <a href="{{ route('packages.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Add Package</a>
                @endcan
            </header>
            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

                <form method="GET" class="row mb-3 g-2">
                    <div class="col-md-3">
                        <select name="category" class="form-control" onchange="this.form.submit()">
                            <option value="all">All Categories</option>
                            @foreach($categories as $cat)<option value="{{ $cat }}" @selected(request('category') === $cat)>{{ ucfirst($cat) }}</option>@endforeach
                        </select>
                    </div>
                </form>

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0">
                        <thead><tr><th>Name</th><th>Category</th><th>Duration</th><th>Base Price</th><th># Services</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            @forelse ($packages as $p)
                            <tr>
                                <td>{{ $p->name }}</td>
                                <td>{{ ucfirst($p->category) }}</td>
                                <td>{{ $p->duration_days ? $p->duration_days.' days' : '—' }}</td>
                                <td>{{ $p->currency }} {{ number_format($p->base_price, 2) }}</td>
                                <td>{{ $p->services_count }}</td>
                                <td><span class="badge {{ $p->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $p->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td>
                                    @can('packages.index')
                                    <a href="{{ route('packages.show', $p->id) }}" class="text-info" title="View"><i class="fa fa-eye"></i></a>
                                    @endcan
                                    @can('packages.edit')
                                    <a href="{{ route('packages.edit', $p->id) }}" class="text-primary" title="Edit"><i class="fa fa-edit"></i></a>
                                    @endcan
                                    @can('packages.delete')
                                    <form action="{{ route('packages.destroy', $p->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <a href="javascript:void(0)" onclick="if(confirm('Are you sure?')) this.closest('form').submit();" class="text-danger" title="Delete"><i class="fa fa-trash-alt"></i></a>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted">No packages found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
