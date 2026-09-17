@extends('layouts.app')

@section('title', 'Print — ' . $package->name)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header"><h2 class="card-title">Package</h2></header>
            <div class="card-body">
                <h3>{{ $package->name }}</h3>
                <p class="text-muted">{{ ucfirst($package->category) }} &middot; {{ $package->duration_days ? $package->duration_days.' days' : '' }} &middot; {{ $package->currency }} {{ number_format($package->base_price, 2) }}</p>
                @if($package->inclusions)<p>{{ $package->inclusions }}</p>@endif
                <table class="table table-bordered">
                    <thead><tr><th>Type</th><th>Reference</th><th>Qty</th></tr></thead>
                    <tbody>
                        @foreach($package->services as $s)
                        <tr><td>{{ ucfirst($s->service_type) }}</td><td>{{ $s->label() }}</td><td>{{ $s->qty }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
                <button class="btn btn-primary no-print" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
            </div>
        </section>
    </div>
</div>
@endsection
