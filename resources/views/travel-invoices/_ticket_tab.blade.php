{{--
    Tour Invoice → Ticket tab. Identical to the Ticket Sale Invoice form
    (Master Details + one grid row per passenger + Summary), generated from
    resources/views/ticket-invoices/_form.blade.php with "tkm-" ids so it
    can live beside the other tour tabs. Each grid row is saved as one
    "ticket" ServiceLine (description = passenger name) whose detail holds
    the same fields as a TicketSaleInvoiceLine; ServiceLineWriter prices it
    with TicketSaleInvoiceLine's own math. Script: _ticket_tab_script.
--}}
@php
    $tkSaleLines = collect();
    if ($invoice) {
        $tkSaleLines = $invoice->serviceLines->where('service_type', 'ticket')->values()->map(function ($line) {
            $d = $line->ticketDetail;
            $row = ['pax_name' => $line->description, 'supplier_id' => $line->supplier_id];
            if (!$d) {
                return $row + ['fare_amount' => (float) $line->receivable_l_amount];
            }
            $row += [
                'pax_type' => $d->pax_type ?: 'adult', 'ticket_no' => $d->ticket_no, 'pnr' => $d->pnr,
                'airline_id' => $d->airline_id, 'ticket_type' => $d->ticket_type ?: 'international',
                'trip_type' => $d->trip_type ?: 'one_way',
                'leg1_from' => $d->leg1_from, 'leg1_stay' => $d->leg1_stay, 'leg1_to' => $d->leg1_to,
                'leg1_departure_date' => optional($d->leg1_departure_date)->format('Y-m-d'),
                'leg1_departure_time' => $d->leg1_departure_time ? substr($d->leg1_departure_time, 0, 5) : null,
                'leg2_from' => $d->leg2_from, 'leg2_stay' => $d->leg2_stay, 'leg2_to' => $d->leg2_to,
                'leg2_departure_date' => optional($d->leg2_departure_date)->format('Y-m-d'),
                'leg2_departure_time' => $d->leg2_departure_time ? substr($d->leg2_departure_time, 0, 5) : null,
                'sales_agent_id' => $d->sales_agent_id, 'psf_basis' => $d->psf_basis ?: 'fare',
            ];
            if ($d->hasTicketSalePricing()) {
                foreach (\App\Models\InvoiceTicketDetail::PRICING_FIELDS as $f) {
                    $row[$f] = $d->{$f};
                }
            } else {
                // Ticket saved before this tab matched the Ticket Sale Invoice
                // (only Receivable/Payable): carry it over as Fare = Receivable
                // and Commission (amount) = Receivable − Payable, which keeps
                // the same Receivable, Payable and Income.
                $recv = (float) $line->receivable_l_amount;
                $pay = (float) $line->payable_l_amount;
                $row['fare_amount'] = $recv;
                $row['commission_amount'] = max(0, round($recv - $pay, 2));
                $row['commission_input_mode'] = 'amount';
            }

            return $row;
        });
    }
    $tkQuotationLines = (!$invoice && $quotation)
        ? $quotation->serviceLines->where('service_type', 'ticket')->map(fn ($l) => [
            'supplier_id' => $l->supplier_id,
            'fare_amount' => round((float) $l->receivable_f_amount * (float) ($l->exchange_rate ?: 1), 2),
        ])->values()
        : collect();
    $tkJsSuppliers = $suppliers->map(fn ($s) => ['id' => $s->id, 'name' => ($s->is_flagged ? '⚠ ' : '') . $s->name])->values();
    $tkJsAirlines = $airlines->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'code' => $a->numeric_code])->values();
    $tkJsAgents = $staffUsers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values();
@endphp
<script>
    window.TOUR_TICKET_DATA = {
        suppliers: @json($tkJsSuppliers),
        airlines: @json($tkJsAirlines),
        agents: @json($tkJsAgents),
        paxTypes: @json($paxTypes),
        existingLines: @json($tkSaleLines),
        quotationLines: @json($tkQuotationLines),
    };
</script>

<div id="tkm-root">
<div id="tkm-lines-differ-warning" class="alert alert-warning" style="display:none;">
    This invoice's existing tickets don't all share the same charges below (they were likely entered individually before this form changed). Master Details is currently showing the <strong>first</strong> ticket's figures. As soon as you change anything in Master Details, <strong>every ticket on this invoice will be updated to match it</strong>.
</div>

<section class="card mb-3">
    <header class="card-header"><h3 class="card-title h6 mb-0">Master Details <small class="text-muted">— shared by every ticket below</small></h3></header>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-2">
                <label class="form-label">Supplier</label>
                <select class="form-control tkm-select2" id="tkm-supplier"><option value="">— none —</option></select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Airline</label>
                <select class="form-control tkm-select2" id="tkm-airline"><option value="">— auto from first ticket # —</option></select>
                <small class="text-muted d-block" id="tkm-airline-hint" style="min-height:1.3em;"></small>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">PNR</label>
                <input type="text" class="form-control" id="tkm-pnr">
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Ticket Type</label>
                <select class="form-control" id="tkm-ticket-type">
                    <option value="domestic">Domestic</option>
                    <option value="international" selected>International</option>
                </select>
            </div>
            <div class="col-md-1 mb-2">
                <label class="form-label">Trip Type</label>
                <select class="form-control px-1" id="tkm-trip-type">
                    <option value="one_way">One Way</option>
                    <option value="return">Return</option>
                </select>
            </div>
        </div>
        {{-- Cities: From - Stay(s) - To. "Connected flights" can have any number of
             Stay cities — each extra one is saved joined with "-" inside the
             existing Stay column (From-Stay1-Stay2-To), so nothing else that
             reads the cities needs to change. --}}
        <div class="row">
            <div class="col-md-12 mb-2">
                <label class="form-label">Cities (From - Stay - To) <small class="text-muted">— use <strong>+ City</strong> for connected flights</small></label>
                <div class="d-flex flex-wrap align-items-center gap-1" id="tkm-leg1-route" data-leg="leg1">
                    <input type="text" class="form-control form-control-sm" style="width:150px" id="tkm-leg1-from" placeholder="From">
                    <span class="route-stays" style="display:contents"></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary route-add-btn">+ City</button>
                    <input type="text" class="form-control form-control-sm" style="width:150px" id="tkm-leg1-to" placeholder="To">
                    <input type="hidden" id="tkm-leg1-stay">
                    <span class="ms-2 text-muted small">Departure</span>
                    <input type="date" class="form-control form-control-sm" style="width:150px" id="tkm-leg1-dep-date" title="Departure Date">
                    <input type="time" class="form-control form-control-sm" style="width:115px" id="tkm-leg1-dep-time" title="Departure Time">
                </div>
                <div class="flex-wrap align-items-center gap-1 mt-1" id="tkm-leg2-wrap" style="display:none;" data-leg="leg2">
                    <input type="text" class="form-control form-control-sm" style="width:150px" id="tkm-leg2-from" placeholder="From (return)">
                    <span class="route-stays" style="display:contents"></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary route-add-btn">+ City</button>
                    <input type="text" class="form-control form-control-sm" style="width:150px" id="tkm-leg2-to" placeholder="To (return)">
                    <input type="hidden" id="tkm-leg2-stay">
                    <span class="ms-2 text-muted small">Departure</span>
                    <input type="date" class="form-control form-control-sm" style="width:150px" id="tkm-leg2-dep-date" title="Return Departure Date">
                    <input type="time" class="form-control form-control-sm" style="width:115px" id="tkm-leg2-dep-time" title="Return Departure Time">
                </div>
            </div>
        </div>
        {{-- Every %/Amount pair is TWO separate inputs (client fix): type in
             either one and the other is calculated; the side you typed last
             is kept exact (its hidden *-mode input records which). --}}
        <div class="row">
            <div class="col-md-2 mb-2">
                <label class="form-label">Fare Amount</label>
                <input type="number" step="any" min="0" class="form-control" id="tkm-fare" value="0">
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Tax Amount</label>
                <input type="number" step="any" min="0" class="form-control" id="tkm-tax" value="0">
            </div>
            <div class="col-md-4 mb-2 pair" data-pair="apt">
                <label class="form-label">APT <small class="text-muted">(on fare)</small></label>
                <div class="input-group">
                    <input type="number" step="any" min="0" class="form-control pair-pct" id="tkm-apt-pct" value="0" aria-label="APT %">
                    <span class="input-group-text">%</span>
                    <input type="number" step="any" min="0" class="form-control pair-amt" id="tkm-apt-amt" value="0" aria-label="APT Amount">
                    <span class="input-group-text">Amt</span>
                </div>
                <input type="hidden" id="tkm-apt-mode" value="percent">
            </div>
            <div class="col-md-4 mb-2 pair" data-pair="commission">
                <label class="form-label">Commission <small class="text-muted">(on fare, from airline)</small></label>
                <div class="input-group">
                    <input type="number" step="any" min="0" class="form-control pair-pct" id="tkm-commission-pct" value="0" aria-label="Commission %">
                    <span class="input-group-text">%</span>
                    <input type="number" step="any" min="0" class="form-control pair-amt" id="tkm-commission-amt" value="0" aria-label="Commission Amount">
                    <span class="input-group-text">Amt</span>
                </div>
                <input type="hidden" id="tkm-commission-mode" value="percent">
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-2 pair" data-pair="wht">
                <label class="form-label">WHT <small class="text-muted">(on commission)</small></label>
                <div class="input-group">
                    <input type="number" step="any" min="0" class="form-control pair-pct" id="tkm-wht-pct" value="0" aria-label="WHT %">
                    <span class="input-group-text">%</span>
                    <input type="number" step="any" min="0" class="form-control pair-amt" id="tkm-wht-amt" value="0" aria-label="WHT Amount">
                    <span class="input-group-text">Amt</span>
                </div>
                <input type="hidden" id="tkm-wht-mode" value="percent">
            </div>
            <div class="col-md-4 mb-2 pair" data-pair="psf">
                <label class="form-label d-flex justify-content-between align-items-center">
                    <span>PSF</span>
                    <select class="form-control form-control-sm w-auto py-0" id="tkm-psf-basis">
                        <option value="fare">on Fare Amount</option>
                        <option value="total">on Fare + Tax + APT</option>
                    </select>
                </label>
                <div class="input-group">
                    <input type="number" step="any" min="0" class="form-control pair-pct" id="tkm-psf-pct" value="0" aria-label="PSF %">
                    <span class="input-group-text">%</span>
                    <input type="number" step="any" min="0" class="form-control pair-amt" id="tkm-psf-amt" value="0" aria-label="PSF Amount">
                    <span class="input-group-text">Amt</span>
                </div>
                <input type="hidden" id="tkm-psf-mode" value="percent">
            </div>
            <div class="col-md-4 mb-2 pair" data-pair="discount">
                <label class="form-label">Discount <small class="text-muted">(aero adventure, on fare)</small></label>
                <div class="input-group">
                    <input type="number" step="any" min="0" class="form-control pair-pct" id="tkm-discount-pct" value="0" aria-label="Discount %">
                    <span class="input-group-text">%</span>
                    <input type="number" step="any" min="0" class="form-control pair-amt" id="tkm-discount-amt" value="0" aria-label="Discount Amount">
                    <span class="input-group-text">Amt</span>
                </div>
                <input type="hidden" id="tkm-discount-mode" value="percent">
            </div>
        </div>
        <div class="row">
            <div class="col-md-3 mb-2">
                <label class="form-label">Sale Agent (SPO)</label>
                <select class="form-control tkm-select2" id="tkm-agent"><option value="">— none —</option></select>
            </div>
            <div class="col-md-4 mb-2 pair" data-pair="agent_commission">
                <label class="form-label">Sale Commission <small class="text-muted">(on PSF amount)</small></label>
                <div class="input-group">
                    <input type="number" step="any" min="0" class="form-control pair-pct" id="tkm-agent_commission-pct" value="0" aria-label="Sale Commission %">
                    <span class="input-group-text">%</span>
                    <input type="number" step="any" min="0" class="form-control pair-amt" id="tkm-agent_commission-amt" value="0" aria-label="Sale Commission Amount">
                    <span class="input-group-text">Amt</span>
                </div>
                <input type="hidden" id="tkm-agent_commission-mode" value="percent">
            </div>
            <div class="col-md-2 mb-2 ms-auto text-end">
                <label class="form-label d-block">Ticket Total</label>
                <h5 class="mb-0" id="tkm-total-amount">0.00</h5>
            </div>
        </div>
        <small class="text-muted">Type either the % or the amount — the other is calculated. The calculated side is shaded.</small>
    </div>
</section>

<section class="card mb-3">
    <header class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title h6 mb-0">Tickets <small class="text-muted">— one row per passenger</small></h3>
        <button type="button" class="btn btn-sm btn-outline-primary" id="tkm-add-ticket-btn"><i class="fa fa-plus"></i> Add Ticket</button>
    </header>
    <div class="card-body">
        <p class="text-muted small mb-2">Enter the full 13-digit Ticket # (e.g. <code>220-1234-567-890</code>) on the <strong>first</strong> ticket — every other row auto-fills from it, in sequence (084-1121-222-<strong>122</strong>, 123, 124, ...). Works regardless of order: rows added before ticket #1 is finished fill in as soon as it is, and correcting ticket #1 re-numbers every row that hasn't been typed into by hand.</p>
        <div class="table-scroll">
        <table class="table table-bordered table-sm mb-0" id="tkm-tickets-table">
            <thead>
                <tr>
                    <th style="width:3%;">#</th>
                    <th>Passenger Name</th>
                    <th style="width:14%;">Passenger Type</th>
                    <th style="width:18%;">Ticket #</th>
                    <th style="width:12%;" class="text-end">Amount</th>
                    <th style="width:5%;"></th>
                </tr>
            </thead>
            <tbody id="tkm-tickets-wrap"></tbody>
        </table>
        </div>
    </div>
</section>

<section class="card mb-3" id="tkm-ticket-summary">
    <header class="card-header"><h3 class="card-title h6 mb-0">Summary <small class="text-muted">— <span id="tkm-ticket-count">0</span> ticket(s)</small></h3></header>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm mb-2">
                    <tr><td>Ticket Total (Customer Net) <small class="text-muted">per ticket</small></td><th class="text-end" id="tkm-per-ticket-total">0.00</th></tr>
                    <tr class="table-light"><td><strong>Customer Total</strong> <small class="text-muted">Ticket Total &times; tickets</small></td><th class="text-end fs-6" id="tkm-grand-total">0.00</th></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm mb-2">
                    <tr><td>Supplier Net <small class="text-muted">per ticket</small></td><th class="text-end" id="tkm-s-supplier-net">0.00</th></tr>
                    <tr class="table-light"><td><strong>Supplier Total</strong> <small class="text-muted">Net &times; tickets</small></td><th class="text-end fs-6" id="tkm-s-supplier-total">0.00</th></tr>
                </table>
            </div>
        </div>
        <small class="text-muted d-block">Supplier Net = Fare + Tax + APT − Commission + WHT. Ticket Total (Customer Net) = Fare + Tax + APT + PSF − Discount.</small>
    </div>
</section>

<template id="tkm-ticket-row-tpl">
    <tr class="ticket-row">
        <td class="index-cell align-middle"><span class="ticket-index-label"></span></td>
        <td>
            <input type="hidden" class="f-id">
            <input type="text" class="form-control form-control-sm f-pax-name" placeholder="Passenger name">
        </td>
        <td><select class="form-control form-control-sm f-pax-type"></select></td>
        <td>
            <input type="text" class="form-control form-control-sm f-ticket-no" placeholder="220-1234-567-890" maxlength="16" inputmode="numeric" title="13-digit ticket number">
        </td>
        <td class="text-end align-middle f-row-amount">0.00</td>
        <td class="text-center align-middle">
            <button type="button" class="btn btn-sm btn-outline-danger remove-ticket-btn"><i class="fa fa-trash"></i></button>
        </td>
    </tr>
</template>

</div>

<style>
    #tkm-root .pair .is-derived { background-color: #f3f5f7; }
    #tkm-root .select2-container { width: 100% !important; }
</style>
