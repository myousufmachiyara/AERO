@extends('layouts.app')

@section('title', $quotation->quotation_no)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">
                    {{ $quotation->quotation_no }}
                    @php($badge = ['draft' => 'bg-secondary', 'sent' => 'bg-info', 'approved' => 'bg-success', 'rejected' => 'bg-danger', 'expired' => 'bg-warning text-dark', 'converted' => 'bg-primary'][$quotation->status] ?? 'bg-secondary')
                    <span class="badge {{ $badge }}">{{ ucfirst($quotation->status) }}</span>
                </h2>
                <div>
                    @can('quotations.print')
                    <a href="{{ route('quotations.print', $quotation->id) }}" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                    @endcan
                    @can('quotations.edit')
                        @if($quotation->isEditable())
                        <a href="{{ route('quotations.edit', $quotation->id) }}" class="btn btn-primary"><i class="fa fa-edit"></i> Edit</a>
                        @endif
                    @endcan
                </div>
            </header>
            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

                <div class="row">
                    <div class="col-md-3 mb-2"><strong>Date:</strong> {{ $quotation->quotation_date->format('d/m/Y') }}</div>
                    <div class="col-md-3 mb-2"><strong>Valid Until:</strong> {{ optional($quotation->valid_until)->format('d/m/Y') ?: '—' }}</div>
                    <div class="col-md-3 mb-2"><strong>Customer:</strong> {{ $quotation->customer->name ?? '—' }}</div>
                    <div class="col-md-3 mb-2"><strong>Package:</strong> {{ $quotation->package->name ?? '—' }}</div>
                    <div class="col-md-3 mb-2"><strong>Visit Type:</strong> {{ $quotation->visit_type ?: '—' }}</div>
                    <div class="col-md-3 mb-2"><strong>Prepared By:</strong> {{ $quotation->creator->name ?? '—' }}</div>
                </div>

                @can('quotations.edit')
                <hr>
                <div class="d-flex gap-2 flex-wrap">
                    @if($quotation->status === 'draft')
                    <form action="{{ route('quotations.status', $quotation->id) }}" method="POST">@csrf @method('PATCH')<input type="hidden" name="status" value="sent">
                        <button class="btn btn-sm btn-info text-white">Mark as Sent</button>
                    </form>
                    @endif
                    @if(in_array($quotation->status, ['draft', 'sent']))
                    <form action="{{ route('quotations.status', $quotation->id) }}" method="POST">@csrf @method('PATCH')<input type="hidden" name="status" value="approved">
                        <button class="btn btn-sm btn-success">Mark as Approved</button>
                    </form>
                    <form action="{{ route('quotations.status', $quotation->id) }}" method="POST">@csrf @method('PATCH')<input type="hidden" name="status" value="rejected">
                        <button class="btn btn-sm btn-danger">Mark as Rejected</button>
                    </form>
                    @endif
                    @if($quotation->status === 'approved')
                        @can('ticket_invoices.create')
                        <a href="{{ route('ticket_invoices.create', ['quotation_id' => $quotation->id]) }}" class="btn btn-sm btn-primary">
                            Convert to Sale Invoice (Tickets)
                        </a>
                        @endcan
                        @can('tour_invoices.create')
                        <a href="{{ route('tour_invoices.create', ['quotation_id' => $quotation->id]) }}" class="btn btn-sm btn-primary">
                            Convert to Tour Invoice
                        </a>
                        @endcan
                    @endif
                    @if($quotation->status === 'converted')
                        <span class="text-muted">
                            Converted to
                            @if($quotation->converted_invoice_type === 'sale' && $quotation->converted_invoice_id)
                                <a href="{{ route('ticket_invoices.show', $quotation->converted_invoice_id) }}">Sale Invoice</a>
                            @elseif($quotation->converted_invoice_type === 'tour' && $quotation->converted_invoice_id)
                                <a href="{{ route('tour_invoices.show', $quotation->converted_invoice_id) }}">Tour Invoice</a>
                            @else
                                an invoice
                            @endif
                        </span>
                    @endif
                </div>
                @endif

                <hr>
                <h5>Passengers</h5>
                <table class="table table-bordered table-sm">
                    <thead><tr><th>Name</th><th>Passport/NIC</th><th>Type</th><th>Nationality</th></tr></thead>
                    <tbody>
                        @forelse($quotation->passengers as $pax)
                        <tr><td>{{ $pax->name }}</td><td>{{ $pax->passport_no_nic }}</td><td>{{ ucfirst($pax->pax_type) }}</td><td>{{ $pax->nationality }}</td></tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted">No passengers added.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <h5>Service Lines</h5>
                <table class="table table-bordered table-sm">
                    <thead><tr><th>Type</th><th>Supplier</th><th>Description</th><th>Receivable</th><th>Payable</th><th>Income</th></tr></thead>
                    <tbody>
                        @forelse($quotation->serviceLines as $line)
                        <tr>
                            <td>{{ ucfirst($line->service_type) }}</td>
                            <td>{{ $line->supplier->name ?? '—' }}</td>
                            <td>{{ $line->description }}</td>
                            <td>{{ $line->currency }} {{ number_format($line->receivable_l_amount, 2) }}</td>
                            <td>{{ $line->currency }} {{ number_format($line->payable_l_amount, 2) }}</td>
                            <td class="{{ $line->income_l_amount < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($line->income_l_amount, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted">No service lines added.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="3" class="text-end">Totals</td>
                            <td>{{ number_format($quotation->total_receivable, 2) }}</td>
                            <td>{{ number_format($quotation->total_payable, 2) }}</td>
                            <td>{{ number_format($quotation->total_income, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>

                @if($quotation->remarks)
                <hr><strong>Remarks:</strong> {{ $quotation->remarks }}
                @endif
            </div>
        </section>
    </div>
</div>
@endsection
