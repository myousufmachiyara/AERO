@php
    $invoice = $invoice ?? null;
    $quotation = $quotation ?? null;
    $existingLines = $invoice ? $invoice->lines->where('status', 'active')->values() : collect();
    $lockedLines = $invoice ? $invoice->lines->where('status', '!=', 'active')->values() : collect();
@endphp

<div id="ticket-form-errors" class="alert alert-danger" style="display:none;" role="alert"></div>
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

{{--
    Master/Grid layout per client feedback: this invoice is almost always
    one group of passengers travelling together on the same sector, same
    fare, same supplier — so those shared charges are entered ONCE in
    "Master Details" below, and the per-ticket grid only asks for what
    actually varies per passenger (name, type, ticket #). Every ticket on
    the invoice is created with the master's charge figures; the grand
    total is simply that one ticket's total times the number of tickets.
--}}
<div class="row mb-3">
    <div class="col-md-3 mb-2">
        <label class="form-label">Date <span class="text-danger">*</span></label>
        <input type="date" name="invoice_date" class="form-control"
               value="{{ old('invoice_date', $invoice?->invoice_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
    </div>
    <div class="col-md-3 mb-2">
        <label class="form-label">Adjustment Date</label>
        <input type="date" name="adjustment_date" class="form-control"
               value="{{ old('adjustment_date', optional($invoice?->adjustment_date)->format('Y-m-d')) }}">
        <small class="text-muted">Ledger posting date, if different from the date above.</small>
    </div>
    <div class="col-md-3 mb-2">
        <label class="form-label">Customer <span class="text-danger">*</span></label>
        <select name="customer_id" class="form-control ti-select2" id="customer-select">
            <option value="">Select customer...</option>
            @foreach($customers as $c)
            <option value="{{ $c->id }}" @selected(old('customer_id', $invoice?->customer_id ?? $quotation?->customer_id ?? null) == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 mb-2">
        <label class="form-label">Remarks</label>
        <input type="text" name="remarks" class="form-control" value="{{ old('remarks', $invoice?->remarks ?? '') }}">
    </div>
    @if($quotation)
    <input type="hidden" name="quotation_id" value="{{ $quotation->id }}">
    @endif
</div>

@if($lockedLines->isNotEmpty())
<div class="alert alert-secondary">
    <strong>{{ $lockedLines->count() }}</strong> ticket(s) on this invoice are already refunded/voided and are not shown here for editing — see the invoice page for their history.
</div>
@endif

<div id="lines-differ-warning" class="alert alert-warning" style="display:none;">
    This invoice's existing tickets don't all share the same charges below (they were likely entered individually before this form changed). Master Details is currently showing the <strong>first</strong> ticket's figures. As soon as you change anything in Master Details, <strong>every ticket on this invoice will be updated to match it</strong> — if that's not what you want, leave Master Details alone and only edit passenger names/types/ticket numbers in the grid.
</div>

<section class="card mb-3">
    <header class="card-header"><h3 class="card-title h6 mb-0">Master Details <small class="text-muted">— shared by every ticket below</small></h3></header>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-2">
                <label class="form-label">Supplier</label>
                <select class="form-control ti-select2" id="m-supplier"><option value="">— none —</option></select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Airline</label>
                <select class="form-control ti-select2" id="m-airline"><option value="">— auto from first ticket # —</option></select>
                <small class="text-muted d-block" id="m-airline-hint" style="min-height:1.3em;"></small>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">PNR</label>
                <input type="text" class="form-control" id="m-pnr">
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Ticket Type</label>
                <select class="form-control" id="m-ticket-type">
                    <option value="domestic">Domestic</option>
                    <option value="international" selected>International</option>
                </select>
            </div>
            <div class="col-md-1 mb-2">
                <label class="form-label">Trip Type</label>
                <select class="form-control px-1" id="m-trip-type">
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
                <div class="d-flex flex-wrap align-items-center gap-1" id="m-leg1-route" data-leg="leg1">
                    <input type="text" class="form-control form-control-sm" style="width:150px" id="m-leg1-from" placeholder="From">
                    <span class="route-stays" style="display:contents"></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary route-add-btn">+ City</button>
                    <input type="text" class="form-control form-control-sm" style="width:150px" id="m-leg1-to" placeholder="To">
                    <input type="hidden" id="m-leg1-stay">
                    <span class="ms-2 text-muted small">Departure</span>
                    <input type="date" class="form-control form-control-sm" style="width:150px" id="m-leg1-dep-date" title="Departure Date">
                    <input type="time" class="form-control form-control-sm" style="width:115px" id="m-leg1-dep-time" title="Departure Time">
                </div>
                <div class="flex-wrap align-items-center gap-1 mt-1" id="m-leg2-wrap" style="display:none;" data-leg="leg2">
                    <input type="text" class="form-control form-control-sm" style="width:150px" id="m-leg2-from" placeholder="From (return)">
                    <span class="route-stays" style="display:contents"></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary route-add-btn">+ City</button>
                    <input type="text" class="form-control form-control-sm" style="width:150px" id="m-leg2-to" placeholder="To (return)">
                    <input type="hidden" id="m-leg2-stay">
                    <span class="ms-2 text-muted small">Departure</span>
                    <input type="date" class="form-control form-control-sm" style="width:150px" id="m-leg2-dep-date" title="Return Departure Date">
                    <input type="time" class="form-control form-control-sm" style="width:115px" id="m-leg2-dep-time" title="Return Departure Time">
                </div>
            </div>
        </div>
        {{-- Every %/Amount pair is TWO separate inputs (client fix): type in
             either one and the other is calculated; the side you typed last
             is kept exact (its hidden *-mode input records which). --}}
        <div class="row">
            <div class="col-md-2 mb-2">
                <label class="form-label">Fare Amount</label>
                <input type="number" step="any" min="0" class="form-control" id="m-fare" value="0">
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Tax Amount</label>
                <input type="number" step="any" min="0" class="form-control" id="m-tax" value="0">
            </div>
            <div class="col-md-4 mb-2 pair" data-pair="apt">
                <label class="form-label">APT <small class="text-muted">(on fare)</small></label>
                <div class="input-group">
                    <input type="number" step="any" min="0" class="form-control pair-pct" id="m-apt-pct" value="0" aria-label="APT %">
                    <span class="input-group-text">%</span>
                    <input type="number" step="any" min="0" class="form-control pair-amt" id="m-apt-amt" value="0" aria-label="APT Amount">
                    <span class="input-group-text">Amt</span>
                </div>
                <input type="hidden" id="m-apt-mode" value="percent">
            </div>
            <div class="col-md-4 mb-2 pair" data-pair="commission">
                <label class="form-label">Commission <small class="text-muted">(on fare, from airline)</small></label>
                <div class="input-group">
                    <input type="number" step="any" min="0" class="form-control pair-pct" id="m-commission-pct" value="0" aria-label="Commission %">
                    <span class="input-group-text">%</span>
                    <input type="number" step="any" min="0" class="form-control pair-amt" id="m-commission-amt" value="0" aria-label="Commission Amount">
                    <span class="input-group-text">Amt</span>
                </div>
                <input type="hidden" id="m-commission-mode" value="percent">
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-2 pair" data-pair="wht">
                <label class="form-label">WHT <small class="text-muted">(on commission)</small></label>
                <div class="input-group">
                    <input type="number" step="any" min="0" class="form-control pair-pct" id="m-wht-pct" value="0" aria-label="WHT %">
                    <span class="input-group-text">%</span>
                    <input type="number" step="any" min="0" class="form-control pair-amt" id="m-wht-amt" value="0" aria-label="WHT Amount">
                    <span class="input-group-text">Amt</span>
                </div>
                <input type="hidden" id="m-wht-mode" value="percent">
            </div>
            <div class="col-md-4 mb-2 pair" data-pair="psf">
                <label class="form-label d-flex justify-content-between align-items-center">
                    <span>PSF</span>
                    <select class="form-control form-control-sm w-auto py-0" id="m-psf-basis">
                        <option value="fare">on Fare Amount</option>
                        <option value="total">on Fare + Tax + APT</option>
                    </select>
                </label>
                <div class="input-group">
                    <input type="number" step="any" min="0" class="form-control pair-pct" id="m-psf-pct" value="0" aria-label="PSF %">
                    <span class="input-group-text">%</span>
                    <input type="number" step="any" min="0" class="form-control pair-amt" id="m-psf-amt" value="0" aria-label="PSF Amount">
                    <span class="input-group-text">Amt</span>
                </div>
                <input type="hidden" id="m-psf-mode" value="percent">
            </div>
            <div class="col-md-4 mb-2 pair" data-pair="discount">
                <label class="form-label">Discount <small class="text-muted">(aero adventure, on fare)</small></label>
                <div class="input-group">
                    <input type="number" step="any" min="0" class="form-control pair-pct" id="m-discount-pct" value="0" aria-label="Discount %">
                    <span class="input-group-text">%</span>
                    <input type="number" step="any" min="0" class="form-control pair-amt" id="m-discount-amt" value="0" aria-label="Discount Amount">
                    <span class="input-group-text">Amt</span>
                </div>
                <input type="hidden" id="m-discount-mode" value="percent">
            </div>
        </div>
        <div class="row">
            <div class="col-md-3 mb-2">
                <label class="form-label">Sale Agent (SPO)</label>
                <select class="form-control ti-select2" id="m-agent"><option value="">— none —</option></select>
            </div>
            <div class="col-md-4 mb-2 pair" data-pair="agent_commission">
                <label class="form-label">Sale Commission <small class="text-muted">(on PSF amount)</small></label>
                <div class="input-group">
                    <input type="number" step="any" min="0" class="form-control pair-pct" id="m-agent_commission-pct" value="0" aria-label="Sale Commission %">
                    <span class="input-group-text">%</span>
                    <input type="number" step="any" min="0" class="form-control pair-amt" id="m-agent_commission-amt" value="0" aria-label="Sale Commission Amount">
                    <span class="input-group-text">Amt</span>
                </div>
                <input type="hidden" id="m-agent_commission-mode" value="percent">
            </div>
            <div class="col-md-2 mb-2 ms-auto text-end">
                <label class="form-label d-block">Ticket Total</label>
                <h5 class="mb-0" id="m-total-amount">0.00</h5>
            </div>
        </div>
        <small class="text-muted">Type either the % or the amount — the other is calculated. The calculated side is shaded.</small>
    </div>
</section>

<section class="card mb-3">
    <header class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title h6 mb-0">Tickets <small class="text-muted">— one row per passenger</small></h3>
        <button type="button" class="btn btn-sm btn-outline-primary" id="add-ticket-btn"><i class="fa fa-plus"></i> Add Ticket</button>
    </header>
    <div class="card-body">
        <p class="text-muted small mb-2">Enter the full 13-digit Ticket # (e.g. <code>220-1234-567-890</code>) on the <strong>first</strong> ticket — every other row auto-fills from it, in sequence (084-1121-222-<strong>122</strong>, 123, 124, ...). Works regardless of order: rows added before ticket #1 is finished fill in as soon as it is, and correcting ticket #1 re-numbers every row that hasn't been typed into by hand.</p>
        <div class="table-scroll">
        <table class="table table-bordered table-sm mb-0" id="tickets-table">
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
            <tbody id="tickets-wrap"></tbody>
        </table>
        </div>
    </div>
</section>

<section class="card mb-3" id="ticket-summary">
    <header class="card-header"><h3 class="card-title h6 mb-0">Summary <small class="text-muted">— <span id="ticket-count">0</span> ticket(s)</small></h3></header>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm mb-2">
                    <tr><td>Ticket Total (Customer Net) <small class="text-muted">per ticket</small></td><th class="text-end" id="per-ticket-total">0.00</th></tr>
                    <tr class="table-light"><td><strong>Customer Total</strong> <small class="text-muted">Ticket Total &times; tickets</small></td><th class="text-end fs-6" id="grand-total">0.00</th></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm mb-2">
                    <tr><td>Supplier Net <small class="text-muted">per ticket</small></td><th class="text-end" id="s-supplier-net">0.00</th></tr>
                    <tr class="table-light"><td><strong>Supplier Total</strong> <small class="text-muted">Net &times; tickets</small></td><th class="text-end fs-6" id="s-supplier-total">0.00</th></tr>
                </table>
            </div>
        </div>
        <small class="text-muted d-block">Supplier Net = Fare + Tax + APT − Commission + WHT. Ticket Total (Customer Net) = Fare + Tax + APT + PSF − Discount.</small>
    </div>
</section>

{{-- Reference data used by JS below --}}
@php
    $jsSuppliers = $suppliers->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values();
    $jsAirlines = $airlines->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'code' => $a->numeric_code])->values();
    $jsAgents = $agents->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values();
    $jsExistingLines = $existingLines->map(function ($l) {
        return [
            'id' => $l->id,
            'supplier_id' => $l->supplier_id,
            'airline_id' => $l->airline_id,
            'pax_name' => $l->pax_name,
            'pax_type' => $l->pax_type,
            'pnr' => $l->pnr,
            'ticket_no' => $l->ticket_no,
            'trip_type' => $l->trip_type, 'ticket_type' => $l->ticket_type,
            'leg1_from' => $l->leg1_from, 'leg1_stay' => $l->leg1_stay, 'leg1_to' => $l->leg1_to,
            'leg1_departure_date' => optional($l->leg1_departure_date)->format('Y-m-d'), 'leg1_departure_time' => $l->leg1_departure_time ? substr($l->leg1_departure_time, 0, 5) : null,
            'leg2_from' => $l->leg2_from, 'leg2_stay' => $l->leg2_stay, 'leg2_to' => $l->leg2_to,
            'leg2_departure_date' => optional($l->leg2_departure_date)->format('Y-m-d'), 'leg2_departure_time' => $l->leg2_departure_time ? substr($l->leg2_departure_time, 0, 5) : null,
            'fare_amount' => $l->fare_amount, 'tax_amount' => $l->tax_amount, 'apt_percent' => $l->apt_percent,
            'apt_charges' => $l->apt_charges, 'apt_input_mode' => $l->apt_input_mode,
            'commission_percent' => $l->commission_percent, 'commission_amount' => $l->commission_amount,
            'commission_input_mode' => $l->commission_input_mode,
            'wht_percent' => $l->wht_percent, 'wht_amount' => $l->wht_amount, 'wht_input_mode' => $l->wht_input_mode,
            'psf_percent' => $l->psf_percent, 'psf_amount' => $l->psf_amount, 'psf_basis' => $l->psf_basis,
            'psf_input_mode' => $l->psf_input_mode,
            'discount_percent' => $l->discount_percent, 'discount_amount' => $l->discount_amount,
            'discount_input_mode' => $l->discount_input_mode,
            'sales_agent_id' => $l->sales_agent_id, 'agent_commission_percent' => $l->agent_commission_percent,
            'agent_commission_amount' => $l->agent_commission_amount,
            'agent_commission_input_mode' => $l->agent_commission_input_mode,
        ];
    })->values();
    // Non-JS fallback: after a server-side validation redirect, re-fill the
    // form from what was submitted instead of the saved invoice.
    if (is_array(old('lines'))) {
        $jsExistingLines = collect(old('lines'))->values();
    }
    $jsQuotationLines = $quotation
        ? $quotation->serviceLines->where('service_type', 'ticket')->map(fn ($l) => [
            'supplier_id' => $l->supplier_id,
            'fare_amount' => $l->receivable_f_amount,
        ])->values()
        : collect();
@endphp
<script>
    window.TICKET_FORM_DATA = {
        suppliers: @json($jsSuppliers),
        airlines: @json($jsAirlines),
        agents: @json($jsAgents),
        paxTypes: @json($paxTypes),
        existingLines: @json($jsExistingLines),
        quotationLines: @json($jsQuotationLines),
    };
</script>

<template id="ticket-row-tpl">
    <tr class="ticket-row">
        <td class="index-cell align-middle"><span class="ticket-index-label"></span></td>
        <td>
            <input type="hidden" class="f-id">
            <input type="text" class="form-control form-control-sm f-pax-name" placeholder="Passenger name">
        </td>
        <td><select class="form-control form-control-sm f-pax-type"></select></td>
        <td>
            <input type="text" class="form-control form-control-sm f-ticket-no" placeholder="220-1234-567-890" maxlength="16">
        </td>
        <td class="text-end align-middle f-row-amount">0.00</td>
        <td class="text-center align-middle">
            <button type="button" class="btn btn-sm btn-outline-danger remove-ticket-btn"><i class="fa fa-trash"></i></button>
        </td>
    </tr>
</template>

<style>
    #ticket-form-root .pair .is-derived { background-color: #f3f5f7; }
    #ticket-form-root .select2-container { width: 100% !important; }
</style>

<script>
(function () {
    const data = window.TICKET_FORM_DATA;
    const wrap = document.getElementById('tickets-wrap');
    const tpl = document.getElementById('ticket-row-tpl');
    const round2 = n => Math.round((n + Number.EPSILON) * 100) / 100;
    const num = id => parseFloat(document.getElementById(id).value) || 0;
    let idx = 0;

    // Fields shared by every ticket on the invoice — entered once in
    // Master Details, copied into every row's hidden inputs so the
    // server-side payload (lines[i][field]) is one full line per ticket.
    const SHARED_FIELD_IDS = {
        supplier_id: 'm-supplier', airline_id: 'm-airline', pnr: 'm-pnr', trip_type: 'm-trip-type',
        ticket_type: 'm-ticket-type',
        leg1_from: 'm-leg1-from', leg1_stay: 'm-leg1-stay', leg1_to: 'm-leg1-to',
        leg2_from: 'm-leg2-from', leg2_stay: 'm-leg2-stay', leg2_to: 'm-leg2-to',
        leg1_departure_date: 'm-leg1-dep-date', leg1_departure_time: 'm-leg1-dep-time',
        leg2_departure_date: 'm-leg2-dep-date', leg2_departure_time: 'm-leg2-dep-time',
        fare_amount: 'm-fare', tax_amount: 'm-tax',
        apt_percent: 'm-apt-pct', apt_charges: 'm-apt-amt', apt_input_mode: 'm-apt-mode',
        commission_percent: 'm-commission-pct', commission_amount: 'm-commission-amt', commission_input_mode: 'm-commission-mode',
        wht_percent: 'm-wht-pct', wht_amount: 'm-wht-amt', wht_input_mode: 'm-wht-mode',
        psf_percent: 'm-psf-pct', psf_amount: 'm-psf-amt', psf_input_mode: 'm-psf-mode', psf_basis: 'm-psf-basis',
        discount_percent: 'm-discount-pct', discount_amount: 'm-discount-amt', discount_input_mode: 'm-discount-mode',
        sales_agent_id: 'm-agent',
        agent_commission_percent: 'm-agent_commission-pct', agent_commission_amount: 'm-agent_commission-amt',
        agent_commission_input_mode: 'm-agent_commission-mode',
    };
    const SHARED_FIELDS = Object.keys(SHARED_FIELD_IDS);

    // The two-way %/amount pairs, IN CALCULATION ORDER (each basis only
    // depends on pairs above it) — mirrors TicketSaleInvoiceLine::recalculate().
    const PAIRS = [
        { key: 'apt',              basis: v => v.fare },
        { key: 'psf',              basis: v => document.getElementById('m-psf-basis').value === 'total' ? round2(v.fare + v.tax + v.apt) : v.fare },
        { key: 'discount',         basis: v => v.fare },
        { key: 'commission',       basis: v => v.fare },
        { key: 'wht',              basis: v => v.commission },
        { key: 'agent_commission', basis: v => v.psf },
    ];
    const pairEl = (key, part) => document.getElementById('m-' + key + '-' + part);

    function fillSelect(select, items, valueKey, labelFn, placeholder) {
        select.innerHTML = '';
        if (placeholder) {
            const o = document.createElement('option');
            o.value = '';
            o.textContent = placeholder;
            select.appendChild(o);
        }
        items.forEach(item => {
            const o = document.createElement('option');
            o.value = item[valueKey];
            o.textContent = labelFn(item);
            select.appendChild(o);
        });
    }

    // Sets a <select>'s value and refreshes its select2 display (if any).
    function setSelect(id, value) {
        const el = document.getElementById(id);
        el.value = value ?? '';
        if (window.jQuery && window.jQuery.fn.select2 && window.jQuery(el).data('select2')) {
            window.jQuery(el).trigger('change.select2');
        }
    }

    function formatTicketNo(raw) {
        const digits = (raw || '').replace(/\D/g, '').slice(0, 13);
        const segs = [3, 4, 3, 3];
        let out = [], p = 0;
        segs.forEach(len => { out.push(digits.slice(p, p + len)); p += len; });
        return out.filter(s => s.length).join('-');
    }

    function digitsOf(ticketNo) {
        return (ticketNo || '').replace(/\D/g, '');
    }

    // Auto-sequencing: increments the full 13-digit number, so groups past
    // 9 passengers roll over correctly (...009 -> ...010).
    function incrementTicketNo(baseDigits, offset) {
        if (!baseDigits || baseDigits.length !== 13 || offset === 0) {
            return baseDigits && baseDigits.length === 13 ? formatTicketNo(baseDigits) : '';
        }
        const n = parseInt(baseDigits, 10) + offset;
        let s = String(n);
        if (s.length > 13) return '';
        while (s.length < 13) s = '0' + s;
        return formatTicketNo(s);
    }

    function firstRowTicketDigits() {
        const firstRow = wrap.querySelector('.ticket-row');
        if (!firstRow) return '';
        return digitsOf(firstRow.querySelector('.f-ticket-no').value);
    }

    // ---- Cities: From - Stay(s) - To, any number of Stay cities ----
    function routeEls(leg) {
        const wrapEl = document.querySelector('[data-leg="' + leg + '"]');
        return { wrapEl, holder: wrapEl.querySelector('.route-stays'), hidden: document.getElementById('m-' + leg + '-stay') };
    }

    function syncRouteHidden(leg) {
        const { holder, hidden } = routeEls(leg);
        hidden.value = [...holder.querySelectorAll('.route-stay-input')].map(i => i.value.trim()).filter(Boolean).join('-');
        masterChanged();
    }

    function addStayInput(leg, value) {
        const { holder } = routeEls(leg);
        const box = document.createElement('span');
        box.className = 'd-inline-flex align-items-center gap-1';
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control form-control-sm route-stay-input';
        input.style.width = '150px';
        input.placeholder = 'Stay';
        input.value = value || '';
        input.addEventListener('input', () => syncRouteHidden(leg));
        box.appendChild(input);
        const rm = document.createElement('button');
        rm.type = 'button';
        rm.className = 'btn btn-sm btn-outline-danger px-1 py-0';
        rm.textContent = '×';
        rm.title = 'Remove this city';
        rm.addEventListener('click', () => { box.remove(); syncRouteHidden(leg); });
        box.appendChild(rm);
        holder.appendChild(box);
        return input;
    }

    function setStays(leg, joined) {
        const { holder } = routeEls(leg);
        holder.innerHTML = '';
        const parts = (joined || '').split('-').map(p => p.trim()).filter(Boolean);
        (parts.length ? parts : ['']).forEach(p => addStayInput(leg, p));
        routeEls(leg).hidden.value = parts.join('-');
    }

    ['leg1', 'leg2'].forEach(leg => {
        routeEls(leg).wrapEl.querySelector('.route-add-btn').addEventListener('click', () => addStayInput(leg, '').focus());
    });

    function applyTripType() {
        document.getElementById('m-leg2-wrap').style.display = document.getElementById('m-trip-type').value === 'return' ? 'flex' : 'none';
    }

    // Shades whichever side of each pair is the calculated one.
    function applyPairModes() {
        PAIRS.forEach(({ key }) => {
            const amountMode = pairEl(key, 'mode').value === 'amount';
            pairEl(key, 'pct').classList.toggle('is-derived', amountMode);
            pairEl(key, 'amt').classList.toggle('is-derived', !amountMode);
        });
    }

    // Live display only — the server always recomputes authoritatively.
    let perTicketTotal = 0;
    let perTicketSupplierNet = 0;

    function recalcMaster() {
        const v = { fare: num('m-fare'), tax: num('m-tax') };

        PAIRS.forEach(({ key, basis }) => {
            const b = basis(v);
            const pctEl = pairEl(key, 'pct'), amtEl = pairEl(key, 'amt');
            let amt;
            if (pairEl(key, 'mode').value === 'amount') {
                amt = round2(parseFloat(amtEl.value) || 0);
                const pct = b > 0 ? round2(amt / b * 100) : 0;
                if (document.activeElement !== pctEl) pctEl.value = pct;
            } else {
                const pct = parseFloat(pctEl.value) || 0;
                amt = round2(b * pct / 100);
                if (document.activeElement !== amtEl) amtEl.value = amt;
            }
            v[key] = amt;
        });

        const total = round2(v.fare + v.tax + v.apt + v.psf - v.discount);
        const supplierNet = round2(v.fare + v.tax + v.apt - v.commission + v.wht);
        document.getElementById('m-total-amount').textContent = total.toFixed(2);

        perTicketTotal = total;
        perTicketSupplierNet = supplierNet;
        recalcGrandTotal();
    }

    function recalcGrandTotal() {
        const rows = wrap.querySelectorAll('.ticket-row');
        rows.forEach(row => { row.querySelector('.f-row-amount').textContent = perTicketTotal.toFixed(2); });
        const count = rows.length;
        document.getElementById('ticket-count').textContent = count;
        document.getElementById('per-ticket-total').textContent = perTicketTotal.toFixed(2);
        document.getElementById('grand-total').textContent = (perTicketTotal * count).toFixed(2);
        document.getElementById('s-supplier-net').textContent = perTicketSupplierNet.toFixed(2);
        document.getElementById('s-supplier-total').textContent = (perTicketSupplierNet * count).toFixed(2);
    }

    function masterChanged() {
        recalcMaster();
        syncAllRowsFromMaster();
    }

    function reindexRows() {
        wrap.querySelectorAll('.ticket-row').forEach((row, i) => {
            row.querySelector('.ticket-index-label').textContent = i + 1;
            row.querySelectorAll('[data-name]').forEach(el => {
                el.name = 'lines[' + i + '][' + el.dataset.name + ']';
            });
        });
    }

    function syncRowFromMaster(row) {
        SHARED_FIELDS.forEach(field => {
            row.querySelector('.f-' + field).value = document.getElementById(SHARED_FIELD_IDS[field]).value;
        });
    }

    function syncAllRowsFromMaster() {
        wrap.querySelectorAll('.ticket-row').forEach(syncRowFromMaster);
    }

    // `ownValues`, when given, seeds a row's hidden fields from that
    // ticket's OWN saved values (editing an older invoice whose tickets
    // differ) until Master Details is actually touched.
    function addTicketRow(prefill, ownValues) {
        prefill = prefill || {};
        const node = tpl.content.cloneNode(true);
        const row = node.querySelector('.ticket-row');
        row.dataset.idx = idx++;

        row.querySelector('.f-id').dataset.name = 'id';
        row.querySelector('.f-pax-name').dataset.name = 'pax_name';
        row.querySelector('.f-pax-type').dataset.name = 'pax_type';
        row.querySelector('.f-ticket-no').dataset.name = 'ticket_no';

        fillSelect(row.querySelector('.f-pax-type'), data.paxTypes.map(t => ({ id: t, label: t })), 'id', t => t.label.charAt(0).toUpperCase() + t.label.slice(1));

        row.querySelector('.f-id').value = prefill.id || '';
        row.querySelector('.f-pax-name').value = prefill.pax_name || '';
        row.querySelector('.f-pax-type').value = prefill.pax_type || 'adult';
        row.querySelector('.f-ticket-no').value = prefill.ticket_no || '';

        const indexCell = row.querySelector('.index-cell');
        SHARED_FIELDS.forEach(field => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.className = 'f-' + field;
            input.dataset.name = field;
            indexCell.appendChild(input);
        });

        if (ownValues) {
            SHARED_FIELDS.forEach(field => { row.querySelector('.f-' + field).value = ownValues[field] ?? ''; });
        } else {
            syncRowFromMaster(row);
        }

        row.querySelector('.f-ticket-no').addEventListener('input', e => {
            e.target.dataset.autoFilled = '';
            if (row === wrap.querySelector('.ticket-row')) {
                autoSequenceTicketNumbers();
            }
        });
        row.querySelector('.f-ticket-no').addEventListener('blur', e => {
            e.target.value = formatTicketNo(e.target.value);
            if (row === wrap.querySelector('.ticket-row')) {
                detectAirlineFromFirstTicket();
                autoSequenceTicketNumbers();
            }
        });

        row.querySelector('.remove-ticket-btn').addEventListener('click', () => {
            row.remove();
            reindexRows();
            recalcGrandTotal();
        });

        wrap.appendChild(row);
        reindexRows();
        return row;
    }

    // Keeps every row's ticket # sequenced off ticket #1 — rows the user
    // typed into by hand are never overwritten.
    function autoSequenceTicketNumbers() {
        const baseDigits = firstRowTicketDigits();
        if (baseDigits.length !== 13) return;
        wrap.querySelectorAll('.ticket-row').forEach((row, position) => {
            if (position === 0) return;
            const input = row.querySelector('.f-ticket-no');
            const isBlank = digitsOf(input.value).length === 0;
            if (isBlank || input.dataset.autoFilled === '1') {
                input.value = incrementTicketNo(baseDigits, position);
                input.dataset.autoFilled = '1';
            }
        });
    }

    function detectAirlineFromFirstTicket() {
        const digits = firstRowTicketDigits();
        const hint = document.getElementById('m-airline-hint');
        if (digits.length < 3) { hint.textContent = ''; return; }
        const code = digits.slice(0, 3);
        const match = data.airlines.find(a => a.code === code);
        if (match) {
            setSelect('m-airline', match.id);
            hint.textContent = 'Auto-detected: ' + match.name;
            syncAllRowsFromMaster();
        } else {
            hint.textContent = 'No airline found for code ' + code + ' — pick one manually.';
        }
    }

    function checkLinesDiffer(lines) {
        if (lines.length < 2) return;
        const first = lines[0];
        const differs = lines.slice(1).some(l => SHARED_FIELDS.some(f => String(l[f] ?? '') !== String(first[f] ?? '')));
        if (differs) {
            document.getElementById('lines-differ-warning').style.display = '';
        }
    }

    function fillMasterFromLine(line) {
        setSelect('m-supplier', line.supplier_id || '');
        setSelect('m-airline', line.airline_id || '');
        setSelect('m-agent', line.sales_agent_id || '');
        document.getElementById('m-pnr').value = line.pnr || '';
        document.getElementById('m-trip-type').value = line.trip_type || 'one_way';
        document.getElementById('m-ticket-type').value = line.ticket_type || 'international';
        document.getElementById('m-leg1-from').value = line.leg1_from || '';
        setStays('leg1', line.leg1_stay);
        document.getElementById('m-leg1-to').value = line.leg1_to || '';
        document.getElementById('m-leg2-from').value = line.leg2_from || '';
        setStays('leg2', line.leg2_stay);
        document.getElementById('m-leg2-to').value = line.leg2_to || '';
        document.getElementById('m-leg1-dep-date').value = line.leg1_departure_date || '';
        document.getElementById('m-leg1-dep-time').value = (line.leg1_departure_time || '').slice(0, 5);
        document.getElementById('m-leg2-dep-date').value = line.leg2_departure_date || '';
        document.getElementById('m-leg2-dep-time').value = (line.leg2_departure_time || '').slice(0, 5);
        document.getElementById('m-fare').value = line.fare_amount || 0;
        document.getElementById('m-tax').value = line.tax_amount || 0;
        document.getElementById('m-psf-basis').value = line.psf_basis || 'fare';

        const pairSource = {
            apt: ['apt_percent', 'apt_charges', 'apt_input_mode'],
            commission: ['commission_percent', 'commission_amount', 'commission_input_mode'],
            wht: ['wht_percent', 'wht_amount', 'wht_input_mode'],
            psf: ['psf_percent', 'psf_amount', 'psf_input_mode'],
            discount: ['discount_percent', 'discount_amount', 'discount_input_mode'],
            agent_commission: ['agent_commission_percent', 'agent_commission_amount', 'agent_commission_input_mode'],
        };
        Object.entries(pairSource).forEach(([key, [pctF, amtF, modeF]]) => {
            pairEl(key, 'pct').value = parseFloat(line[pctF]) || 0;
            pairEl(key, 'amt').value = parseFloat(line[amtF]) || 0;
            pairEl(key, 'mode').value = line[modeF] === 'amount' ? 'amount' : 'percent';
        });

        applyPairModes();
        applyTripType();
    }

    fillSelect(document.getElementById('m-supplier'), data.suppliers, 'id', s => s.name, '— none —');
    fillSelect(document.getElementById('m-airline'), data.airlines, 'id', a => a.name + ' (' + a.code + ')', '— auto from first ticket # —');
    fillSelect(document.getElementById('m-agent'), data.agents, 'id', u => u.name, '— none —');

    // Typing in either side of a pair makes that side the "entered" one.
    PAIRS.forEach(({ key }) => {
        pairEl(key, 'pct').addEventListener('input', () => { pairEl(key, 'mode').value = 'percent'; applyPairModes(); masterChanged(); });
        pairEl(key, 'amt').addEventListener('input', () => { pairEl(key, 'mode').value = 'amount'; applyPairModes(); masterChanged(); });
        // Recompute once focus leaves (the derived side was skipped while focused).
        ['pct', 'amt'].forEach(part => pairEl(key, part).addEventListener('blur', recalcMaster));
    });

    ['m-fare', 'm-tax', 'm-pnr', 'm-leg1-from', 'm-leg1-to', 'm-leg2-from', 'm-leg2-to',
     'm-leg1-dep-date', 'm-leg1-dep-time', 'm-leg2-dep-date', 'm-leg2-dep-time'].forEach(id => {
        document.getElementById(id).addEventListener('input', masterChanged);
    });
    ['m-supplier', 'm-airline', 'm-agent', 'm-ticket-type', 'm-psf-basis'].forEach(id => {
        document.getElementById(id).addEventListener('change', masterChanged);
    });
    document.getElementById('m-trip-type').addEventListener('change', () => { applyTripType(); masterChanged(); });

    document.getElementById('add-ticket-btn').addEventListener('click', () => {
        addTicketRow({});
        autoSequenceTicketNumbers();
        recalcGrandTotal();
    });

    if (data.existingLines.length) {
        data.existingLines.forEach(l => addTicketRow(l, l));
        fillMasterFromLine(data.existingLines[0]);
        checkLinesDiffer(data.existingLines);
    } else if (data.quotationLines.length) {
        fillMasterFromLine(data.quotationLines[0]);
        data.quotationLines.forEach(() => addTicketRow({}));
    } else {
        fillMasterFromLine({});
        addTicketRow({});
    }

    recalcMaster();
    if (!data.existingLines.length) syncAllRowsFromMaster();

    // ---- Searchable dropdowns (select2) ----
    // jQuery + select2 are loaded in the layout footer, AFTER this script,
    // so initialise once the page has finished parsing. select2 fires
    // jQuery events only, so re-dispatch a native 'change' for the
    // listeners above.
    document.addEventListener('DOMContentLoaded', () => {
        const $ = window.jQuery;
        if (!$ || !$.fn.select2) return;
        document.querySelectorAll('#ticket-form-root .ti-select2').forEach(el => {
            $(el).select2({ width: '100%', placeholder: el.options[0] ? el.options[0].text : '', allowClear: !!(el.options[0] && el.options[0].value === '') });
            $(el).on('select2:select select2:clear', () => {
                el.dispatchEvent(new Event('change', { bubbles: true }));
                clearFieldError(el);
            });
        });
    });

    // ---- Submit via AJAX: validation errors are shown inline, the page never reloads ----
    const formEl = wrap.closest('form');
    const errorBox = document.getElementById('ticket-form-errors');

    function clearFieldError(el) {
        el.classList.remove('is-invalid');
        const s2 = el.nextElementSibling;
        if (s2 && s2.classList.contains('select2')) {
            s2.classList.remove('is-invalid-select2');
            const sel = s2.querySelector('.select2-selection');
            if (sel) sel.style.borderColor = '';
        }
    }

    function markInvalid(el) {
        if (!el) return;
        el.classList.add('is-invalid');
        const s2 = el.nextElementSibling;
        if (s2 && s2.classList.contains('select2')) {
            s2.classList.add('is-invalid-select2');
            const sel = s2.querySelector('.select2-selection');
            if (sel) sel.style.borderColor = '#dc3545';
        }
    }

    // Maps a server error key (customer_id, lines.2.ticket_no, lines.0.psf_percent ...) to its input.
    function fieldForKey(key) {
        if (!key.startsWith('lines.')) {
            return formEl.querySelector('[name="' + key + '"]');
        }
        const [, i, field] = key.split('.');
        if (!field) return null;
        if (SHARED_FIELD_IDS[field]) return document.getElementById(SHARED_FIELD_IDS[field]);
        const row = wrap.querySelectorAll('.ticket-row')[parseInt(i, 10)];
        return row ? row.querySelector('[data-name="' + field + '"]') : null;
    }

    function showErrors(errors, fallbackMessage) {
        formEl.querySelectorAll('.is-invalid').forEach(clearFieldError);
        const messages = [];
        let firstEl = null;
        Object.entries(errors || {}).forEach(([key, list]) => {
            const field = key.split('.')[2];
            const shared = key.startsWith('lines.') && SHARED_FIELD_IDS[field];
            (list || []).forEach(msg => {
                // A Master Details field fails identically on every row — show it once, without the row number.
                const text = shared ? msg.replace(/^(The )?Ticket row \d+ /i, '$1') : msg;
                if (!messages.includes(text)) messages.push(text);
            });
            const el = fieldForKey(key);
            markInvalid(el);
            if (!firstEl && el) firstEl = el;
        });
        if (!messages.length) messages.push(fallbackMessage || 'Something went wrong. Please try again.');

        errorBox.innerHTML = '<strong>Please fix the following:</strong><ul class="mb-0"></ul>';
        const ul = errorBox.querySelector('ul');
        messages.forEach(m => { const li = document.createElement('li'); li.textContent = m; ul.appendChild(li); });
        errorBox.style.display = '';
        errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (firstEl && firstEl.offsetParent !== null) setTimeout(() => firstEl.focus({ preventScroll: true }), 300);
    }

    // Client-side check for a PARTIAL ticket # (blank is allowed — "not entered yet").
    function clientErrors() {
        const errors = {};
        wrap.querySelectorAll('.ticket-row').forEach((row, i) => {
            const paxName = row.querySelector('.f-pax-name').value.trim();
            const digitCount = digitsOf(row.querySelector('.f-ticket-no').value).length;
            if (paxName && digitCount > 0 && digitCount !== 13) {
                errors['lines.' + i + '.ticket_no'] = ['Ticket row ' + (i + 1) + ': Ticket # has ' + digitCount +
                    ' digit(s), needs exactly 13 (format 220-1234-567-890). Finish it, or clear it to auto-fill from ticket #1.'];
            }
        });
        if (!document.getElementById('customer-select').value) errors.customer_id = ['Please select a customer.'];
        if (!formEl.querySelector('[name="invoice_date"]').value) errors.invoice_date = ['Please enter the invoice date.'];
        return errors;
    }

    if (formEl) {
        // Browser-native validation is OFF: it silently blocked submits when a
        // hidden input (e.g. the inactive side of a %/amount pair) was "invalid".
        formEl.setAttribute('novalidate', 'novalidate');
        formEl.addEventListener('input', e => { if (e.target.classList) clearFieldError(e.target); });

        formEl.addEventListener('submit', async (e) => {
            e.preventDefault();
            recalcMaster();
            syncAllRowsFromMaster();

            const local = clientErrors();
            if (Object.keys(local).length) { showErrors(local); return; }

            const btn = formEl.querySelector('[type="submit"]');
            const btnHtml = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = 'Saving…'; }

            try {
                const res = await fetch(formEl.action, {
                    method: 'POST', // _method=PUT (edit) travels in the form data
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(formEl),
                    credentials: 'same-origin',
                });
                let json = {};
                try { json = await res.json(); } catch (_) {}

                if (res.ok && json.redirect) {
                    window.location.href = json.redirect;
                    return;
                }
                if (res.status === 419) {
                    showErrors({}, 'Your session expired. Please refresh the page and try again (copy anything you typed first).');
                } else {
                    showErrors(json.errors, json.message);
                }
            } catch (err) {
                showErrors({}, 'Could not reach the server: ' + err.message);
            }
            if (btn) { btn.disabled = false; btn.innerHTML = btnHtml; }
        });
    }
})();
</script>
