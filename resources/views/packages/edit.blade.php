@extends('layouts.app')

@section('title', 'Edit Package')

@section('content')
<div class="row">
    <div class="col-12">
        <form action="{{ route('packages.update', $package->id) }}" method="POST" onkeydown="return event.key != 'Enter';">
            @csrf
            @method('PUT')
            <section class="card">
                <header class="card-header">
                    <h2 class="card-title">Edit Package — {{ $package->name }}</h2>
                    @if ($errors->any())<div class="alert alert-danger mt-2"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                </header>
                <div class="card-body">
                    @include('packages._form', ['categories' => $categories, 'serviceTypes' => $serviceTypes, 'hotelRooms' => $hotelRooms, 'vehicles' => $vehicles, 'visaTypes' => $visaTypes, 'services' => $services, 'package' => $package])
                </div>
                <footer class="card-footer text-end">
                    <a href="{{ route('packages.index') }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Package</button>
                </footer>
            </section>
        </form>
    </div>
</div>
@endsection
