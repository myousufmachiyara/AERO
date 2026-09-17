@extends('layouts.app')

@section('title', 'Print — ' . $hotel->name)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header"><h2 class="card-title">Hotel Profile</h2></header>
            <div class="card-body">
                <h3>{{ $hotel->name }}</h3>
                <p class="text-muted">{{ $hotel->city }} &middot; {{ $hotel->star_rating ? str_repeat('★', $hotel->star_rating) : 'Unrated' }}</p>
                <table class="table table-bordered">
                    <tr><th style="width:220px;">Supplier</th><td>{{ $hotel->supplier->name ?? '—' }}</td></tr>
                    <tr><th>Address</th><td>{{ $hotel->address ?: '—' }}</td></tr>
                    <tr><th>Contact</th><td>{{ $hotel->contact_no ?: '—' }}</td></tr>
                </table>
                <h5>Room Types</h5>
                <table class="table table-bordered">
                    <thead><tr><th>Room Type</th><th>View</th><th>Capacity</th><th>Default Rate</th></tr></thead>
                    <tbody>
                        @foreach($hotel->rooms as $room)
                        <tr>
                            <td>{{ $room->room_type }}</td>
                            <td>{{ $room->roomView->name ?? '—' }}</td>
                            <td>{{ $room->capacity }}</td>
                            <td>{{ $room->currency }} {{ number_format($room->default_rate, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <button class="btn btn-primary no-print" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
            </div>
        </section>
    </div>
</div>
@endsection
