@extends('layouts.app')

@section('title', 'My Commission')

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header">
                <h2 class="card-title">My Commission</h2>
            </header>
            <div class="card-body">
                <form method="GET" class="row mb-3 g-2">
                    <div class="col-md-2">
                        <select name="month" class="form-control">
                            @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}" @selected($month == $m)>{{ \Carbon\Carbon::create()->month($m)->format('F') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="year" class="form-control">
                            @foreach(range(now()->year - 2, now()->year) as $y)
                            <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-default w-100" type="submit"><i class="fas fa-search"></i> View</button>
                    </div>
                </form>

                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Passenger</th>
                            <th>Ticket #</th>
                            <th>Commission</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lines as $line)
                        <tr>
                            <td>
                                @can('ticket_invoices.index')
                                <a href="{{ route('ticket_invoices.show', $line->invoice->id) }}">{{ $line->invoice->invoice_no }}</a>
                                @else
                                {{ $line->invoice->invoice_no }}
                                @endcan
                            </td>
                            <td>{{ $line->invoice->invoice_date->format('d/m/Y') }}</td>
                            <td>{{ $line->invoice->customer->name ?? '—' }}</td>
                            <td>{{ $line->pax_name }}</td>
                            <td>{{ $line->ticket_no }}</td>
                            <td>{{ number_format($line->agent_commission_amount, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted">No commission recorded for this month.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="5" class="text-end">Total</td>
                            <td>{{ number_format($total, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection