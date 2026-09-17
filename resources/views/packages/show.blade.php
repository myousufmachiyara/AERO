@extends('layouts.app')

@section('title', $package->name)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">{{ $package->name }} <small class="text-muted">{{ ucfirst($package->category) }}</small></h2>
                <div>
                    @can('packages.print')
                    <a href="{{ route('packages.print', $package->id) }}" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                    @endcan
                    @can('packages.edit')
                    <a href="{{ route('packages.edit', $package->id) }}" class="btn btn-primary"><i class="fa fa-edit"></i> Edit</a>
                    @endcan
                </div>
            </header>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2"><strong>Duration:</strong> {{ $package->duration_days ? $package->duration_days.' days' : '—' }}</div>
                    <div class="col-md-3 mb-2"><strong>Base Price:</strong> {{ $package->currency }} {{ number_format($package->base_price, 2) }}</div>
                    <div class="col-md-3 mb-2"><strong>Status:</strong> <span class="badge {{ $package->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $package->is_active ? 'Active' : 'Inactive' }}</span></div>
                </div>
                @if($package->inclusions)
                <hr><strong>Inclusions:</strong>
                <p>{{ $package->inclusions }}</p>
                @endif
                <hr>
                <h5>Bundled Services</h5>
                <table class="table table-bordered table-sm">
                    <thead><tr><th>Type</th><th>Reference</th><th>Qty</th><th>Notes</th></tr></thead>
                    <tbody>
                        @forelse($package->services as $s)
                        <tr>
                            <td>{{ ucfirst($s->service_type) }}</td>
                            <td>{{ $s->label() }}</td>
                            <td>{{ $s->qty }}</td>
                            <td>{{ $s->notes }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted">No services bundled yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
