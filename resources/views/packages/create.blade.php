@extends('layouts.app')

@section('title', 'Add Package')

@section('content')
<div class="row">
    <div class="col-12">
        <form action="{{ route('packages.store') }}" method="POST" onkeydown="return event.key != 'Enter';">
            @csrf
            <section class="card">
                <header class="card-header">
                    <h2 class="card-title">Add Package</h2>
                    @if ($errors->any())<div class="alert alert-danger mt-2"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                </header>
                <div class="card-body">
                    @include('packages._form', ['categories' => $categories, 'serviceTypes' => $serviceTypes, 'hotelRooms' => $hotelRooms, 'vehicles' => $vehicles, 'visaTypes' => $visaTypes, 'services' => $services])
                </div>
                <footer class="card-footer text-end">
                    <a href="{{ route('packages.index') }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Package</button>
                </footer>
            </section>
        </form>
    </div>
</div>
@endsection
