@extends('layouts.app')

@section('title', 'Sale Invoice — ' . $invoice->invoice_no)

@php
    // Same "shared across every ticket" field list the create/edit form
    // uses (see _form.blade.php's SHARED_FIELD_IDS) — kept in sync here so
    // the "tickets don't all match" warning below fires under exactly the
    // same condition it would on the form.
    $sharedFields = [
        'supplier_id', 'airline_id', 'pnr', 'trip_type',
        'leg1_from', 'leg1_stay', 'leg1_to', 'leg2_from', 'leg2_stay', 'leg2_to',
        'fare_amount', 'tax_amount', 'apt_percent', 'commission_percent', 'wht_percent',
        'psf_percent', 'psf_amount', 'psf_basis', 'psf_input_mode',
        'discount_percent', 'discount_amount', 'discount_input_mode',
        'sales_agent_id', 'agent_commission_percent',
    ];
    $firstLine = $invoice->lines->first();
    $linesDiffer = false;
    if ($firstLine && $invoice->lines->count() > 1) {
        foreach ($invoice->lines->slice(1) as $otherLine) {
            foreach ($sharedFields as $field) {
                if ((string) $otherLine->{$field} !== (string) $firstLine->{$field}) {
                    $linesDiffer = true;
                    break 2;
                }
            }
        }
    }

    // Bulk Refund/Void (checkboxes in the Tickets table below) only make
    // sense under the exact same conditions the existing single-ticket
    // Refund/Void buttons already require: the invoice must still be
    // pending, and the user needs edit permission. Computed once here
    // rather than re-checked per row.
    // Client fix: posted tickets can be refunded/voided too (that posts its
    // own reversal + charge vouchers), so only the permission matters now.
    $canBulkAct = auth()->user()?->can('ticket_invoices.edit') ?? false;
    $activeLineCount = $invoice->lines->where('status', 'active')->count();
@endphp

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
                        <button type="submit" class="btn btn-success" onclick="return confirm('Post this invoice? This will hit the customer and supplier ledgers.');">
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
                    @can('invoice_payments.create')
                    @if($invoice->outstandingAmount() > 0)
                    <a href="{{ route('invoice_payments.create', ['direction' => 'receipt', 'customer_id' => $invoice->customer_id, 'ticket_sale_invoice_id' => $invoice->id]) }}" class="btn btn-success"><i class="fa fa-hand-holding-usd"></i> Record Receipt</a>
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

        @if($firstLine)
        <section class="card mb-3">
            <header class="card-header"><strong>Master Details</strong> <span class="text-muted small">(shared by every ticket on this invoice)</span></header>
            <div class="card-body">
                @if($linesDiffer)
                <div class="alert alert-warning">
                    These tickets were entered with different supplier/PNR/cities/fare/charge values on one or more rows
                    (most likely created before this invoice used shared Master Details). The figures below are from the
                    <strong>first</strong> ticket only — check each ticket's own numbers in the Amount Receivable column,
                    and open Edit to see every row's actual values.
                </div>
                @endif

                <div class="row mb-2">
                    <div class="col-md-2"><strong>Supplier:</strong> {{ $firstLine->supplier->name ?? '—' }}</div>
                    <div class="col-md-2"><strong>Airline:</strong> {{ $firstLine->airline->name ?? '—' }}</div>
                    <div class="col-md-2"><strong>PNR:</strong> {{ $firstLine->pnr ?: '—' }}</div>
                    <div class="col-md-2"><strong>Ticket Type:</strong> {{ ucfirst($firstLine->ticket_type ?: 'international') }}</div>
                    <div class="col-md-2"><strong>Trip Type:</strong> {{ $firstLine->trip_type === 'return' ? 'Return' : 'One Way' }}</div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-4"><strong>Cities:</strong> {{ $firstLine->citiesLabel() ?: '—' }}</div>
                    <div class="col-md-4"><strong>Departure:</strong> {{ optional($firstLine->leg1_departure_date)->format('d/m/Y') ?: '—' }} {{ $firstLine->leg1_departure_time }}</div>
                    @if($firstLine->trip_type === 'return')
                    <div class="col-md-4"><strong>Return Departure:</strong> {{ optional($firstLine->leg2_departure_date)->format('d/m/Y') ?: '—' }} {{ $firstLine->leg2_departure_time }}</div>
                    @endif
                </div>
                <div class="row mb-2">
                    <div class="col-md-2"><strong>Fare:</strong> {{ number_format($firstLine->fare_amount, 2) }}</div>
                    <div class="col-md-2"><strong>Tax:</strong> {{ number_format($firstLine->tax_amount, 2) }}</div>
                    <div class="col-md-2"><strong>APT %:</strong> {{ number_format($firstLine->apt_percent, 2) }}% <small class="text-muted">{{ $firstLine->apt_input_mode === 'amount' ? '(from amount)' : '' }}</small></div>
                    <div class="col-md-2"><strong>APT:</strong> {{ number_format($firstLine->apt_charges, 2) }}</div>
                    <div class="col-md-2"><strong>Comm %:</strong> {{ number_format($firstLine->commission_percent, 2) }}% <small class="text-muted">{{ $firstLine->commission_input_mode === 'amount' ? '(from amount)' : '' }}</small></div>
                    <div class="col-md-2"><strong>Comm Amt:</strong> {{ number_format($firstLine->commission_amount, 2) }}</div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-2"><strong>WHT % (on comm):</strong> {{ number_format($firstLine->wht_percent, 2) }}% <small class="text-muted">{{ $firstLine->wht_input_mode === 'amount' ? '(from amount)' : '' }}</small></div>
                    <div class="col-md-2"><strong>WHT:</strong> {{ number_format($firstLine->wht_amount, 2) }}</div>
                    <div class="col-md-2">
                        <strong>PSF %:</strong> {{ number_format($firstLine->psf_percent, 2) }}%
                        <small class="text-muted">({{ $firstLine->psf_basis === 'total' ? 'fare+tax+apt' : 'fare' }}{{ $firstLine->psf_input_mode === 'amount' ? ', from amount' : '' }})</small>
                    </div>
                    <div class="col-md-2"><strong>PSF:</strong> {{ number_format($firstLine->psf_amount, 2) }}</div>
                    <div class="col-md-2">
                        <strong>Discount %:</strong> {{ number_format($firstLine->discount_percent, 2) }}%
                        <small class="text-muted">{{ $firstLine->discount_input_mode === 'amount' ? '(from amount)' : '' }}</small>
                    </div>
                    <div class="col-md-2"><strong>Discount:</strong> {{ number_format($firstLine->discount_amount, 2) }}</div>
                </div>
                <div class="row">
                    <div class="col-md-2"><strong>Sale Agent(SPO):</strong> {{ $firstLine->salesAgent->name ?? '—' }}</div>
                    <div class="col-md-2"><strong>Sale Comm %:</strong> {{ number_format($firstLine->agent_commission_percent, 2) }}% <small class="text-muted">(on PSF{{ $firstLine->agent_commission_input_mode === 'amount' ? ', from amount' : '' }})</small></div>
                    <div class="col-md-2"><strong>Sale Comm:</strong> {{ number_format($firstLine->agent_commission_amount, 2) }}</div>
                    <div class="col-md-4 text-end"><strong>Ticket Total:</strong> <span class="fs-5">{{ number_format($firstLine->total_amount, 2) }}</span></div>
                </div>
            </div>
        </section>
        @endif

        <section class="card mb-3">
            <header class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div><strong>Tickets</strong> <span class="text-muted small">({{ $invoice->lines->count() }})</span></div>
                @if($canBulkAct && $activeLineCount > 1)
                <div id="bulk-actions-toolbar" style="display:none;">
                    <span class="text-muted small"><span id="bulk-selected-count">0</span> selected</span>
                    <button type="button" class="btn btn-sm btn-outline-info" id="bulk-refund-btn" onclick="ticketInvoiceToggleRow('bulk-refund-panel')">Bulk Refund</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="bulk-void-btn" onclick="ticketInvoiceToggleRow('bulk-void-panel')">Bulk Void</button>
                </div>
                @endif
            </header>

            {{--
                The whole table (and the bulk panels below it) sits inside ONE
                form so the checked checkboxes post straight through as
                line_ids[] — no JS needed to collect selections. Bulk Refund
                and Bulk Void are two different endpoints, so their buttons
                each override the form's action/method via formaction
                (standard HTML, works without JS). The single-ticket
                Refund/Void panels further down the page are deliberately
                OUTSIDE this form — forms cannot nest in HTML.
            --}}
            <form method="POST" id="bulk-ticket-form" data-ajax-form>
                @csrf
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                @if($canBulkAct && $activeLineCount > 1)
                                <th style="width:2.5rem;"><input type="checkbox" id="select-all-tickets" title="Select all active tickets" @if($activeLineCount === 0) disabled @endif></th>
                                @endif
                                <th>#</th>
                                <th>Passenger Name</th>
                                <th>Passenger Type</th>
                                <th>Ticket #</th>
                                <th>Status</th>
                                <th class="text-end">Amount Receivable</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->lines as $line)
                            <tr>
                                @if($canBulkAct && $activeLineCount > 1)
                                <td>
                                    @if($line->isActive())
                                    <input type="checkbox" class="bulk-line-checkbox" name="line_ids[]" value="{{ $line->id }}" onchange="updateBulkSelectionUI()">
                                    @endif
                                </td>
                                @endif
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $line->pax_name }}</td>
                                <td>{{ ucfirst($line->pax_type) }}</td>
                                <td>{{ $line->ticket_no ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ ['active' => 'bg-primary', 'refunded' => 'bg-info', 'voided' => 'bg-secondary'][$line->status] ?? 'bg-secondary' }}">
                                        {{ ucfirst($line->status) }}
                                    </span>
                                </td>
                                <td class="text-end">{!! \App\Support\Money::receivable($line->effectiveReceivable()) !!}</td>
                                <td class="text-end">
                                    @if($line->isActive())
                                    @can('ticket_invoices.edit')
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="ticketInvoiceToggleRow('refund-{{ $line->id }}')">Refund</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="ticketInvoiceToggleRow('void-{{ $line->id }}')">Void</button>
                                    @endcan
                                    @else
                                    @can('ticket_invoices.edit')
                                    {{-- The revert form lives outside the bulk form (nested forms are not allowed); this button submits it via form="". --}}
                                    <button type="submit" form="revert-form-{{ $line->id }}" class="btn btn-sm btn-outline-warning" title="Undo the {{ $line->status === 'refunded' ? 'refund' : 'void' }}"><i class="fas fa-undo"></i> Revert {{ $line->status === 'refunded' ? 'Refund' : 'Void' }}</button>
                                    @endcan
                                    @endif
                                </td>
                            </tr>
                            @if($line->status === 'refunded' || $line->status === 'voided')
                            <tr>
                                @if($canBulkAct && $activeLineCount > 1)
                                <td></td>
                                @endif
                                <td></td>
                                <td colspan="6" class="pb-2">
                                    @if($line->status === 'refunded')
                                    <div class="alert alert-info mb-0 py-2">
                                        Refunded on {{ optional($line->refund_date)->format('d/m/Y') }} (adjustment {{ optional($line->refund_adjustment_date)->format('d/m/Y') }}) —
                                        Fare {{ number_format($line->refund_fare_amount, 2) }}, Tax {{ number_format($line->refund_tax_amount, 2) }},
                                        Supplier deduction {{ number_format($line->refund_deduction_supplier, 2) }},
                                        {{ config('travel.company_name') }} deduction {{ number_format($line->refund_deduction_company, 2) }},
                                        Returned to customer {{ number_format($line->refund_amount, 2) }},
                                        Still charged to customer {{ number_format($line->refund_profit, 2) }} (all payable to the supplier — no profit on a refunded ticket).
                                    </div>
                                    @else
                                    <div class="alert alert-secondary mb-0 py-2">
                                        Voided on {{ optional($line->void_date)->format('d/m/Y') }} —
                                        Supplier deduction {{ number_format($line->void_deduction_supplier, 2) }},
                                        {{ config('travel.company_name') }} deduction {{ number_format($line->void_deduction_company, 2) }},
                                        Charges {{ number_format($line->void_total_deduction, 2) }} deducted from the customer (returned {{ number_format((float) $line->total_amount - (float) $line->void_total_deduction, 2) }} of {{ number_format((float) $line->total_amount, 2) }}); all charges payable to the supplier — no profit on a voided ticket.
                                    </div>
                                    @endif
                                </td>
                            </tr>
                            @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($canBulkAct && $activeLineCount > 1)
                <div class="ajax-form-errors alert alert-danger py-2 m-2" style="display:none;"></div>
                <div id="bulk-refund-panel" class="card-body border-top" style="display:none;">
                    <h6>Bulk Refund <span id="bulk-refund-count-label"></span></h6>
                    @if($invoice->isPosted())<div class="alert alert-info py-2 small mb-2">This invoice is posted — each refund also posts reversal + charge vouchers to the customer and supplier ledgers.</div>@endif
                    <p class="text-muted small mb-2">Every selected ticket is refunded in full (its own fare + tax), with the same supplier/company deduction split applied to each one. Need a different amount for one specific ticket? Use that ticket's own Refund button instead.</p>
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Refund Date <span class="text-danger">*</span></label>
                            <input type="date" name="refund_date" form="bulk-ticket-form" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Refund Adjustment Date</label>
                            <input type="date" name="refund_adjustment_date" form="bulk-ticket-form" class="form-control">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Deduction by Supplier <span class="text-muted">(per ticket)</span></label>
                            <input type="number" step="0.01" min="0" name="refund_deduction_supplier" form="bulk-ticket-form" class="form-control" value="0" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Deduction by {{ config('travel.company_name') }} <span class="text-muted">(per ticket)</span></label>
                            <input type="number" step="0.01" min="0" name="refund_deduction_company" form="bulk-ticket-form" class="form-control" value="0" required>
                        </div>
                    </div>
                    <div class="mt-2">
                        <button type="submit" form="bulk-ticket-form" formaction="{{ route('ticket_invoices.lines.bulk_refund', $invoice->id) }}" class="btn btn-info btn-sm" id="bulk-refund-submit" data-bulk-kind="refund">Confirm Bulk Refund</button>
                        <button type="button" class="btn btn-default btn-sm" onclick="ticketInvoiceToggleRow('bulk-refund-panel')">Cancel</button>
                    </div>
                </div>

                <div id="bulk-void-panel" class="card-body border-top" style="display:none;">
                    <h6>Bulk Void <span id="bulk-void-count-label"></span></h6>
                    @if(!now()->isSameDay($invoice->invoice_date))
                    <div class="alert alert-warning mb-0">Tickets can only be voided on the same day they were issued ({{ $invoice->invoice_date->format('d/m/Y') }}). Use Refund instead.</div>
                    @else
                    <p class="text-muted small mb-2">The same supplier/company deduction split is applied to every selected ticket — all other amounts on each become 0, same as a single Void.</p>
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Deduction by Supplier <span class="text-muted">(per ticket)</span></label>
                            <input type="number" step="0.01" min="0" name="void_deduction_supplier" form="bulk-ticket-form" class="form-control" value="0" required>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Deduction by {{ config('travel.company_name') }} <span class="text-muted">(per ticket)</span></label>
                            <input type="number" step="0.01" min="0" name="void_deduction_company" form="bulk-ticket-form" class="form-control" value="0" required>
                        </div>
                    </div>
                    <div class="mt-2">
                        <button type="submit" form="bulk-ticket-form" formaction="{{ route('ticket_invoices.lines.bulk_void', $invoice->id) }}" class="btn btn-danger btn-sm" id="bulk-void-submit" data-bulk-kind="void">Confirm Bulk Void</button>
                        <button type="button" class="btn btn-default btn-sm" onclick="ticketInvoiceToggleRow('bulk-void-panel')">Cancel</button>
                    </div>
                    @endif
                </div>
                @endif
            </form>

            @can('ticket_invoices.edit')
            @foreach($invoice->lines->where('status', '!=', 'active') as $line)
            <form id="revert-form-{{ $line->id }}" action="{{ route('ticket_invoices.lines.revert', [$invoice->id, $line->id]) }}" method="POST" data-ajax-form class="d-none"
                  data-confirm="Revert the {{ $line->status === 'refunded' ? 'refund' : 'void' }} of ticket {{ $line->ticket_no }}? It becomes active again with its original amounts{{ $invoice->isPosted() ? ' and the ledgers go back to the original posting' : '' }}.">
                @csrf
            </form>
            @endforeach
            @endcan
        </section>

        @foreach($invoice->lines as $line)
        @if($line->isActive())
        @can('ticket_invoices.edit')
        @php
            $ticketDetails = [
                'Passenger' => $line->pax_name . ' (' . ucfirst($line->pax_type) . ')',
                'Ticket #' => $line->ticket_no ?: '—',
                'PNR' => $line->pnr ?: '—',
                'Airline' => $line->airline->name ?? '—',
                'Supplier' => $line->supplier->name ?? '—',
                'Sector' => $line->citiesLabel() ?: '—',
                'Ticket Type' => ucfirst($line->ticket_type ?: 'international') . ' / ' . ($line->trip_type === 'return' ? 'Return' : 'One Way'),
                'Departure' => trim((optional($line->leg1_departure_date)->format('d/m/Y') ?? '') . ' ' . ($line->leg1_departure_time ?? '')) ?: '—',
            ];
            $ticketAmounts = [
                'Fare' => $line->fare_amount,
                'Tax' => $line->tax_amount,
                'APT (' . number_format($line->apt_percent, 2) . '%)' => $line->apt_charges,
                'PSF (' . number_format($line->psf_percent, 2) . '%)' => $line->psf_amount,
                'Discount (' . number_format($line->discount_percent, 2) . '%)' => $line->discount_amount,
                'Commission (' . number_format($line->commission_percent, 2) . '%)' => $line->commission_amount,
                'WHT (' . number_format($line->wht_percent, 2) . '%)' => $line->wht_amount,
                'Sale Comm. (' . number_format($line->agent_commission_percent, 2) . '%)' => $line->agent_commission_amount,
                'Ticket Total' => $line->total_amount,
            ];
        @endphp
        @foreach(['refund', 'void'] as $kind)
        <div id="{{ $kind }}-{{ $line->id }}" class="card mb-3 p-3 ticket-adjust-panel" style="display:none;"
             data-total="{{ (float) $line->total_amount }}">
            <h6 class="mb-2">{{ ucfirst($kind) }} Ticket {{ $line->ticket_no }} — {{ $line->pax_name }}
                @if($invoice->isPosted())<span class="badge bg-success ms-1">Posted — vouchers will be created</span>@endif
            </h6>

            <div class="row g-2 small mb-2">
                @foreach($ticketDetails as $label => $value)
                <div class="col-md-3"><span class="text-muted">{{ $label }}:</span> <strong>{{ $value }}</strong></div>
                @endforeach
            </div>
            <div class="table-responsive mb-2">
                <table class="table table-sm table-bordered mb-0 small text-end">
                    <tr class="table-light">@foreach($ticketAmounts as $label => $v)<th class="text-end">{{ $label }}</th>@endforeach</tr>
                    <tr>@foreach($ticketAmounts as $label => $v)<td>{{ number_format((float) $v, 2) }}</td>@endforeach</tr>
                </table>
            </div>

            @if($kind === 'void' && !now()->isSameDay($invoice->invoice_date))
            <div class="alert alert-warning mb-0">Tickets can only be voided on the same day they were issued ({{ $invoice->invoice_date->format('d/m/Y') }}). Use Refund instead.</div>
            @else
            <form action="{{ route('ticket_invoices.lines.' . $kind, [$invoice->id, $line->id]) }}" method="POST" data-ajax-form
                  data-confirm="{{ ucfirst($kind) }} this ticket?{{ $invoice->isPosted() ? ' Reversal and charge vouchers will be posted to the ledgers.' : '' }} It can be reverted later.">
                @csrf
                <div class="ajax-form-errors alert alert-danger py-2" style="display:none;"></div>
                <div class="row">
                    @if($kind === 'refund')
                    <div class="col-md-2 mb-2">
                        <label class="form-label">Refund Date <span class="text-danger">*</span></label>
                        <input type="date" name="refund_date" class="form-control" value="{{ now()->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">Adjustment Date</label>
                        <input type="date" name="refund_adjustment_date" class="form-control">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">Refund Fare</label>
                        <input type="number" step="any" min="0" name="refund_fare_amount" class="form-control calc-in" value="{{ $line->fare_amount }}">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">Refund Tax</label>
                        <input type="number" step="any" min="0" name="refund_tax_amount" class="form-control calc-in" value="{{ $line->tax_amount }}">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">Deduction by Supplier</label>
                        <input type="number" step="any" min="0" name="refund_deduction_supplier" class="form-control calc-in" value="0">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">Deduction by {{ config('travel.company_name') }}</label>
                        <input type="number" step="any" min="0" name="refund_deduction_company" class="form-control calc-in" value="0">
                    </div>
                    @else
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Deduction by Supplier</label>
                        <input type="number" step="any" min="0" name="void_deduction_supplier" class="form-control calc-in" value="0">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Deduction by {{ config('travel.company_name') }}</label>
                        <input type="number" step="any" min="0" name="void_deduction_company" class="form-control calc-in" value="0">
                    </div>
                    @endif
                </div>
                <div class="small mb-2 adjust-preview" data-kind="{{ $kind }}"></div>
                <div>
                    <button type="submit" class="btn btn-{{ $kind === 'refund' ? 'info' : 'danger' }} btn-sm">Confirm {{ ucfirst($kind) }}</button>
                    <button type="button" class="btn btn-default btn-sm" onclick="ticketInvoiceToggleRow('{{ $kind }}-{{ $line->id }}')">Cancel</button>
                </div>
            </form>
            @endif
        </div>
        @endforeach
        @endcan
        @endif
        @endforeach

        @php $adjEntries = $invoice->ledgerEntries->sortBy('id'); @endphp
        @if($adjEntries->isNotEmpty())
        <section class="card mb-3">
            <header class="card-header"><strong>Ledger Vouchers</strong> <span class="text-muted small">— posting, refund and void entries created by this invoice</span></header>
            <div class="table-responsive">
                <table class="table table-sm mb-0 small align-middle">
                    <thead>
                        <tr><th>Voucher #</th><th>Date</th><th>Type</th><th>Debit</th><th>Credit</th><th class="text-end">Amount</th><th>Description</th></tr>
                    </thead>
                    <tbody>
                        @foreach($adjEntries as $entry)
                        @php $v = $entry->voucher; @endphp
                        <tr class="{{ $entry->adjustment ? ($entry->adjustment === 'refund' ? 'table-info' : 'table-secondary') : '' }}">
                            <td>{{ $v->id ?? '—' }}</td>
                            <td>{{ $v ? \Illuminate\Support\Carbon::parse($v->date)->format('d/m/Y') : '—' }}</td>
                            <td>
                                @if($entry->adjustment)
                                <span class="badge {{ $entry->adjustment === 'refund' ? 'bg-info' : 'bg-secondary' }}">{{ ucfirst($entry->adjustment) }}</span>
                                {{ str_starts_with($entry->entry_type, 'rev_') ? 'Reversal' : 'Charges' }}
                                @else
                                <span class="badge bg-success">Post</span>
                                @endif
                            </td>
                            <td>{{ $v?->debitAccount->name ?? '—' }}</td>
                            <td>{{ $v?->creditAccount->name ?? '—' }}</td>
                            <td class="text-end">{{ number_format((float) $entry->amount, 2) }}</td>
                            <td>{{ $v->description ?? '' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @endif

        <section class="card">
            <div class="card-body d-flex justify-content-end">
                @php
                    // Complete picture: the sale AS ISSUED (every ticket), then the
                    // refund / void adjustments, then what is left (client fix, Nov 2026).
                    $all = $invoice->lines;
                    $refunded = $all->where('status', 'refunded');
                    $voided = $all->where('status', 'voided');
                    $active = $all->where('status', 'active');
                    $gross = $all->sum(fn ($l) => (float) $l->total_amount);
                    $refundBack = $refunded->sum(fn ($l) => (float) $l->refund_amount);
                    $voidBack = $voided->sum(fn ($l) => (float) $l->total_amount - (float) $l->void_total_deduction);
                    $chargesKept = $refunded->sum(fn ($l) => (float) $l->refund_deduction_supplier + (float) $l->refund_deduction_company)
                        + $voided->sum(fn ($l) => (float) $l->void_total_deduction);
                    $supplierGross = $all->sum(fn ($l) => $l->supplierNet());
                @endphp
                <table class="table table-sm w-auto mb-0">
                    <tr><th colspan="2">Sale as issued <small class="text-muted fw-normal">({{ $all->count() }} ticket(s))</small></th></tr>
                    <tr><td class="text-end pe-3">Total Fare:</td><td class="text-end">{{ number_format($all->sum('fare_amount'), 2) }}</td></tr>
                    <tr><td class="text-end pe-3">Total Tax:</td><td class="text-end">{{ number_format($all->sum('tax_amount'), 2) }}</td></tr>
                    <tr><td class="text-end pe-3">Total APT:</td><td class="text-end">{{ number_format($all->sum('apt_charges'), 2) }}</td></tr>
                    <tr><td class="text-end pe-3">Total PSF:</td><td class="text-end">{{ number_format($all->sum('psf_amount'), 2) }}</td></tr>
                    <tr><td class="text-end pe-3">Total Discount:</td><td class="text-end">{{ number_format($all->sum('discount_amount'), 2) }}</td></tr>
                    <tr class="fw-bold"><td class="text-end pe-3">Gross Sale (Customer):</td><td class="text-end">{{ number_format($gross, 2) }}</td></tr>
                    <tr><td class="text-end pe-3">Total Commission:</td><td class="text-end">{{ number_format($all->sum('commission_amount'), 2) }}</td></tr>
                    <tr><td class="text-end pe-3">Total WHT:</td><td class="text-end">{{ number_format($all->sum('wht_amount'), 2) }}</td></tr>
                    <tr><td class="text-end pe-3">Gross Supplier Net:</td><td class="text-end">{{ number_format($supplierGross, 2) }}</td></tr>
                </table>
                <table class="table table-sm w-auto mb-0 ms-4">
                    <tr><th colspan="2">After refunds &amp; voids
                        <small class="text-muted fw-normal">({{ $active->count() }} active · {{ $refunded->count() }} refunded · {{ $voided->count() }} voided)</small></th></tr>
                    <tr><td class="text-end pe-3">Gross Sale (Customer):</td><td class="text-end">{{ number_format($gross, 2) }}</td></tr>
                    @if($refunded->isNotEmpty())
                    <tr><td class="text-end pe-3">Less: Refunded to Customer:</td><td class="text-end">{!! \App\Support\Money::payable($refundBack) !!}</td></tr>
                    @endif
                    @if($voided->isNotEmpty())
                    <tr><td class="text-end pe-3">Less: Voided, returned to Customer:</td><td class="text-end">{!! \App\Support\Money::payable($voidBack) !!}</td></tr>
                    @endif
                    @if($refunded->isNotEmpty() || $voided->isNotEmpty())
                    <tr><td class="text-end pe-3 text-muted">of which refund / void charges kept:</td><td class="text-end text-muted">{{ number_format($chargesKept, 2) }}</td></tr>
                    @endif
                    <tr class="fw-bold border-top"><td class="text-end pe-3">Net Receivable (Customer):</td><td class="text-end">{!! \App\Support\Money::receivable($invoice->total_amount) !!}</td></tr>
                    <tr><td class="text-end pe-3">Payable to Supplier (net of commission):</td><td class="text-end">{!! \App\Support\Money::payable($invoice->total_payable) !!}</td></tr>
                    <tr class="fw-bold"><td class="text-end pe-3">Income:</td><td class="text-end">{{ number_format($invoice->total_income, 2) }}</td></tr>
                </table>
            </div>
            @php
                // Final totals (client request, Nov 28): what the customer owes in total
                // and what we owe each supplier in total, after refunds / voids.
                $finalReceivable = (float) $invoice->total_amount;
                $finalReceived = $invoice->receivedAmount();
                $finalBalance = $finalReceivable - $finalReceived;
                $finalBySupplier = $all->groupBy('supplier_id')->map(fn ($ls) => [
                    'name' => optional($ls->first()->supplier)->name ?? '—',
                    'amount' => $ls->sum(fn ($l) => $l->effectiveSupplierPayable()),
                ])->filter(fn ($r) => abs($r['amount']) > 0.004);
                $finalPayable = (float) $invoice->total_payable;
            @endphp
            <div class="card-footer bg-light">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100 bg-white">
                            <div class="text-uppercase small text-muted fw-semibold mb-2">Final Receivable from Customer</div>
                            <div class="d-flex justify-content-between"><span>{{ optional($invoice->customer)->name }}</span>
                                <span class="fs-5 fw-bold">{!! \App\Support\Money::receivable($finalReceivable) !!}</span></div>
                            <div class="d-flex justify-content-between small mt-1"><span class="text-muted">Received so far</span><span>{{ number_format($finalReceived, 2) }}</span></div>
                            <div class="d-flex justify-content-between small fw-bold border-top pt-1 mt-1"><span>Balance to receive</span><span>{!! \App\Support\Money::receivable($finalBalance) !!}</span></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100 bg-white">
                            <div class="text-uppercase small text-muted fw-semibold mb-2">Final Payable to Supplier</div>
                            @if($finalBySupplier->count() > 1)
                                @foreach($finalBySupplier as $r)
                                    <div class="d-flex justify-content-between small"><span>{{ $r['name'] }}</span><span>{!! \App\Support\Money::payable($r['amount']) !!}</span></div>
                                @endforeach
                                <div class="d-flex justify-content-between border-top pt-1 mt-1"><span class="fw-semibold">Total</span>
                                    <span class="fs-5 fw-bold">{!! \App\Support\Money::payable($finalPayable) !!}</span></div>
                            @else
                                <div class="d-flex justify-content-between"><span>{{ optional($finalBySupplier->first())['name'] ?? optional(optional($all->first())->supplier)->name }}</span>
                                    <span class="fs-5 fw-bold">{!! \App\Support\Money::payable($finalPayable) !!}</span></div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
    // Namespaced (not a generic global "toggleRow") so this can't be
    // silently shadowed by a same-named helper elsewhere in a larger
    // app — that collision is the #1 cause of "the button does nothing,
    // no error shown" on a page like this.
    function ticketInvoiceToggleRow(id) {
        const el = document.getElementById(id);
        if (!el) {
            console.error('[ticket-invoices/show] No element with id="' + id + '" — the Refund/Void panel markup is missing or its id changed.');
            return;
        }
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }

    // Bulk Refund/Void selection — keeps the toolbar's "N selected" count,
    // the Bulk Refund/Bulk Void buttons, and the "select all" checkbox's
    // tri-state in sync with whichever individual row checkboxes are
    // ticked. Pure read of the DOM each time, no stored selection state —
    // so there's nothing to go stale if a row is (re)rendered.
    function updateBulkSelectionUI() {
        const boxes = document.querySelectorAll('.bulk-line-checkbox');
        const checked = document.querySelectorAll('.bulk-line-checkbox:checked');
        const toolbar = document.getElementById('bulk-actions-toolbar');
        const countLabel = document.getElementById('bulk-selected-count');
        if (countLabel) countLabel.textContent = checked.length;
        if (toolbar) toolbar.style.display = checked.length > 0 ? '' : 'none';

        const refundBtn = document.getElementById('bulk-refund-btn');
        const voidBtn = document.getElementById('bulk-void-btn');
        if (refundBtn) refundBtn.disabled = checked.length === 0;
        if (voidBtn) voidBtn.disabled = checked.length === 0;

        ['bulk-refund-count-label', 'bulk-void-count-label'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = checked.length > 0 ? '(' + checked.length + ' ticket' + (checked.length === 1 ? '' : 's') + ' selected)' : '';
        });

        const selectAll = document.getElementById('select-all-tickets');
        if (selectAll) {
            selectAll.checked = boxes.length > 0 && checked.length === boxes.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < boxes.length;
        }

        // Hide a bulk panel left open from a previous selection that's now empty.
        if (checked.length === 0) {
            ['bulk-refund-panel', 'bulk-void-panel'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.display = 'none';
            });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const selectAll = document.getElementById('select-all-tickets');
        if (selectAll) {
            selectAll.addEventListener('change', () => {
                document.querySelectorAll('.bulk-line-checkbox').forEach(cb => { cb.checked = selectAll.checked; });
                updateBulkSelectionUI();
            });
        }
    });

    // Blocks the submit (same pattern as the single-ticket confirm()
    // dialogs) if nothing is actually checked — belt-and-suspenders on
    // top of the server's own "line_ids required" validation, and gives
    // an immediate, specific message instead of a round-trip error.
    // ---- Refund / Void submit via AJAX: errors show inline, no page reload ----
    (function () {
        const n = v => parseFloat(v) || 0;
        const fmt = v => v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        // Live preview of what the refund/void will leave on the ticket.
        function updatePreview(form) {
            const preview = form.querySelector('.adjust-preview');
            if (!preview) return;
            const panel = form.closest('.ticket-adjust-panel');
            const total = n(panel.dataset.total);
            const val = name => n((form.querySelector('[name="' + name + '"]') || {}).value);
            if (preview.dataset.kind === 'refund') {
                const ded = val('refund_deduction_supplier') + val('refund_deduction_company');
                const refundAmt = val('refund_fare_amount') + val('refund_tax_amount') - ded;
                preview.innerHTML = 'Returned to customer: <strong>' + fmt(refundAmt) + '</strong> &nbsp;|&nbsp; Still charged to customer: <strong>' + fmt(total - refundAmt) + '</strong> (payable to the supplier — no profit)';
            } else {
                const ded = val('void_deduction_supplier') + val('void_deduction_company');
                preview.innerHTML = 'Charges deducted from customer: <strong>' + fmt(ded) + '</strong> &nbsp;|&nbsp; Returned to customer: <strong>' + fmt(total - ded) + '</strong> (ticket was ' + fmt(total) + ') &nbsp;|&nbsp; Charges go to the supplier — no profit';
            }
        }

        function showErrors(form, json, fallback) {
            const box = form.querySelector('.ajax-form-errors') || form.parentElement.querySelector('.ajax-form-errors');
            const msgs = [];
            Object.values((json && json.errors) || {}).forEach(list => (list || []).forEach(m => { if (!msgs.includes(m)) msgs.push(m); }));
            if (!msgs.length) msgs.push((json && json.message) || fallback || 'Something went wrong.');
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            Object.keys((json && json.errors) || {}).forEach(k => {
                const el = form.querySelector('[name="' + k + '"]') || document.querySelector('[form="' + form.id + '"][name="' + k + '"]');
                if (el) el.classList.add('is-invalid');
            });
            if (!box) { alert(msgs.join('\n')); return; }
            box.innerHTML = msgs.map(m => '<div></div>').join('');
            box.querySelectorAll('div').forEach((d, i) => { d.textContent = msgs[i]; });
            box.style.display = '';
            box.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        document.querySelectorAll('form[data-ajax-form]').forEach(form => {
            form.setAttribute('novalidate', 'novalidate');
            form.addEventListener('input', () => updatePreview(form));
            updatePreview(form);

            form.addEventListener('submit', async e => {
                e.preventDefault();
                const submitter = e.submitter;
                const action = (submitter && submitter.getAttribute('formaction')) || form.action;

                if (submitter && submitter.dataset.bulkKind) {
                    if (!confirmBulkAction(submitter.dataset.bulkKind)) return;
                } else if (form.dataset.confirm && !confirm(form.dataset.confirm)) {
                    return;
                }

                if (submitter) submitter.disabled = true;
                try {
                    const res = await fetch(action, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: new FormData(form),
                        credentials: 'same-origin',
                    });
                    let json = {};
                    try { json = await res.json(); } catch (_) {}
                    if (res.ok && json.redirect) { window.location.href = json.redirect; return; }
                    showErrors(form, json, res.status === 419 ? 'Session expired — refresh the page.' : null);
                } catch (err) {
                    showErrors(form, null, 'Could not reach the server: ' + err.message);
                }
                if (submitter) submitter.disabled = false;
            });
        });
    })();

    function confirmBulkAction(kind) {
        const checked = document.querySelectorAll('.bulk-line-checkbox:checked').length;
        if (checked === 0) {
            alert('Select at least one ticket first.');
            return false;
        }
        const verb = kind === 'refund' ? 'Refund' : 'Void';
        return confirm(verb + ' ' + checked + ' selected ticket(s)? Each can be reverted later.');
    }
</script>
@endsection
