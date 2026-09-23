@extends('layouts.app')

@section('title', 'Airlines')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">Airlines</h2>
                @can('airlines.create')
                <a href="{{ route('airlines.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Airline
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
                        <input type="text" name="search" class="form-control" placeholder="Search name or code..." value="{{ request('search') }}">
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
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($airlines as $airline)
                            <tr>
                                <td>{{ $airline->numeric_code }}</td>
                                <td>{{ $airline->name }}</td>
                                <td>
                                    <span class="badge {{ $airline->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $airline->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    @can('airlines.index')
                                    <a href="{{ route('airlines.show', $airline->id) }}" class="text-info" title="View"><i class="fa fa-eye"></i></a>
                                    @endcan
                                    @can('airlines.edit')
                                    <a href="{{ route('airlines.edit', $airline->id) }}" class="text-primary" title="Edit"><i class="fa fa-edit"></i></a>
                                    @endcan
                                    @can('airlines.delete')
                                    <form action="{{ route('airlines.destroy', $airline->id) }}" method="POST" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <a href="javascript:void(0)" onclick="if(confirm('Are you sure?')) this.closest('form').submit();" class="text-danger" title="Delete"><i class="fa fa-trash-alt"></i></a>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted">No airlines found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $airlines->links() }}</div>
            </div>
        </section>
    </div>
</div>
@endsection