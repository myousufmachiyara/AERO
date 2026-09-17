@extends('layouts.app')

@section('title', 'Edit Customer')

@section('content')
<div class="row">
    <div class="col-12">
        <form action="{{ route('customers.update', $customer->id) }}" method="POST" onkeydown="return event.key != 'Enter';">
            @csrf
            @method('PUT')
            <section class="card">
                <header class="card-header">
                    <h2 class="card-title">Edit Customer — {{ $customer->code }}</h2>
                    @if ($errors->any())
                        <div class="alert alert-danger mt-2">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    @endif
                </header>
                <div class="card-body">
                    @include('customers._form', ['types' => $types, 'customer' => $customer])
                </div>
                <footer class="card-footer text-end">
                    <a href="{{ route('customers.index') }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Customer</button>
                </footer>
            </section>
        </form>
    </div>
</div>
@endsection
