@extends('layouts.app')

@section('title', 'Print — ' . $template->name)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header"><h2 class="card-title">Charge Template — Rate Card</h2></header>
            <div class="card-body">
                <h3>{{ $template->name }}</h3>
                <p class="text-muted">{{ ucfirst($template->service_category) }} &middot; effective {{ $template->effective_date->format('d/m/Y') }} &middot; {{ $template->default_currency }} @ {{ $template->default_exchange_rate }}</p>
                <table class="table table-bordered">
                    <thead><tr><th>Charge Type</th><th>Calculation</th><th>Value</th></tr></thead>
                    <tbody>
                        @foreach($template->items as $item)
                        <tr>
                            <td>{{ $item->chargeType->name ?? '—' }} ({{ $item->chargeType->code ?? '' }})</td>
                            <td>{{ ucfirst($item->chargeType->calculation_type ?? '') }}</td>
                            <td>{{ $item->chargeType->calculation_type === 'percentage' ? $item->value.'%' : number_format($item->value, 2) }}</td>
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
