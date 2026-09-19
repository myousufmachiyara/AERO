@extends('layouts.app')

@section('title', 'Sale Invoice — ' . $invoice->invoice_no)

@section('content')
<div class="row">
    <div class="col-12">
        <section class="card mb-3">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">
                    {{ $invoice->invoice_no }}
                    <span class="badge {{ $invoice->status === 'posted' ? 'bg-success' : 'bg-warning text-dark' }}">{{ ucfirst($invoice->status) }}</span>
                </h2>
                <div>
                    @can('ticket_invoices.print')
                    <a href="{{ route('ticket_invoices.print', $invoice->id) }}" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print (Customer)</a>
                    <a href="{{ route('ticket_invoices.print_detailed', $invoice->id) }}" class="btn btn-default" target="_blank"><i class="fa fa-file-alt"></i> Print (Detailed)</a>
                    @endcan
                    @can('ticket_invoices.edit')
                    @if($invoice->isPending())
                    <a href="{{ route('ticket_invoices.edit', $invoice->id) }}" class="btn btn-primary"><i class="fa fa-edit"></i> Edit</a>
                    <form action="{{ route('ticket_invoices.post', $invoice->id) }}" method="POST" style="display:inline;">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-success" onclick="return confirm('Post this invoice? This will hit the customer and airline ledgers.');">
                            <i class="fa fa-check"></i> Post
                        </button>
                    </form>
                    @else
                    <form action="{{ route('ticket_invoices.unpost', $invoice->id) }}" method="POST" style="display:inline;">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-warning" onclick="return confirm('Unpost this invoice? The ledger entries it created will be reversed.');">
                            <i class="fa fa-undo"></i> Unpost
                        </button>
                    </form>
                    @endif
                    @endcan
                </div>
            </header>
            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

                <div class="row mb-2">
                    <div class="col-md-3"><strong>Date:</strong> {{ $invoice->invoice_date->format('d/m/Y') }}</div>
                    <div class="col-md-3"><strong>Adjustment Date:</strong> {{ $invoice->effectiveAdjustmentDate()->format('d/m/Y') }}</div>
                    <div class="col-md-3"><strong>Customer:</strong> {{ $invoice->customer->name ?? '—' }}</div>
                    <div class="col-md-3"><strong>Created By:</strong> {{ $invoice->creator->name ?? '—' }}</div>
                </div>
                @if($invoice->isPosted())
                <div class="row mb-2">
                    <div class="col-md-6"><strong>Posted By:</strong> {{ $invoice->poster->name ?? '—' }}</div>
                    <div class="col-md-6"><strong>Posted At:</strong> {{ optional($invoice->posted_at)->format('d/m/Y H:i') }}</div>
                </div>
                @endif
                @if($invoice->remarks)
                <div class="mb-2"><strong>Remarks:</strong> {{ $invoice->remarks }}</div>
                @endif
            </div>
        </section>

        @foreach($invoice->lines as $line)
        <section class="card mb-3">
            <header class="card-header d-flex justify-content-between align-items-center">
                <strong>Ticket #{{ $loop->iteration }} — {{ $line->pax_name }}</strong>
                <div>
                    <span class="badge {{ ['active' => 'bg-primary', 'refunded' => 'bg-info', 'voided' => 'bg-secondary'][$line->status] ?? 'bg-secondary' }}">
                        {{ ucfirst($line->status) }}
                    </span>
                    @if($line->isActive() && $invoice->isPending())
                    @can('ticket_invoices.edit')
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="toggleRow('refund-{{ $line->id }}')">Refund</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="toggleRow('void-{{ $line->id }}')">Void</button>
                    @endcan
                    @endif
                </div>
            </header>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-md-2"><strong>Pax Type:</strong> {{ ucfirst($line->pax_type) }}</div>
                    <div class="col-md-2"><strong>PNR:</strong> {{ $line->pnr ?: '—' }}</div>
                    <div class="col-md-2"><strong>Ticket #:</strong> {{ $line->ticket_no ?: '—' }}</div>
                    <div class="col-md-2"><strong>Airline:</strong> {{ $line->airline->name ?? '—' }}</div>
                    <div class="col-md-2"><strong>Supplier:</strong> {{ $line->supplier->name ?? '—' }}</div>
                    <div class="col-md-2"><strong>Cities:</strong> {{ $line->citiesLabel() ?: '—' }}</div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-2"><strong>Fare:</strong> {{ number_format($line->fare_amount, 2) }}</div>
                    <div class="col-md-2"><strong>Tax:</strong> {{ number_format($line->tax_amount, 2) }}</div>
                    <div class="col-md-2"><strong>APT:</strong> {{ number_format($line->apt_charges, 2) }}</div>
                    <div class="col-md-2"><strong>Comm %:</strong> {{ number_format($line->commission_percent, 2) }}</div>
                    <div class="col-md-2"><strong>Comm Amt:</strong> {{ number_format($line->commission_amount, 2) }}</div>
                    <div class="col-md-2"><strong>WHT:</strong> {{ number_format($line->wht_amount, 2) }}</div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-2"><strong>PSF:</strong> {{ number_format($line->psf_amount, 2) }}</div>
                    <div class="col-md-2"><strong>Discount:</strong> {{ number_format($line->discount_amount, 2) }}</div>
                    <div class="col-md-2"><strong>Agent:</strong> {{ $line->salesAgent->name ?? '—' }}</div>
                    <div class="col-md-2"><strong>Agent Comm:</strong> {{ number_format($line->agent_commission_amount, 2) }}</div>
                    <div class="col-md-4 text-end"><strong>Amount Receivable:</strong> <span class="fs-5">{{ number_format($line->effectiveReceivable(), 2) }}</span></div>
                </div>

                @if($line->status === 'refunded')
                <div class="alert alert-info mb-0">
                    Refunded on {{ optional($line->refund_date)->format('d/m/Y') }} (adjustment {{ optional($line->refund_adjustment_date)->format('d/m/Y') }}) —
                    Fare {{ number_format($line->refund_fare_amount, 2) }}, Tax {{ number_format($line->refund_tax_amount, 2) }},
                    Charges {{ number_format($line->refund_charges, 2) }}, Returned to customer {{ number_format($line->refund_amount, 2) }},
                    Retained profit {{ number_format($line->refund_profit, 2) }}.
                </div>
                @elseif($line->status === 'voided')
                <div class="alert alert-secondary mb-0">
                    Voided on {{ optional($line->void_date)->format('d/m/Y') }} —
                    Supplier deduction {{ number_format($line->void_deduction_supplier, 2) }},
                    {{ config('travel.company_name') }} deduction {{ number_format($line->void_deduction_company, 2) }},
                    Total receivable from customer {{ number_format($line->void_total_deduction, 2) }}.
                </div>
                @endif

                @if($line->isActive() && $invoice->isPending())
                <div id="refund-{{ $line->id }}" class="mt-3 p-3 border rounded" style="display:none;">
                    <h6>Refund Ticket {{ $line->ticket_no }}</h6>
                    <form action="{{ route('ticket_invoices.lines.refund', [$invoice->id, $line->id]) }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Refund Date <span class="text-danger">*</span></label>
                                <input type="date" name="refund_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Refund Adjustment Date</label>
                                <input type="date" name="refund_adjustment_date" class="form-control">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Fare Amount</label>
                                <input type="number" step="0.01" min="0" name="refund_fare_amount" class="form-control" value="{{ $line->fare_amount }}" required>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Tax Amount</label>
                                <input type="number" step="0.01" min="0" name="refund_tax_amount" class="form-control" value="{{ $line->tax_amount }}" required>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Refund Charges</label>
                                <input type="number" step="0.01" min="0" name="refund_charges" class="form-control" value="0" required>
                            </div>
                        </div>
                        <small class="text-muted">Original fare/tax are pre-filled for reference — adjust for a partial refund. Refund amount and retained profit are calculated automatically.</small>
                        <div class="mt-2">
                            <button type="submit" class="btn btn-info btn-sm" onclick="return confirm('Refund this ticket? This cannot be undone.');">Confirm Refund</button>
                            <button type="button" class="btn btn-default btn-sm" onclick="toggleRow('refund-{{ $line->id }}')">Cancel</button>
                        </div>
                    </form>
                </div>

                <div id="void-{{ $line->id }}" class="mt-3 p-3 border rounded" style="display:none;">
                    <h6>Void Ticket {{ $line->ticket_no }}</h6>
                    @if(!now()->isSameDay($invoice->invoice_date))
                    <div class="alert alert-warning mb-0">Tickets can only be voided on the same day they were issued ({{ $invoice->invoice_date->format('d/m/Y') }}). Use Refund instead.</div>
                    @else
                    <form action="{{ route('ticket_invoices.lines.void', [$invoice->id, $line->id]) }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Deduction by Supplier</label>
                                <input type="number" step="0.01" min="0" name="void_deduction_supplier" class="form-control" value="0" required>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Deduction by {{ config('travel.company_name') }}</label>
                                <input type="number" step="0.01" min="0" name="void_deduction_company" class="form-control" value="0" required>
                            </div>
                        </div>
                        <small class="text-muted">All other amounts on this ticket become 0 — only the total deduction remains receivable from the customer.</small>
                        <div class="mt-2">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Void this ticket? This cannot be undone.');">Confirm Void</button>
                            <button type="button" class="btn btn-default btn-sm" onclick="toggleRow('void-{{ $line->id }}')">Cancel</button>
                        </div>
                    </form>
                    @endif
                </div>
                @endif
            </div>
        </section>
        @endforeach

        <section class="card">
            <div class="card-body d-flex justify-content-end">
                <table class="table table-sm w-auto mb-0">
                    <tr><td class="text-end pe-3">Total Fare:</td><td>{{ number_format($invoice->total_fare, 2) }}</td></tr>
                    <tr><td class="text-end pe-3">Total Tax:</td><td>{{ number_format($invoice->total_tax, 2) }}</td></tr>
                    <tr><td class="text-end pe-3">Total APT:</td><td>{{ number_format($invoice->total_apt, 2) }}</td></tr>
                    <tr><td class="text-end pe-3">Total Commission:</td><td>{{ number_format($invoice->total_commission, 2) }}</td></tr>
                    <tr><td class="text-end pe-3">Total WHT:</td><td>{{ number_format($invoice->total_wht, 2) }}</td></tr>
                    <tr><td class="text-end pe-3">Total PSF:</td><td>{{ number_format($invoice->total_psf, 2) }}</td></tr>
                    <tr><td class="text-end pe-3">Total Discount:</td><td>{{ number_format($invoice->total_discount, 2) }}</td></tr>
                    <tr class="fw-bold"><td class="text-end pe-3">Grand Total (Customer):</td><td>{{ number_format($invoice->total_amount, 2) }}</td></tr>
                </table>
            </div>
        </section>
    </div>
</div>

<script>
    function toggleRow(id) {
        const el = document.getElementById(id);
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }
</script>
@endsection