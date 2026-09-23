@extends('layouts.app')

@section('title', 'Add Airline')

@section('content')
<div class="row">
    <div class="col-12">
        <form action="{{ route('airlines.store') }}" method="POST" onkeydown="return event.key != 'Enter';">
            @csrf
            <section class="card">
                <header class="card-header">
                    <h2 class="card-title">Add Airline</h2>
                    @if ($errors->any())
                        <div class="alert alert-danger mt-2">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    @endif
                </header>
                <div class="card-body">
                    @include('airlines._form')
                </div>
                <footer class="card-footer text-end">
                    <a href="{{ route('airlines.index') }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Airline</button>
                </footer>
            </section>
        </form>
    </div>
</div>
@endsection