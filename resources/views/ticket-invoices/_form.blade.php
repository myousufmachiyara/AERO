@php
    $invoice = $invoice ?? null;
    $quotation = $quotation ?? null;
    $existingLines = $invoice ? $invoice->lines->where('status', 'active')->values() : collect();
    $lockedLines = $invoice ? $invoice->lines->where('status', '!=', 'active')->values() : collect();
@endphp

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
        <input type="date" name="invoice_date" class="form-control" required
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
        <select name="customer_id" class="form-control" required>
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
                <select class="form-control" id="m-supplier"><option value="">— none —</option></select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Airline</label>
                <select class="form-control" id="m-airline"><option value="">— auto from first ticket # —</option></select>
                <small class="text-muted" id="m-airline-hint"></small>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">PNR</label>
                <input type="text" class="form-control" id="m-pnr">
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Trip Type</label>
                <select class="form-control" id="m-trip-type">
                    <option value="one_way">One Way</option>
                    <option value="return">Return</option>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12 mb-2">
                <label class="form-label">Cities (From - Stay - To)</label>
                <div class="row g-1">
                    <div class="col-md-3"><input type="text" class="form-control form-control-sm" id="m-leg1-from" placeholder="From"></div>
                    <div class="col-md-3"><input type="text" class="form-control form-control-sm" id="m-leg1-stay" placeholder="Stay"></div>
                    <div class="col-md-3"><input type="text" class="form-control form-control-sm" id="m-leg1-to" placeholder="To"></div>
                </div>
                <div class="row g-1 mt-1" id="m-leg2-wrap" style="display:none;">
                    <div class="col-md-3"><input type="text" class="form-control form-control-sm" id="m-leg2-from" placeholder="From (return)"></div>
                    <div class="col-md-3"><input type="text" class="form-control form-control-sm" id="m-leg2-stay" placeholder="Stay (return)"></div>
                    <div class="col-md-3"><input type="text" class="form-control form-control-sm" id="m-leg2-to" placeholder="To (return)"></div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-2 mb-2">
                <label class="form-label">Fare Amount</label>
                <input type="number" step="0.01" min="0" class="form-control" id="m-fare" value="0">
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Tax Amount</label>
                <input type="number" step="0.01" min="0" class="form-control" id="m-tax" value="0">
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">APT %</label>
                <input type="number" step="0.01" min="0" max="100" class="form-control" id="m-apt-pct" value="0">
                <small class="text-muted">= <span id="m-apt-amt">0.00</span></small>
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Commission % (on fare)</label>
                <input type="number" step="0.01" min="0" max="100" class="form-control" id="m-commission-pct" value="0">
                <small class="text-muted">= <span id="m-commission-amt">0.00</span> (from airline)</small>
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">WHT % (on commission)</label>
                <input type="number" step="0.01" min="0" max="100" class="form-control" id="m-wht-pct" value="0">
                <small class="text-muted">= <span id="m-wht-amt">0.00</span></small>
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Sales Agent</label>
                <select class="form-control" id="m-agent"><option value="">— none —</option></select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3 mb-2">
                <label class="form-label d-flex justify-content-between align-items-center mb-1">
                    <span>PSF</span>
                    <select class="form-control form-control-sm" id="m-psf-mode" style="width:auto;padding:0 4px;">
                        <option value="percent">Enter %</option>
                        <option value="amount">Enter Amount</option>
                    </select>
                </label>
                <input type="number" step="0.01" min="0" max="100" class="form-control" id="m-psf-pct" placeholder="PSF %" value="0">
                <input type="number" step="0.01" min="0" class="form-control mt-1" id="m-psf-amt-input" placeholder="PSF Amount" value="0" style="display:none;">
                <select class="form-control form-control-sm mt-1" id="m-psf-basis">
                    <option value="fare">Based on Fare Amount</option>
                    <option value="total">Based on Fare + Tax + APT</option>
                </select>
                <small class="text-muted">= <span id="m-psf-computed">0.00</span></small>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label d-flex justify-content-between align-items-center mb-1">
                    <span>Discount</span>
                    <select class="form-control form-control-sm" id="m-discount-mode" style="width:auto;padding:0 4px;">
                        <option value="percent">Enter %</option>
                        <option value="amount">Enter Amount</option>
                    </select>
                </label>
                <input type="number" step="0.01" min="0" max="100" class="form-control" id="m-discount-pct" placeholder="Discount %" value="0">
                <input type="number" step="0.01" min="0" class="form-control mt-1" id="m-discount-amt" placeholder="Discount Amount" value="0" style="display:none;">
                <small class="text-muted">(on Fare Amount) = <span id="m-discount-computed">0.00</span></small>
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Agent Commission %</label>
                <input type="number" step="0.01" min="0" max="100" class="form-control" id="m-agent-commission-pct" value="0">
                <small class="text-muted">= <span id="m-agent-commission-amt">0.00</span> (on ticket total)</small>
            </div>
            <div class="col-md-4 mb-2 ms-auto text-end">
                <label class="form-label d-block">Ticket Total (per passenger)</label>
                <h5 id="m-total-amount">0.00</h5>
            </div>
        </div>
    </div>
</section>

<section class="card mb-3">
    <header class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title h6 mb-0">Tickets <small class="text-muted">— one row per passenger</small></h3>
        <button type="button" class="btn btn-sm btn-outline-primary" id="add-ticket-btn"><i class="fa fa-plus"></i> Add Ticket</button>
    </header>
    <div class="card-body">
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

<div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center">
        <span class="text-muted"><span id="ticket-count">0</span> ticket(s) &times; <span id="per-ticket-total">0.00</span></span>
        <h5 class="mb-0">Grand Total: <span id="grand-total">0.00</span></h5>
    </div>
</div>

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
            'trip_type' => $l->trip_type,
            'leg1_from' => $l->leg1_from, 'leg1_stay' => $l->leg1_stay, 'leg1_to' => $l->leg1_to,
            'leg2_from' => $l->leg2_from, 'leg2_stay' => $l->leg2_stay, 'leg2_to' => $l->leg2_to,
            'fare_amount' => $l->fare_amount, 'tax_amount' => $l->tax_amount, 'apt_percent' => $l->apt_percent,
            'commission_percent' => $l->commission_percent, 'wht_percent' => $l->wht_percent,
            'psf_percent' => $l->psf_percent, 'psf_amount' => $l->psf_amount, 'psf_basis' => $l->psf_basis,
            'psf_input_mode' => $l->psf_input_mode,
            'discount_percent' => $l->discount_percent, 'discount_amount' => $l->discount_amount,
            'discount_input_mode' => $l->discount_input_mode,
            'sales_agent_id' => $l->sales_agent_id, 'agent_commission_percent' => $l->agent_commission_percent,
        ];
    })->values();
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
            <input type="text" class="form-control form-control-sm f-pax-name" placeholder="Passenger name" required>
        </td>
        <td><select class="form-control form-control-sm f-pax-type"></select></td>
        <td>
            <input type="text" class="form-control form-control-sm f-ticket-no" placeholder="220-1234-567-890" maxlength="16" required>
        </td>
        <td class="text-end align-middle f-row-amount">0.00</td>
        <td class="text-center align-middle">
            <button type="button" class="btn btn-sm btn-outline-danger remove-ticket-btn"><i class="fa fa-trash"></i></button>
        </td>
    </tr>
</template>

<script>
(function () {
    const data = window.TICKET_FORM_DATA;
    const wrap = document.getElementById('tickets-wrap');
    const tpl = document.getElementById('ticket-row-tpl');
    let idx = 0;

    // Fields shared by every ticket on the invoice — entered once in
    // Master Details, copied into every row's hidden inputs so the
    // server-side payload (lines[i][field]) is unchanged.
    const SHARED_FIELD_IDS = {
        supplier_id: 'm-supplier', airline_id: 'm-airline', pnr: 'm-pnr', trip_type: 'm-trip-type',
        leg1_from: 'm-leg1-from', leg1_stay: 'm-leg1-stay', leg1_to: 'm-leg1-to',
        leg2_from: 'm-leg2-from', leg2_stay: 'm-leg2-stay', leg2_to: 'm-leg2-to',
        fare_amount: 'm-fare', tax_amount: 'm-tax', apt_percent: 'm-apt-pct',
        commission_percent: 'm-commission-pct', wht_percent: 'm-wht-pct',
        psf_percent: 'm-psf-pct', psf_amount: 'm-psf-amt-input', psf_basis: 'm-psf-basis', psf_input_mode: 'm-psf-mode',
        discount_percent: 'm-discount-pct', discount_amount: 'm-discount-amt', discount_input_mode: 'm-discount-mode',
        sales_agent_id: 'm-agent', agent_commission_percent: 'm-agent-commission-pct',
    };
    const SHARED_FIELDS = Object.keys(SHARED_FIELD_IDS);

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

    // Auto-sequencing: "pick up from the first ticket, bump the number for
    // each next one" — increments the full 13-digit number (not just the
    // last character), so groups past 9 passengers roll over digits
    // correctly (...009 -> ...010) instead of wrapping back to 0 and
    // colliding. Falls back to blank (user fills in manually) if the
    // first ticket # isn't a complete 13-digit number yet, or the
    // increment would spill past 13 digits.
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

    function applyPsfMode() {
        const mode = document.getElementById('m-psf-mode').value;
        document.getElementById('m-psf-pct').style.display = mode === 'amount' ? 'none' : '';
        document.getElementById('m-psf-amt-input').style.display = mode === 'amount' ? '' : 'none';
    }

    function applyDiscountMode() {
        const mode = document.getElementById('m-discount-mode').value;
        document.getElementById('m-discount-pct').style.display = mode === 'amount' ? 'none' : '';
        document.getElementById('m-discount-amt').style.display = mode === 'amount' ? '' : 'none';
    }

    function applyTripType() {
        document.getElementById('m-leg2-wrap').style.display = document.getElementById('m-trip-type').value === 'return' ? 'flex' : 'none';
    }

    // Mirrors TicketSaleInvoiceLine::recalculate() server-side — this is
    // for live display only, the server always recomputes authoritatively.
    let perTicketTotal = 0;

    function recalcMaster() {
        const round2 = n => Math.round(n * 100) / 100;

        const fare = parseFloat(document.getElementById('m-fare').value) || 0;
        const tax = parseFloat(document.getElementById('m-tax').value) || 0;
        const aptPct = parseFloat(document.getElementById('m-apt-pct').value) || 0;
        const psfBasis = document.getElementById('m-psf-basis').value || 'fare';
        const psfMode = document.getElementById('m-psf-mode').value || 'percent';
        const discountMode = document.getElementById('m-discount-mode').value || 'percent';
        const commPct = parseFloat(document.getElementById('m-commission-pct').value) || 0;
        const whtPct = parseFloat(document.getElementById('m-wht-pct').value) || 0;
        const agentCommPct = parseFloat(document.getElementById('m-agent-commission-pct').value) || 0;

        const aptAmt = round2(fare * aptPct / 100);

        const psfBasisAmt = psfBasis === 'total' ? round2(fare + tax + aptAmt) : fare;
        let psfPct, psfAmt;
        if (psfMode === 'amount') {
            psfAmt = parseFloat(document.getElementById('m-psf-amt-input').value) || 0;
            psfPct = psfBasisAmt > 0 ? round2(psfAmt / psfBasisAmt * 100) : 0;
        } else {
            psfPct = parseFloat(document.getElementById('m-psf-pct').value) || 0;
            psfAmt = round2(psfBasisAmt * psfPct / 100);
        }

        let discPct, discAmt;
        if (discountMode === 'amount') {
            discAmt = parseFloat(document.getElementById('m-discount-amt').value) || 0;
            discPct = fare > 0 ? round2(discAmt / fare * 100) : 0;
        } else {
            discPct = parseFloat(document.getElementById('m-discount-pct').value) || 0;
            discAmt = round2(fare * discPct / 100);
        }

        const commAmt = round2(fare * commPct / 100);
        const whtAmt = round2(commAmt * whtPct / 100);
        const total = round2(fare + tax + aptAmt + psfAmt - discAmt);
        const agentCommAmt = round2(total * agentCommPct / 100);

        document.getElementById('m-apt-amt').textContent = aptAmt.toFixed(2);
        document.getElementById('m-psf-computed').textContent = psfMode === 'amount' ? psfPct.toFixed(2) + '%' : psfAmt.toFixed(2);
        document.getElementById('m-discount-computed').textContent = discountMode === 'amount' ? discPct.toFixed(2) + '%' : discAmt.toFixed(2);
        document.getElementById('m-commission-amt').textContent = commAmt.toFixed(2);
        document.getElementById('m-wht-amt').textContent = whtAmt.toFixed(2);
        document.getElementById('m-agent-commission-amt').textContent = agentCommAmt.toFixed(2);
        document.getElementById('m-total-amount').textContent = total.toFixed(2);

        perTicketTotal = total;
        recalcGrandTotal();
    }

    function recalcGrandTotal() {
        const rows = wrap.querySelectorAll('.ticket-row');
        rows.forEach(row => { row.querySelector('.f-row-amount').textContent = perTicketTotal.toFixed(2); });
        const count = rows.length;
        document.getElementById('ticket-count').textContent = count;
        document.getElementById('per-ticket-total').textContent = perTicketTotal.toFixed(2);
        // Grand total = one ticket's total × number of tickets, per client spec.
        document.getElementById('grand-total').textContent = (perTicketTotal * count).toFixed(2);
    }

    function reindexRows() {
        wrap.querySelectorAll('.ticket-row').forEach((row, i) => {
            row.querySelector('.ticket-index-label').textContent = i + 1;
            row.querySelectorAll('[data-name]').forEach(el => {
                el.name = 'lines[' + row.dataset.idx + '][' + el.dataset.name + ']';
            });
        });
    }

    // Copies the current Master Details values into one row's hidden
    // inputs. Called for every new row, and for every existing row
    // whenever a Master Details field changes (see the listeners below).
    function syncRowFromMaster(row) {
        SHARED_FIELDS.forEach(field => {
            const master = document.getElementById(SHARED_FIELD_IDS[field]);
            row.querySelector('.f-' + field).value = master.value;
        });
    }

    function syncAllRowsFromMaster() {
        wrap.querySelectorAll('.ticket-row').forEach(syncRowFromMaster);
    }

    // `ownValues`, when given, seeds a row's hidden fields from that
    // ticket's OWN previously-saved values instead of the current Master
    // Details fields — used only when loading an existing invoice, so a
    // ticket that was entered with different charges before this form
    // changed keeps its original figures until Master Details is
    // actually touched (see the warning banner above).
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

        // Hidden shared-field inputs, one per master field, so the submit
        // payload still carries a full line per ticket exactly like before.
        // Tucked inside the index cell (as siblings of the index number
        // span, not replacing it) so the <tr> itself only ever has <td>
        // children — hidden inputs work from anywhere in the form, but
        // this keeps the table markup valid.
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

        row.querySelector('.f-ticket-no').addEventListener('blur', e => {
            e.target.value = formatTicketNo(e.target.value);
            // Airline auto-detect only matters off the FIRST ticket — the
            // airline is a shared/master field, not per-passenger.
            if (row === wrap.querySelector('.ticket-row')) {
                detectAirlineFromFirstTicket();
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

    function detectAirlineFromFirstTicket() {
        const digits = firstRowTicketDigits();
        const hint = document.getElementById('m-airline-hint');
        if (digits.length < 3) { hint.textContent = ''; return; }
        const code = digits.slice(0, 3);
        const match = data.airlines.find(a => a.code === code);
        if (match) {
            document.getElementById('m-airline').value = match.id;
            hint.textContent = 'Auto-detected: ' + match.name;
            syncAllRowsFromMaster();
        } else {
            hint.textContent = 'No airline found for code ' + code + ' — pick one manually.';
        }
    }

    // Shows the "tickets don't all match" warning when existing lines
    // (loaded from a saved invoice) differ from the first one on any of
    // the shared fields — before any edit, so the agent knows what
    // touching Master Details will do.
    function checkLinesDiffer(lines) {
        if (lines.length < 2) return;
        const first = lines[0];
        const differs = lines.slice(1).some(l => SHARED_FIELDS.some(f => String(l[f] ?? '') !== String(first[f] ?? '')));
        if (differs) {
            document.getElementById('lines-differ-warning').style.display = '';
        }
    }

    function fillMasterFromLine(line) {
        document.getElementById('m-supplier').value = line.supplier_id || '';
        document.getElementById('m-airline').value = line.airline_id || '';
        document.getElementById('m-pnr').value = line.pnr || '';
        document.getElementById('m-trip-type').value = line.trip_type || 'one_way';
        document.getElementById('m-leg1-from').value = line.leg1_from || '';
        document.getElementById('m-leg1-stay').value = line.leg1_stay || '';
        document.getElementById('m-leg1-to').value = line.leg1_to || '';
        document.getElementById('m-leg2-from').value = line.leg2_from || '';
        document.getElementById('m-leg2-stay').value = line.leg2_stay || '';
        document.getElementById('m-leg2-to').value = line.leg2_to || '';
        document.getElementById('m-fare').value = line.fare_amount || 0;
        document.getElementById('m-tax').value = line.tax_amount || 0;
        document.getElementById('m-apt-pct').value = line.apt_percent || 0;
        document.getElementById('m-commission-pct').value = line.commission_percent || 0;
        document.getElementById('m-wht-pct').value = line.wht_percent || 0;
        document.getElementById('m-psf-pct').value = line.psf_percent || 0;
        document.getElementById('m-psf-amt-input').value = line.psf_amount || 0;
        document.getElementById('m-psf-basis').value = line.psf_basis || 'fare';
        document.getElementById('m-psf-mode').value = line.psf_input_mode || 'percent';
        document.getElementById('m-discount-pct').value = line.discount_percent || 0;
        document.getElementById('m-discount-amt').value = line.discount_amount || 0;
        document.getElementById('m-discount-mode').value = line.discount_input_mode || 'percent';
        document.getElementById('m-agent').value = line.sales_agent_id || '';
        document.getElementById('m-agent-commission-pct').value = line.agent_commission_percent || 0;
        applyPsfMode();
        applyDiscountMode();
        applyTripType();
    }

    fillSelect(document.getElementById('m-supplier'), data.suppliers, 'id', s => s.name, '— none —');
    fillSelect(document.getElementById('m-airline'), data.airlines, 'id', a => a.name + ' (' + a.code + ')', '— auto from first ticket # —');
    fillSelect(document.getElementById('m-agent'), data.agents, 'id', u => u.name, '— none —');

    // Master field changes recompute the total AND cascade to every row
    // (intentional — see the warning banner: Master Details applies to
    // every ticket on the invoice, there's no per-ticket override).
    ['m-fare', 'm-tax', 'm-apt-pct', 'm-psf-pct', 'm-psf-amt-input', 'm-discount-pct', 'm-discount-amt',
     'm-commission-pct', 'm-wht-pct', 'm-agent-commission-pct', 'm-supplier', 'm-airline', 'm-pnr',
     'm-leg1-from', 'm-leg1-stay', 'm-leg1-to', 'm-leg2-from', 'm-leg2-stay', 'm-leg2-to', 'm-agent'].forEach(id => {
        document.getElementById(id).addEventListener('input', () => { recalcMaster(); syncAllRowsFromMaster(); });
    });
    document.getElementById('m-psf-basis').addEventListener('change', () => { recalcMaster(); syncAllRowsFromMaster(); });
    document.getElementById('m-psf-mode').addEventListener('change', () => { applyPsfMode(); recalcMaster(); syncAllRowsFromMaster(); });
    document.getElementById('m-discount-mode').addEventListener('change', () => { applyDiscountMode(); recalcMaster(); syncAllRowsFromMaster(); });
    document.getElementById('m-trip-type').addEventListener('change', () => { applyTripType(); recalcMaster(); syncAllRowsFromMaster(); });

    document.getElementById('add-ticket-btn').addEventListener('click', () => {
        const baseDigits = firstRowTicketDigits();
        const newRowPosition = wrap.querySelectorAll('.ticket-row').length; // 0-based position of the row about to be added
        const autoTicketNo = baseDigits.length === 13 ? incrementTicketNo(baseDigits, newRowPosition) : '';
        addTicketRow({ ticket_no: autoTicketNo });
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
})();
</script>
