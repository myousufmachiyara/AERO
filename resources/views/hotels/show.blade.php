@extends('layouts.app')

@section('title', 'Hotel — ' . $hotel->name)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">{{ $hotel->name }} <small class="text-muted">{{ $hotel->city }}</small></h2>
                <div>
                    @can('hotels.print')
                    <a href="{{ route('hotels.print', $hotel->id) }}" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                    @endcan
                    @can('hotels.edit')
                    <a href="{{ route('hotels.edit', $hotel->id) }}" class="btn btn-primary"><i class="fa fa-edit"></i> Edit</a>
                    @endcan
                </div>
            </header>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2"><strong>Star Rating:</strong> {{ $hotel->star_rating ? str_repeat('★', $hotel->star_rating) : '—' }}</div>
                    <div class="col-md-4 mb-2"><strong>Supplier:</strong> {{ $hotel->supplier->name ?? '—' }}</div>
                    <div class="col-md-4 mb-2"><strong>Contact:</strong> {{ $hotel->contact_no ?: '—' }}</div>
                    <div class="col-md-12 mb-2"><strong>Address:</strong> {{ $hotel->address ?: '—' }}</div>
                </div>
                <hr>
                <h5>Room Types</h5>
                <table class="table table-bordered table-sm">
                    <thead><tr><th>Room Type</th><th>View</th><th>Capacity</th><th>Default Rate</th><th>Currency</th></tr></thead>
                    <tbody>
                        @forelse($hotel->rooms as $room)
                        <tr>
                            <td>{{ $room->room_type }}</td>
                            <td>{{ $room->roomView->name ?? '—' }}</td>
                            <td>{{ $room->capacity }}</td>
                            <td>{{ number_format($room->default_rate, 2) }}</td>
                            <td>{{ $room->currency }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted">No room types added yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <a href="{{ route('hotel_rooms.index', ['hotel_id' => $hotel->id]) }}" class="btn btn-sm btn-default">Manage Room Types</a>
            </div>
        </section>
    </div>
</div>
@endsection
