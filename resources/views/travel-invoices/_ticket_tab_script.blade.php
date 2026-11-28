{{-- Script for travel-invoices/_ticket_tab (Ticket Sale Invoice layout inside the Tour Invoice). Adapted from ticket-invoices/_form. --}}
<script>
(function () {
    if (!document.getElementById('tkm-root')) return;
    const data = window.TOUR_TICKET_DATA;
    const wrap = document.getElementById('tkm-tickets-wrap');
    const tpl = document.getElementById('tkm-ticket-row-tpl');
    const round2 = n => Math.round((n + Number.EPSILON) * 100) / 100;
    const num = id => parseFloat(document.getElementById(id).value) || 0;

    // Fields shared by every ticket on the invoice — entered once in
    // Master Details, copied into every row's hidden inputs. Each grid row
    // is one tour-invoice service line: lines[N][supplier_id],
    // lines[N][description] (passenger name) and lines[N][detail][field].
    const SHARED_FIELD_IDS = {
        supplier_id: 'tkm-supplier', airline_id: 'tkm-airline', pnr: 'tkm-pnr', trip_type: 'tkm-trip-type',
        ticket_type: 'tkm-ticket-type',
        leg1_from: 'tkm-leg1-from', leg1_stay: 'tkm-leg1-stay', leg1_to: 'tkm-leg1-to',
        leg2_from: 'tkm-leg2-from', leg2_stay: 'tkm-leg2-stay', leg2_to: 'tkm-leg2-to',
        leg1_departure_date: 'tkm-leg1-dep-date', leg1_departure_time: 'tkm-leg1-dep-time',
        leg2_departure_date: 'tkm-leg2-dep-date', leg2_departure_time: 'tkm-leg2-dep-time',
        fare_amount: 'tkm-fare', tax_amount: 'tkm-tax',
        apt_percent: 'tkm-apt-pct', apt_charges: 'tkm-apt-amt', apt_input_mode: 'tkm-apt-mode',
        commission_percent: 'tkm-commission-pct', commission_amount: 'tkm-commission-amt', commission_input_mode: 'tkm-commission-mode',
        wht_percent: 'tkm-wht-pct', wht_amount: 'tkm-wht-amt', wht_input_mode: 'tkm-wht-mode',
        psf_percent: 'tkm-psf-pct', psf_amount: 'tkm-psf-amt', psf_input_mode: 'tkm-psf-mode', psf_basis: 'tkm-psf-basis',
        discount_percent: 'tkm-discount-pct', discount_amount: 'tkm-discount-amt', discount_input_mode: 'tkm-discount-mode',
        sales_agent_id: 'tkm-agent',
        agent_commission_percent: 'tkm-agent_commission-pct', agent_commission_amount: 'tkm-agent_commission-amt',
        agent_commission_input_mode: 'tkm-agent_commission-mode',
    };
    const SHARED_FIELDS = Object.keys(SHARED_FIELD_IDS);

    // The two-way %/amount pairs, IN CALCULATION ORDER (each basis only
    // depends on pairs above it) — mirrors TicketSaleInvoiceLine::recalculate().
    const PAIRS = [
        { key: 'apt',              basis: v => v.fare },
        { key: 'psf',              basis: v => document.getElementById('tkm-psf-basis').value === 'total' ? round2(v.fare + v.tax + v.apt) : v.fare },
        { key: 'discount',         basis: v => v.fare },
        { key: 'commission',       basis: v => v.fare },
        { key: 'wht',              basis: v => v.commission },
        { key: 'agent_commission', basis: v => v.psf },
    ];
    const pairEl = (key, part) => document.getElementById('tkm-' + key + '-' + part);

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
        const wrapEl = document.querySelector('#tkm-root [data-leg="' + leg + '"]');
        return { wrapEl, holder: wrapEl.querySelector('.route-stays'), hidden: document.getElementById('tkm-' + leg + '-stay') };
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
        document.getElementById('tkm-leg2-wrap').style.display = document.getElementById('tkm-trip-type').value === 'return' ? 'flex' : 'none';
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
        const v = { fare: num('tkm-fare'), tax: num('tkm-tax') };

        let baseMissing = false;

        PAIRS.forEach(({ key, basis }) => {
            const b = basis(v);
            const pctEl = pairEl(key, 'pct'), amtEl = pairEl(key, 'amt');
            let amt;
            if (pairEl(key, 'mode').value === 'amount') {
                amt = round2(parseFloat(amtEl.value) || 0);
                const pct = b > 0 ? round2(amt / b * 100) : 0;
                if (document.activeElement !== pctEl) { pctEl.value = pct; pctEl.dataset.shown = pctEl.value; }
                if (amt > 0 && !(b > 0)) baseMissing = true;
            } else {
                const pct = parseFloat(pctEl.value) || 0;
                amt = round2(b * pct / 100);
                if (document.activeElement !== amtEl) { amtEl.value = amt; amtEl.dataset.shown = amtEl.value; }
            }
            v[key] = amt;
        });
        const hint = document.getElementById('tkm-pair-hint');
        if (hint) hint.classList.toggle('d-none', !baseMissing);

        const total = round2(v.fare + v.tax + v.apt + v.psf - v.discount);
        const supplierNet = round2(v.fare + v.tax + v.apt - v.commission + v.wht);
        document.getElementById('tkm-total-amount').textContent = total.toFixed(2);

        perTicketTotal = total;
        perTicketSupplierNet = supplierNet;
        recalcGrandTotal();
    }

    function recalcGrandTotal() {
        const rows = wrap.querySelectorAll('.ticket-row');
        rows.forEach(row => { row.querySelector('.f-row-amount').textContent = perTicketTotal.toFixed(2); });
        const count = rows.length;
        document.getElementById('tkm-ticket-count').textContent = count;
        document.getElementById('tkm-per-ticket-total').textContent = perTicketTotal.toFixed(2);
        document.getElementById('tkm-grand-total').textContent = (perTicketTotal * count).toFixed(2);
        document.getElementById('tkm-s-supplier-net').textContent = perTicketSupplierNet.toFixed(2);
        document.getElementById('tkm-s-supplier-total').textContent = (perTicketSupplierNet * count).toFixed(2);
        if (typeof window.recalcTotals === 'function') window.recalcTotals(); // Invoice Summary tab
    }

    function masterChanged() {
        recalcMaster();
        syncAllRowsFromMaster();
    }

    // Fields that live on the service line itself; everything else goes in lines[N][detail][...].
    const LINE_LEVEL = { supplier_id: 'supplier_id', pax_name: 'description', service_type: 'service_type' };

    function reindexRows() {
        wrap.querySelectorAll('.ticket-row').forEach((row, i) => {
            row.querySelector('.ticket-index-label').textContent = i + 1;
            const n = row.dataset.lineIdx;
            row.querySelectorAll('[data-name]').forEach(el => {
                const f = el.dataset.name;
                el.name = LINE_LEVEL[f] ? 'lines[' + n + '][' + LINE_LEVEL[f] + ']' : 'lines[' + n + '][detail][' + f + ']';
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
        // lineIndex is the tour form's shared counter, so ticket rows never
        // collide with hotel/transport/visa/other line numbers.
        row.dataset.lineIdx = lineIndex++;

        row.querySelector('.f-pax-name').dataset.name = 'pax_name';
        row.querySelector('.f-pax-type').dataset.name = 'pax_type';
        row.querySelector('.f-ticket-no').dataset.name = 'ticket_no';

        fillSelect(row.querySelector('.f-pax-type'), data.paxTypes.map(t => ({ id: t, label: t })), 'id', t => t.label.charAt(0).toUpperCase() + t.label.slice(1));

        row.querySelector('.f-pax-name').value = prefill.pax_name || '';
        row.querySelector('.f-pax-type').value = prefill.pax_type || 'adult';
        row.querySelector('.f-ticket-no').value = prefill.ticket_no || '';

        const indexCell = row.querySelector('.index-cell');
        const typeInput = document.createElement('input');
        typeInput.type = 'hidden';
        typeInput.value = 'ticket';
        typeInput.dataset.name = 'service_type';
        indexCell.appendChild(typeInput);
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
            // Ticket # is exactly 13 digits — anything past the 13th digit is dropped as you type.
            const typed = e.target.value.replace(/[^\d-]/g, '');
            if (typed.replace(/\D/g, '').length > 13 || typed !== e.target.value) {
                e.target.value = formatTicketNo(typed);
            }
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
        const hint = document.getElementById('tkm-airline-hint');
        if (digits.length < 3) { hint.textContent = ''; return; }
        const code = digits.slice(0, 3);
        const match = data.airlines.find(a => a.code === code);
        if (match) {
            setSelect('tkm-airline', match.id);
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
            document.getElementById('tkm-lines-differ-warning').style.display = '';
        }
    }

    function fillMasterFromLine(line) {
        setSelect('tkm-supplier', line.supplier_id || '');
        setSelect('tkm-airline', line.airline_id || '');
        setSelect('tkm-agent', line.sales_agent_id || '');
        document.getElementById('tkm-pnr').value = line.pnr || '';
        document.getElementById('tkm-trip-type').value = line.trip_type || 'one_way';
        document.getElementById('tkm-ticket-type').value = line.ticket_type || 'international';
        document.getElementById('tkm-leg1-from').value = line.leg1_from || '';
        setStays('leg1', line.leg1_stay);
        document.getElementById('tkm-leg1-to').value = line.leg1_to || '';
        document.getElementById('tkm-leg2-from').value = line.leg2_from || '';
        setStays('leg2', line.leg2_stay);
        document.getElementById('tkm-leg2-to').value = line.leg2_to || '';
        document.getElementById('tkm-leg1-dep-date').value = line.leg1_departure_date || '';
        document.getElementById('tkm-leg1-dep-time').value = (line.leg1_departure_time || '').slice(0, 5);
        document.getElementById('tkm-leg2-dep-date').value = line.leg2_departure_date || '';
        document.getElementById('tkm-leg2-dep-time').value = (line.leg2_departure_time || '').slice(0, 5);
        document.getElementById('tkm-fare').value = line.fare_amount || 0;
        document.getElementById('tkm-tax').value = line.tax_amount || 0;
        document.getElementById('tkm-psf-basis').value = line.psf_basis || 'fare';

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
            pairEl(key, 'pct').dataset.shown = pairEl(key, 'pct').value;
            pairEl(key, 'amt').dataset.shown = pairEl(key, 'amt').value;
            pairEl(key, 'mode').value = line[modeF] === 'amount' ? 'amount' : 'percent';
        });

        applyPairModes();
        applyTripType();
    }

    fillSelect(document.getElementById('tkm-supplier'), data.suppliers, 'id', s => s.name, '— none —');
    fillSelect(document.getElementById('tkm-airline'), data.airlines, 'id', a => a.name + ' (' + a.code + ')', '— auto from first ticket # —');
    fillSelect(document.getElementById('tkm-agent'), data.agents, 'id', u => u.name, '— none —');

    // Typing in either side of a pair makes that side the "entered" one.
    PAIRS.forEach(({ key }) => {
        // Whichever box the user types in becomes the "entered" side; the
        // other one is calculated (both directions). Listen to input, keyup
        // and change so every browser / keyboard / paste path is caught, but
        // only react when the value really changed (tabbing in changes nothing).
        ['pct', 'amt'].forEach(part => {
            const el = pairEl(key, part);
            el.dataset.shown = el.value;
            const onEdit = () => {
                if (el.value === el.dataset.shown) return;
                el.dataset.shown = el.value;
                pairEl(key, 'mode').value = part === 'pct' ? 'percent' : 'amount';
                applyPairModes();
                masterChanged();
            };
            ['input', 'keyup', 'change'].forEach(ev => el.addEventListener(ev, onEdit));
            el.addEventListener('blur', recalcMaster);
        });
    });

    ['tkm-fare', 'tkm-tax', 'tkm-pnr', 'tkm-leg1-from', 'tkm-leg1-to', 'tkm-leg2-from', 'tkm-leg2-to',
     'tkm-leg1-dep-date', 'tkm-leg1-dep-time', 'tkm-leg2-dep-date', 'tkm-leg2-dep-time'].forEach(id => {
        document.getElementById(id).addEventListener('input', masterChanged);
    });
    ['tkm-supplier', 'tkm-airline', 'tkm-agent', 'tkm-ticket-type', 'tkm-psf-basis'].forEach(id => {
        document.getElementById(id).addEventListener('change', masterChanged);
    });
    document.getElementById('tkm-trip-type').addEventListener('change', () => { applyTripType(); masterChanged(); });

    document.getElementById('tkm-add-ticket-btn').addEventListener('click', () => {
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
        document.querySelectorAll('#tkm-root .tkm-select2').forEach(el => {
            $(el).select2({ width: '100%', placeholder: el.options[0] ? el.options[0].text : '', allowClear: !!(el.options[0] && el.options[0].value === '') });
            $(el).on('select2:select select2:clear', () => {
                el.dispatchEvent(new Event('change', { bubbles: true }));
                clearFieldError(el);
            });
        });
    });

    function clearFieldError(el) {
        el.classList.remove('is-invalid');
        const s2 = el.nextElementSibling;
        if (s2 && s2.classList.contains('select2')) {
            const sel = s2.querySelector('.select2-selection');
            if (sel) sel.style.borderColor = '';
        }
    }

    // ---- Hooks used by the tour form (Invoice Summary + error display) ----
    window.TourTicketTab = {
        // One row per ticket for the Invoice Summary tab: receivable = Ticket
        // Total, payable = Supplier Net, income = the difference (same as the
        // server's ServiceLine figures).
        summaryRows() {
            return [...wrap.querySelectorAll('.ticket-row')].map(row => ({
                label: (row.querySelector('.f-pax-name').value || '—') + (row.querySelector('.f-ticket-no').value ? ' (' + row.querySelector('.f-ticket-no').value + ')' : ''),
                currency: 'PKR',
                recv: perTicketTotal,
                pay: perTicketSupplierNet,
                income: round2(perTicketTotal - perTicketSupplierNet),
            }));
        },
        // Maps a server error key (lines.N.detail.ticket_no, lines.N.detail.psf_percent …) to its input.
        fieldForKey(key) {
            const m = key.match(/^lines\.(\d+)\.(?:detail\.)?([a-z0-9_]+)$/);
            if (!m) return null;
            const row = wrap.querySelector('.ticket-row[data-line-idx="' + m[1] + '"]');
            if (!row) return null;
            const field = m[2] === 'description' ? 'pax_name' : m[2];
            if (SHARED_FIELD_IDS[field]) return document.getElementById(SHARED_FIELD_IDS[field]);
            return row.querySelector('[data-name="' + field + '"]');
        },
        isSharedKey(key) {
            const m = key.match(/^lines\.(\d+)\.(?:detail\.)?([a-z0-9_]+)$/);
            return !!(m && wrap.querySelector('.ticket-row[data-line-idx="' + m[1] + '"]') && SHARED_FIELD_IDS[m[2]]);
        },
        // Partial ticket # check before submitting (blank is fine).
        clientErrors() {
            const errors = {};
            wrap.querySelectorAll('.ticket-row').forEach((row, i) => {
                const paxName = row.querySelector('.f-pax-name').value.trim();
                const digitCount = digitsOf(row.querySelector('.f-ticket-no').value).length;
                if (paxName && digitCount > 0 && digitCount !== 13) {
                    errors['lines.' + row.dataset.lineIdx + '.detail.ticket_no'] = ['Ticket row ' + (i + 1) + ': Ticket # has ' + digitCount +
                        ' digit(s), needs exactly 13 (format 220-1234-567-890). Finish it, or clear it to auto-fill from ticket #1.'];
                }
            });
            return errors;
        },
        beforeSubmit() {
            recalcMaster();
            syncAllRowsFromMaster();
            wrap.querySelectorAll('.f-ticket-no').forEach(el => { el.value = formatTicketNo(el.value); });
        },
        clearFieldError,
    };
})();
</script>
