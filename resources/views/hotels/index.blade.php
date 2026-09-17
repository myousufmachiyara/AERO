@extends('layouts.app')

@section('title', 'Hotels')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">Hotels</h2>
                @can('hotels.create')
                <a href="{{ route('hotels.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Add Hotel</a>
                @endcan
            </header>
            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

                <form method="GET" class="row mb-3 g-2">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Search name or city..." value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-default w-100" type="submit"><i class="fas fa-search"></i> Filter</button>
                    </div>
                </form>

                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>City</th>
                                <th>Stars</th>
                                <th>Supplier</th>
                                <th>Room Types</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($hotels as $hotel)
                            <tr>
                                <td>{{ $hotel->name }}</td>
                                <td>{{ $hotel->city }}</td>
                                <td>{{ $hotel->star_rating ? str_repeat('★', $hotel->star_rating) : '—' }}</td>
                                <td>{{ $hotel->supplier->name ?? '—' }}</td>
                                <td>{{ $hotel->rooms_count }}</td>
                                <td><span class="badge {{ $hotel->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $hotel->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td>
                                    @can('hotels.index')
                                    <a href="{{ route('hotels.show', $hotel->id) }}" class="text-info" title="View"><i class="fa fa-eye"></i></a>
                                    @endcan
                                    @can('hotels.edit')
                                    <a href="{{ route('hotels.edit', $hotel->id) }}" class="text-primary" title="Edit"><i class="fa fa-edit"></i></a>
                                    @endcan
                                    @can('hotels.delete')
                                    <form action="{{ route('hotels.destroy', $hotel->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <a href="javascript:void(0)" onclick="if(confirm('Are you sure?')) this.closest('form').submit();" class="text-danger" title="Delete"><i class="fa fa-trash-alt"></i></a>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted">No hotels found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $hotels->links() }}</div>
            </div>
        </section>
    </div>
</div>
@endsection
