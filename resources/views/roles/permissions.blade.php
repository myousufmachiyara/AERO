@extends('layouts.app')

@section('title', 'All Permissions')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header">
                <h2 class="card-title">All Permissions</h2>
            </header>
            <div class="card-body">
                <p class="text-muted">Permissions are granted to roles from <a href="{{ route('roles.index') }}">Roles &amp; Permissions</a>. This is a read-only list of every permission currently registered in the system.</p>
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Permission</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($permissions as $permission)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $permission->name }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
