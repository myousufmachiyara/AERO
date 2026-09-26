@php
    $invoice = $invoice ?? null;
    $quotation = $quotation ?? null;
    $existingLines = $invoice ? $invoice->lines->where('status', 'active')->values() : collect();
    $lockedLines = $invoice ? $invoice->lines->where('status', '!=', 'active')->values() : collect();
@endphp

{{-- No-tab, all-in-one layout per client feedback: everything on one page. --}}
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

<div id="tickets-wrap"></div>

<button type="button" class="btn btn-outline-primary mb-3" id="add-ticket-btn"><i class="fa fa-plus"></i> Add Ticket</button>

<div class="card">
    <div class="card-body d-flex justify-content-end">
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
            'commission_percent' => $l->commission_percent, 'wht_amount' => $l->wht_amount,
            'psf_percent' => $l->psf_percent, 'psf_basis' => $l->psf_basis, 'discount_amount' => $l->discount_amount,
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

<template id="ticket-card-tpl">
    <div class="card mb-3 ticket-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Ticket <span class="ticket-index-label"></span></strong>
            <button type="button" class="btn btn-sm btn-outline-danger remove-ticket-btn"><i class="fa fa-trash"></i> Remove</button>
        </div>
        <div class="card-body">
            <input type="hidden" class="f-id" value="">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="form-label">Supplier</label>
                    <select class="form-control f-supplier"><option value="">— none —</option></select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Passenger Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control f-pax-name" required>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Passenger Type</label>
                    <select class="form-control f-pax-type"></select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">PNR</label>
                    <input type="text" class="form-control f-pnr">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Ticket # <span class="text-danger">*</span></label>
                    <input type="text" class="form-control f-ticket-no" placeholder="220-1234-567-890" maxlength="16">
                    <small class="text-muted f-airline-hint"></small>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="form-label">Airline</label>
                    <select class="form-control f-airline"><option value="">— auto from ticket # —</option></select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Trip Type</label>
                    <select class="form-control f-trip-type">
                        <option value="one_way">One Way</option>
                        <option value="return">Return</option>
                    </select>
                </div>
                <div class="col-md-7 mb-2">
                    <label class="form-label">Cities (From - Stay - To)</label>
                    <div class="row g-1">
                        <div class="col-3"><input type="text" class="form-control form-control-sm f-leg1-from" placeholder="From"></div>
                        <div class="col-3"><input type="text" class="form-control form-control-sm f-leg1-stay" placeholder="Stay"></div>
                        <div class="col-3"><input type="text" class="form-control form-control-sm f-leg1-to" placeholder="To"></div>
                    </div>
                    <div class="row g-1 mt-1 f-leg2-wrap" style="display:none;">
                        <div class="col-3"><input type="text" class="form-control form-control-sm f-leg2-from" placeholder="From (return)"></div>
                        <div class="col-3"><input type="text" class="form-control form-control-sm f-leg2-stay" placeholder="Stay (return)"></div>
                        <div class="col-3"><input type="text" class="form-control form-control-sm f-leg2-to" placeholder="To (return)"></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-2 mb-2">
                    <label class="form-label">Fare Amount</label>
                    <input type="number" step="0.01" min="0" class="form-control f-fare" value="0">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Tax Amount</label>
                    <input type="number" step="0.01" min="0" class="form-control f-tax" value="0">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">APT %</label>
                    <input type="number" step="0.01" min="0" max="100" class="form-control f-apt-pct" value="0">
                    <small class="text-muted">= <span class="f-apt-amt">0.00</span></small>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Commission % (on fare)</label>
                    <input type="number" step="0.01" min="0" max="100" class="form-control f-commission-pct" value="0">
                    <small class="text-muted">= <span class="f-commission-amt">0.00</span> (from airline)</small>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">WHT</label>
                    <input type="number" step="0.01" min="0" class="form-control f-wht" value="0">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">PSF %</label>
                    <input type="number" step="0.01" min="0" max="100" class="form-control f-psf-pct" value="0">
                    <small class="text-muted">= <span class="f-psf-amt">0.00</span></small>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">PSF Based On</label>
                    <select class="form-control f-psf-basis">
                        <option value="fare">Fare Amount</option>
                        <option value="total">Fare + Tax + APT</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-2 mb-2">
                    <label class="form-label">Discount</label>
                    <input type="number" step="0.01" min="0" class="form-control f-discount" value="0">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Sales Agent</label>
                    <select class="form-control f-agent"><option value="">— none —</option></select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Agent Commission %</label>
                    <input type="number" step="0.01" min="0" max="100" class="form-control f-agent-commission-pct" value="0">
                    <small class="text-muted">= <span class="f-agent-commission-amt">0.00</span> (on ticket total)</small>
                </div>
                <div class="col-md-3 mb-2 ms-auto text-end">
                    <label class="form-label d-block">Ticket Total (customer)</label>
                    <h5 class="f-total-amount">0.00</h5>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
(function () {
    const data = window.TICKET_FORM_DATA;
    const wrap = document.getElementById('tickets-wrap');
    const tpl = document.getElementById('ticket-card-tpl');
    let idx = 0;

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

    function recalcCard(card) {
        // Mirrors TicketSaleInvoiceLine::recalculate() server-side — this is
        // for live display only, the server always recomputes authoritatively.
        const fare = parseFloat(card.querySelector('.f-fare').value) || 0;
        const tax = parseFloat(card.querySelector('.f-tax').value) || 0;
        const aptPct = parseFloat(card.querySelector('.f-apt-pct').value) || 0;
        const psfPct = parseFloat(card.querySelector('.f-psf-pct').value) || 0;
        const psfBasis = card.querySelector('.f-psf-basis').value || 'fare';
        const discount = parseFloat(card.querySelector('.f-discount').value) || 0;
        const commPct = parseFloat(card.querySelector('.f-commission-pct').value) || 0;
        const agentCommPct = parseFloat(card.querySelector('.f-agent-commission-pct').value) || 0;

        const round2 = n => Math.round(n * 100) / 100;

        const aptAmt = round2(fare * aptPct / 100);
        const psfBasisAmt = psfBasis === 'total' ? round2(fare + tax + aptAmt) : fare;
        const psfAmt = round2(psfBasisAmt * psfPct / 100);
        const commAmt = round2(fare * commPct / 100);
        const total = round2(fare + tax + aptAmt + psfAmt - discount);
        const agentCommAmt = round2(total * agentCommPct / 100);

        card.querySelector('.f-apt-amt').textContent = aptAmt.toFixed(2);
        card.querySelector('.f-psf-amt').textContent = psfAmt.toFixed(2);
        card.querySelector('.f-commission-amt').textContent = commAmt.toFixed(2);
        card.querySelector('.f-agent-commission-amt').textContent = agentCommAmt.toFixed(2);
        card.querySelector('.f-total-amount').textContent = total.toFixed(2);
        card.dataset.total = total;

        recalcGrandTotal();
    }

    function recalcGrandTotal() {
        let sum = 0;
        wrap.querySelectorAll('.ticket-card').forEach(c => { sum += parseFloat(c.dataset.total || 0); });
        document.getElementById('grand-total').textContent = sum.toFixed(2);
    }

    function reindexCards() {
        wrap.querySelectorAll('.ticket-card').forEach((card, i) => {
            card.querySelector('.ticket-index-label').textContent = i + 1;
            card.querySelectorAll('[data-name]').forEach(el => {
                el.name = 'lines[' + card.dataset.idx + '][' + el.dataset.name + ']';
            });
        });
    }

    function addTicketCard(prefill) {
        prefill = prefill || {};
        const node = tpl.content.cloneNode(true);
        const card = node.querySelector('.ticket-card');
        card.dataset.idx = idx++;
        card.dataset.total = 0;

        const map = {
            '.f-id': 'id', '.f-supplier': 'supplier_id', '.f-pax-name': 'pax_name',
            '.f-pax-type': 'pax_type', '.f-pnr': 'pnr', '.f-ticket-no': 'ticket_no',
            '.f-airline': 'airline_id', '.f-trip-type': 'trip_type',
            '.f-leg1-from': 'leg1_from', '.f-leg1-stay': 'leg1_stay', '.f-leg1-to': 'leg1_to',
            '.f-leg2-from': 'leg2_from', '.f-leg2-stay': 'leg2_stay', '.f-leg2-to': 'leg2_to',
            '.f-fare': 'fare_amount', '.f-tax': 'tax_amount', '.f-apt-pct': 'apt_percent',
            '.f-commission-pct': 'commission_percent', '.f-wht': 'wht_amount', '.f-psf-pct': 'psf_percent',
            '.f-psf-basis': 'psf_basis',
            '.f-discount': 'discount_amount', '.f-agent': 'sales_agent_id',
            '.f-agent-commission-pct': 'agent_commission_percent',
        };
        Object.keys(map).forEach(sel => card.querySelector(sel).dataset.name = map[sel]);

        fillSelect(card.querySelector('.f-supplier'), data.suppliers, 'id', s => s.name, '— none —');
        fillSelect(card.querySelector('.f-airline'), data.airlines, 'id', a => a.name + ' (' + a.code + ')', '— auto from ticket # —');
        fillSelect(card.querySelector('.f-agent'), data.agents, 'id', u => u.name, '— none —');
        fillSelect(card.querySelector('.f-pax-type'), data.paxTypes.map(t => ({ id: t, label: t })), 'id', t => t.label.charAt(0).toUpperCase() + t.label.slice(1));

        card.querySelector('.f-id').value = prefill.id || '';
        card.querySelector('.f-supplier').value = prefill.supplier_id || '';
        card.querySelector('.f-pax-name').value = prefill.pax_name || '';
        card.querySelector('.f-pax-type').value = prefill.pax_type || 'adult';
        card.querySelector('.f-pnr').value = prefill.pnr || '';
        card.querySelector('.f-ticket-no').value = prefill.ticket_no || '';
        card.querySelector('.f-airline').value = prefill.airline_id || '';
        card.querySelector('.f-trip-type').value = prefill.trip_type || 'one_way';
        card.querySelector('.f-leg1-from').value = prefill.leg1_from || '';
        card.querySelector('.f-leg1-stay').value = prefill.leg1_stay || '';
        card.querySelector('.f-leg1-to').value = prefill.leg1_to || '';
        card.querySelector('.f-leg2-from').value = prefill.leg2_from || '';
        card.querySelector('.f-leg2-stay').value = prefill.leg2_stay || '';
        card.querySelector('.f-leg2-to').value = prefill.leg2_to || '';
        card.querySelector('.f-fare').value = prefill.fare_amount || 0;
        card.querySelector('.f-tax').value = prefill.tax_amount || 0;
        card.querySelector('.f-apt-pct').value = prefill.apt_percent || 0;
        card.querySelector('.f-commission-pct').value = prefill.commission_percent || 0;
        card.querySelector('.f-wht').value = prefill.wht_amount || 0;
        card.querySelector('.f-psf-pct').value = prefill.psf_percent || 0;
        card.querySelector('.f-psf-basis').value = prefill.psf_basis || 'fare';
        card.querySelector('.f-discount').value = prefill.discount_amount || 0;
        card.querySelector('.f-agent').value = prefill.sales_agent_id || '';
        card.querySelector('.f-agent-commission-pct').value = prefill.agent_commission_percent || 0;

        const leg2Wrap = card.querySelector('.f-leg2-wrap');
        leg2Wrap.style.display = card.querySelector('.f-trip-type').value === 'return' ? 'flex' : 'none';
        card.querySelector('.f-trip-type').addEventListener('change', e => {
            leg2Wrap.style.display = e.target.value === 'return' ? 'flex' : 'none';
        });

        card.querySelector('.f-ticket-no').addEventListener('blur', e => {
            const formatted = formatTicketNo(e.target.value);
            e.target.value = formatted;
            const code = formatted.split('-')[0];
            const hint = card.querySelector('.f-airline-hint');
            if (code && code.length === 3) {
                const match = data.airlines.find(a => a.code === code);
                if (match) {
                    card.querySelector('.f-airline').value = match.id;
                    hint.textContent = 'Auto-detected: ' + match.name;
                } else {
                    hint.textContent = 'No airline found for code ' + code + ' — pick one manually.';
                }
            } else {
                hint.textContent = '';
            }
        });

        card.querySelectorAll('.f-fare, .f-tax, .f-apt-pct, .f-psf-pct, .f-discount, .f-commission-pct, .f-agent-commission-pct').forEach(el => {
            el.addEventListener('input', () => recalcCard(card));
        });
        card.querySelector('.f-psf-basis').addEventListener('change', () => recalcCard(card));

        card.querySelector('.remove-ticket-btn').addEventListener('click', () => {
            card.remove();
            reindexCards();
            recalcGrandTotal();
        });

        wrap.appendChild(card);
        reindexCards();
        recalcCard(card);
    }

    document.getElementById('add-ticket-btn').addEventListener('click', () => addTicketCard());

    if (data.existingLines.length) {
        data.existingLines.forEach(l => addTicketCard(l));
    } else if (data.quotationLines.length) {
        data.quotationLines.forEach(l => addTicketCard({ supplier_id: l.supplier_id, fare_amount: l.fare_amount }));
    } else {
        addTicketCard();
    }
})();
</script>
