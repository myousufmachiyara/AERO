@extends('layouts.app')

@section('title', $template->name)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">{{ $template->name }}</h2>
                <div>
                    @can('charge_templates.print')
                    <a href="{{ route('charge_templates.print', $template->id) }}" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                    @endcan
                    @can('charge_templates.edit')
                    <a href="{{ route('charge_templates.edit', $template->id) }}" class="btn btn-primary"><i class="fa fa-edit"></i> Edit</a>
                    @endcan
                </div>
            </header>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2"><strong>Category:</strong> {{ ucfirst($template->service_category) }}</div>
                    <div class="col-md-3 mb-2"><strong>Effective Date:</strong> {{ $template->effective_date->format('d/m/Y') }}</div>
                    <div class="col-md-3 mb-2"><strong>Currency:</strong> {{ $template->default_currency }}</div>
                    <div class="col-md-3 mb-2"><strong>Default Exch. Rate:</strong> {{ $template->default_exchange_rate }}</div>
                </div>
                <hr>
                <h5>Default Charges</h5>
                <table class="table table-bordered table-sm">
                    <thead><tr><th>Charge Type</th><th>Calculation</th><th>Value</th></tr></thead>
                    <tbody>
                        @forelse($template->items as $item)
                        <tr>
                            <td>{{ $item->chargeType->name ?? '—' }} ({{ $item->chargeType->code ?? '' }})</td>
                            <td>{{ ucfirst($item->chargeType->calculation_type ?? '') }}</td>
                            <td>{{ $item->chargeType->calculation_type === 'percentage' ? $item->value.'%' : number_format($item->value, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted">No charges configured.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
