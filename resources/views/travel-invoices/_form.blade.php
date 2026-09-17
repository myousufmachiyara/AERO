@php
    // Shared by both Sale Invoice (tickets only) and Tour Invoice (all
    // service types) — $serviceTypes (from the controller) decides which
    // tabs render. $invoice / $quotation may both be null (fresh, blank
    // invoice) or exactly one may be set (editing an invoice, or creating
    // one from an approved quotation).
    $invoice = $invoice ?? null;
    $quotation = $quotation ?? null;
    $existingPassengers = $invoice->passengers ?? ($quotation->passengers ?? collect());
    $tabTypes = ['ticket' => 'Ticket', 'hotel' => 'Hotel', 'transport' => 'Transport', 'visa' => 'Visa', 'other' => 'Other Services'];
    $activeTabs = array_intersect_key($tabTypes, array_flip($serviceTypes));

    // Serialize existing lines (from an invoice being edited) or a
    // quotation's flat lines (being converted) into one JSON shape the JS
    // below understands — quotation lines simply have no `detail`/`charges`.
    $linesJson = [];
    if ($invoice) {
        foreach ($invoice->serviceLines as $line) {
            $detail = null;
            switch ($line->service_type) {
                case 'ticket':
                    if ($line->ticketDetail) {
                        $detail = $line->ticketDetail->only(['pnr', 'gds', 'airline', 'ticket_no', 'ticket_type', 'sector', 'tour_code']);
                        $detail['issue_date'] = optional($line->ticketDetail->issue_date)->format('Y-m-d');
                        $detail['flights'] = $line->ticketDetail->flights->map(fn ($f) => [
                            'city' => $f->city, 'flight_no' => $f->flight_no,
                            'dep_date' => optional($f->dep_date)->format('Y-m-d'),
                            'dep_time' => $f->dep_time, 'arr_time' => $f->arr_time, 'fare_basis' => $f->fare_basis,
                        ])->values();
                    }
                    break;
                case 'hotel':
                    if ($line->hotelDetail) {
                        $detail = $line->hotelDetail->only(['hotel_id', 'hotel_room_id', 'nights', 'room_qty', 'extra_bed_qty', 'booking_name']);
                        $detail['check_in'] = optional($line->hotelDetail->check_in)->format('Y-m-d');
                        $detail['check_out'] = optional($line->hotelDetail->check_out)->format('Y-m-d');
                    }
                    break;
                case 'transport':
                    if ($line->transportDetail) {
                        $detail = $line->transportDetail->only(['vehicle_id', 'sector', 'booking_name']);
                    }
                    break;
                case 'visa':
                    if ($line->visaDetail) {
                        $detail = $line->visaDetail->only(['visa_type_id', 'reference_no']);
                        $detail['apply_date'] = optional($line->visaDetail->apply_date)->format('Y-m-d');
                        $detail['expiry_date'] = optional($line->visaDetail->expiry_date)->format('Y-m-d');
                    }
                    break;
                case 'other':
                    if ($line->otherDetail) {
                        $detail = $line->otherDetail->only(['service_id', 'qty']);
                    }
                    break;
            }

            $linesJson[] = [
                'service_type' => $line->service_type,
                'supplier_id' => $line->supplier_id,
                'description' => $line->description,
                'currency' => $line->currency,
                'exchange_rate' => (float) $line->exchange_rate,
                'receivable_f_amount' => (float) $line->receivable_f_amount,
                'payable_f_amount' => (float) $line->payable_f_amount,
                'detail' => $detail,
                'charges' => $line->charges->map(fn ($c) => [
                    'charge_type_id' => $c->charge_type_id, 'value' => (float) $c->value, 'computed_amount' => (float) $c->computed_amount,
                ])->values(),
            ];
        }
    } elseif ($quotation) {
        foreach ($quotation->serviceLines as $line) {
            $linesJson[] = [
                'service_type' => $line->service_type,
                'supplier_id' => $line->supplier_id,
                'description' => $line->description,
                'currency' => $line->currency,
                'exchange_rate' => (float) $line->exchange_rate,
                'receivable_f_amount' => (float) $line->receivable_f_amount,
                'payable_f_amount' => (float) $line->payable_f_amount,
                'detail' => null,
                'charges' => [],
            ];
        }
    }
@endphp

<style>
    .line-card { border: 1px solid #dee2e6; border-radius: .375rem; padding: .75rem; margin-bottom: .75rem; background: #fbfbfb; }
    .line-card .form-label { font-size: .78rem; margin-bottom: .15rem; color: #555; }
    .line-income.negative { color: #dc3545; font-weight: 600; }
    .line-income.positive { color: #198754; font-weight: 600; }
    .mini-table th, .mini-table td { padding: .25rem .4rem; vertical-align: middle; }
    #paxTable th { background: #f8f9fa; }
</style>

<ul class="nav nav-tabs" id="invoiceTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-general" type="button">General Information</button></li>
    @foreach($activeTabs as $type => $label)
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-{{ $type }}" type="button">{{ $label }}</button></li>
    @endforeach
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-summary" type="button">Invoice Summary</button></li>
</ul>

<div class="tab-content border border-top-0 p-3 mb-3">

    {{-- ============ General Information ============ --}}
    <div class="tab-pane fade show active" id="tab-general">
        @if($quotation)
        <div class="alert alert-info">Pulled from Quotation <strong>{{ $quotation->quotation_no }}</strong> — customer, passengers and service lines below were auto-fetched from it.</div>
        <input type="hidden" name="quotation_id" value="{{ $quotation->id }}">
        @elseif($invoice && $invoice->quotation_id)
        <input type="hidden" name="quotation_id" value="{{ $invoice->quotation_id }}">
        <p class="text-muted">Linked to Quotation <a href="{{ route('quotations.show', $invoice->quotation_id) }}">{{ $invoice->quotation->quotation_no ?? '#' . $invoice->quotation_id }}</a>.</p>
        @endif

        <div class="row">
            <div class="col-md-2 mb-3">
                <label class="form-label">Invoice Date<span class="text-danger">*</span></label>
                <input type="date" name="invoice_date" class="form-control" value="{{ old('invoice_date', optional($invoice?->invoice_date)->format('Y-m-d') ?? date('Y-m-d')) }}" required>
            </div>
            <div class="col-md-2 mb-3">
                <label class="form-label">Visit Type</label>
                <input type="text" name="visit_type" class="form-control" value="{{ old('visit_type', $invoice->visit_type ?? $quotation->visit_type ?? '') }}">
            </div>
            <div class="col-md-2 mb-3">
                <label class="form-label">Payment Mode</label>
                <select name="payment_mode" class="form-control">
                    @foreach(['credit' => 'Credit', 'cash' => 'Cash'] as $val => $lbl)
                    <option value="{{ $val }}" @selected(old('payment_mode', $invoice->payment_mode ?? 'credit') === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Customer<span class="text-danger">*</span></label>
                <select name="customer_id" class="form-control select2-js" required>
                    <option value="">Select Customer</option>
                    @foreach($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id', $invoice->customer_id ?? $quotation->customer_id ?? '') == $c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Name on Invoice</label>
                <input type="text" name="name_on_invoice" class="form-control" value="{{ old('name_on_invoice', $invoice->name_on_invoice ?? '') }}">
            </div>
        </div>
        <div class="row">
            <div class="col-md-3 mb-3">
                <label class="form-label">Cost Center</label>
                <input type="text" name="cost_center" class="form-control" value="{{ old('cost_center', $invoice->cost_center ?? '') }}">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Staff</label>
                <select name="staff_id" class="form-control select2-js">
                    <option value="">— None —</option>
                    @foreach($staffUsers as $u)<option value="{{ $u->id }}" @selected(old('staff_id', $invoice->staff_id ?? '') == $u->id)>{{ $u->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Remarks</label>
                <input type="text" name="remarks" class="form-control" value="{{ old('remarks', $invoice->remarks ?? '') }}">
            </div>
        </div>

        <hr>
        <h6>Passengers</h6>
        <table class="table table-bordered table-sm" id="paxTable">
            <thead><tr><th>Name</th><th width="16%">Passport / NIC</th><th width="12%">Pax Type</th><th width="14%">Nationality</th><th width="14%">DOB</th><th width="50px"></th></tr></thead>
            <tbody>
                @forelse($existingPassengers as $i => $pax)
                <tr>
                    <td><input type="text" name="passengers[{{ $i }}][name]" class="form-control" value="{{ $pax->name }}"></td>
                    <td><input type="text" name="passengers[{{ $i }}][passport_no_nic]" class="form-control" value="{{ $pax->passport_no_nic }}"></td>
                    <td>
                        <select name="passengers[{{ $i }}][pax_type]" class="form-control">
                            <option value="adult" @selected($pax->pax_type === 'adult')>Adult</option>
                            <option value="child" @selected($pax->pax_type === 'child')>Child</option>
                            <option value="infant" @selected($pax->pax_type === 'infant')>Infant</option>
                        </select>
                    </td>
                    <td><input type="text" name="passengers[{{ $i }}][nationality]" class="form-control" value="{{ $pax->nationality }}"></td>
                    <td><input type="date" name="passengers[{{ $i }}][dob]" class="form-control" value="{{ optional($pax->dob)->format('Y-m-d') }}"></td>
                    <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                </tr>
                @empty
                <tr>
                    <td><input type="text" name="passengers[0][name]" class="form-control"></td>
                    <td><input type="text" name="passengers[0][passport_no_nic]" class="form-control"></td>
                    <td>
                        <select name="passengers[0][pax_type]" class="form-control">
                            <option value="adult">Adult</option><option value="child">Child</option><option value="infant">Infant</option>
                        </select>
                    </td>
                    <td><input type="text" name="passengers[0][nationality]" class="form-control"></td>
                    <td><input type="date" name="passengers[0][dob]" class="form-control"></td>
                    <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <button type="button" class="btn btn-success btn-sm" onclick="addPaxRow()">+ Add Passenger</button>
    </div>

    {{-- ============ One tab + one <template> per service type ============ --}}
    @foreach($activeTabs as $type => $label)
    <div class="tab-pane fade" id="tab-{{ $type }}">
        <div id="cards-{{ $type }}"></div>
        <button type="button" class="btn btn-success btn-sm" onclick="addLineCard('{{ $type }}')">+ Add {{ $label }} Line</button>
    </div>

    <template id="tpl-{{ $type }}">
        <div class="line-card" data-type="{{ $type }}" data-flight-idx="0" data-charge-idx="0">
            <input type="hidden" name="lines[__IDX__][service_type]" value="{{ $type }}">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="form-label">Supplier</label>
                    <select name="lines[__IDX__][supplier_id]" class="form-control select2-js line-field">
                        <option value="">— None —</option>
                        @foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->is_flagged ? '⚠ ' : '' }}{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-1 mb-2">
                    <label class="form-label">Currency</label>
                    <input type="text" name="lines[__IDX__][currency]" class="form-control line-currency line-field" maxlength="3" value="PKR">
                </div>
                <div class="col-md-1 mb-2">
                    <label class="form-label">Exch. Rate</label>
                    <input type="number" step="any" name="lines[__IDX__][exchange_rate]" class="form-control line-rate line-field" value="1">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Receivable (F)</label>
                    <input type="number" step="any" name="lines[__IDX__][receivable_f_amount]" class="form-control line-recv line-field" value="0">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Payable (F)</label>
                    <input type="number" step="any" name="lines[__IDX__][payable_f_amount]" class="form-control line-pay line-field" value="0">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Income (Local)</label>
                    <div class="form-control-plaintext line-income fw-bold">0.00</div>
                </div>
                <div class="col-md-1 mb-2 text-end">
                    <label class="form-label d-block">&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.line-card').remove(); recalcTotals();"><i class="fas fa-times"></i></button>
                </div>
            </div>

            @if($type === 'ticket')
            <div class="row">
                <div class="col-md-2 mb-2"><label class="form-label">PNR</label><input type="text" name="lines[__IDX__][detail][pnr]" class="form-control"></div>
                <div class="col-md-2 mb-2"><label class="form-label">GDS</label><input type="text" name="lines[__IDX__][detail][gds]" class="form-control"></div>
                <div class="col-md-2 mb-2"><label class="form-label">Airline</label><input type="text" name="lines[__IDX__][detail][airline]" class="form-control"></div>
                <div class="col-md-2 mb-2"><label class="form-label">Ticket No</label><input type="text" name="lines[__IDX__][detail][ticket_no]" class="form-control"></div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Ticket Type</label>
                    <select name="lines[__IDX__][detail][ticket_type]" class="form-control">
                        <option value="international">International</option>
                        <option value="domestic">Domestic</option>
                    </select>
                </div>
                <div class="col-md-2 mb-2"><label class="form-label">Issue Date</label><input type="date" name="lines[__IDX__][detail][issue_date]" class="form-control"></div>
                <div class="col-md-3 mb-2"><label class="form-label">Sector</label><input type="text" name="lines[__IDX__][detail][sector]" class="form-control" placeholder="e.g. KHI-JED-KHI"></div>
                <div class="col-md-3 mb-2"><label class="form-label">Tour Code</label><input type="text" name="lines[__IDX__][detail][tour_code]" class="form-control"></div>
            </div>
            <div class="mb-2">
                <label class="form-label d-block">Flight Legs</label>
                <table class="table table-bordered table-sm mini-table flights-table">
                    <thead><tr><th>City</th><th>Flight No</th><th>Dep Date</th><th>Dep Time</th><th>Arr Time</th><th>Fare Basis</th><th width="36"></th></tr></thead>
                    <tbody></tbody>
                </table>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addFlightRow(this.closest('.line-card'))">+ Add Flight Leg</button>
            </div>
            <template class="flight-tpl">
                <tr>
                    <td><input type="text" name="lines[__IDX__][detail][flights][__FIDX__][city]" class="form-control form-control-sm"></td>
                    <td><input type="text" name="lines[__IDX__][detail][flights][__FIDX__][flight_no]" class="form-control form-control-sm"></td>
                    <td><input type="date" name="lines[__IDX__][detail][flights][__FIDX__][dep_date]" class="form-control form-control-sm"></td>
                    <td><input type="text" name="lines[__IDX__][detail][flights][__FIDX__][dep_time]" class="form-control form-control-sm" placeholder="HH:MM"></td>
                    <td><input type="text" name="lines[__IDX__][detail][flights][__FIDX__][arr_time]" class="form-control form-control-sm" placeholder="HH:MM"></td>
                    <td><input type="text" name="lines[__IDX__][detail][flights][__FIDX__][fare_basis]" class="form-control form-control-sm"></td>
                    <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                </tr>
            </template>
            @endif

            @if($type === 'hotel')
            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="form-label">Hotel</label>
                    <select name="lines[__IDX__][detail][hotel_id]" class="form-control select2-js hotel-select" onchange="onHotelChange(this)">
                        <option value="">— None —</option>
                        @foreach($hotels as $h)<option value="{{ $h->id }}">{{ $h->name }} ({{ $h->city }})</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Room Type</label>
                    <select name="lines[__IDX__][detail][hotel_room_id]" class="form-control select2-js room-select">
                        <option value="">— Select Hotel First —</option>
                    </select>
                </div>
                <div class="col-md-2 mb-2"><label class="form-label">Check-in</label><input type="date" name="lines[__IDX__][detail][check_in]" class="form-control"></div>
                <div class="col-md-2 mb-2"><label class="form-label">Check-out</label><input type="date" name="lines[__IDX__][detail][check_out]" class="form-control"></div>
                <div class="col-md-2 mb-2"><label class="form-label">Nights</label><input type="number" name="lines[__IDX__][detail][nights]" class="form-control" value="1"></div>
                <div class="col-md-2 mb-2"><label class="form-label">Room Qty</label><input type="number" name="lines[__IDX__][detail][room_qty]" class="form-control" value="1"></div>
                <div class="col-md-2 mb-2"><label class="form-label">Extra Bed Qty</label><input type="number" name="lines[__IDX__][detail][extra_bed_qty]" class="form-control" value="0"></div>
                <div class="col-md-4 mb-2"><label class="form-label">Booking Name</label><input type="text" name="lines[__IDX__][detail][booking_name]" class="form-control"></div>
            </div>
            @endif

            @if($type === 'transport')
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Vehicle</label>
                    <select name="lines[__IDX__][detail][vehicle_id]" class="form-control select2-js">
                        <option value="">— None —</option>
                        @foreach($vehicles as $v)<option value="{{ $v->id }}">{{ $v->name }} ({{ $v->type }})</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-2"><label class="form-label">Sector</label><input type="text" name="lines[__IDX__][detail][sector]" class="form-control"></div>
                <div class="col-md-4 mb-2"><label class="form-label">Booking Name</label><input type="text" name="lines[__IDX__][detail][booking_name]" class="form-control"></div>
            </div>
            @endif

            @if($type === 'visa')
            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="form-label">Visa Type</label>
                    <select name="lines[__IDX__][detail][visa_type_id]" class="form-control select2-js">
                        <option value="">— None —</option>
                        @foreach($visaTypes as $vt)<option value="{{ $vt->id }}">{{ $vt->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2"><label class="form-label">Apply Date</label><input type="date" name="lines[__IDX__][detail][apply_date]" class="form-control"></div>
                <div class="col-md-3 mb-2"><label class="form-label">Expiry Date</label><input type="date" name="lines[__IDX__][detail][expiry_date]" class="form-control"></div>
                <div class="col-md-3 mb-2"><label class="form-label">Reference No</label><input type="text" name="lines[__IDX__][detail][reference_no]" class="form-control"></div>
            </div>
            @endif

            @if($type === 'other')
            <div class="row">
                <div class="col-md-6 mb-2">
                    <label class="form-label">Service</label>
                    <select name="lines[__IDX__][detail][service_id]" class="form-control select2-js">
                        <option value="">— None —</option>
                        @foreach($services as $sv)<option value="{{ $sv->id }}">{{ $sv->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2"><label class="form-label">Qty</label><input type="number" name="lines[__IDX__][detail][qty]" class="form-control" value="1"></div>
            </div>
            @endif

            <div class="mb-1">
                <label class="form-label d-block">Charges (SPO / WHT / COM / PSF / Tax ...)</label>
                <table class="table table-bordered table-sm mini-table charges-table">
                    <thead><tr><th>Charge Type</th><th width="18%">Value</th><th width="20%">Amount (+/-)</th><th width="36"></th></tr></thead>
                    <tbody></tbody>
                </table>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addChargeRow(this.closest('.line-card'))">+ Add Charge</button>
            </div>
            <template class="charge-tpl">
                <tr>
                    <td>
                        <select name="lines[__IDX__][charges][__CIDX__][charge_type_id]" class="form-control form-control-sm charge-type" onchange="onChargeTypeChange(this)">
                            <option value="">— Select —</option>
                            @foreach($chargeTypes as $ct)<option value="{{ $ct->id }}" data-calc="{{ $ct->calculation_type }}" data-default="{{ $ct->default_value }}">{{ $ct->name }}</option>@endforeach
                        </select>
                    </td>
                    <td><input type="number" step="any" name="lines[__IDX__][charges][__CIDX__][value]" class="form-control form-control-sm charge-value" value="0"></td>
                    <td><input type="number" step="any" name="lines[__IDX__][charges][__CIDX__][computed_amount]" class="form-control form-control-sm charge-amount" value="0"></td>
                    <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); recalcCard(this.closest('.line-card'));"><i class="fas fa-times"></i></button></td>
                </tr>
            </template>
        </div>
    </template>
    @endforeach

    {{-- ============ Invoice Summary ============ --}}
    <div class="tab-pane fade" id="tab-summary">
        <table class="table table-bordered">
            <thead><tr><th>#</th><th>Type</th><th>Description</th><th>Currency</th><th>Receivable</th><th>Payable</th><th>Income</th></tr></thead>
            <tbody id="summaryBody"><tr><td colspan="7" class="text-center text-muted">No lines added yet.</td></tr></tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="4" class="text-end">Totals</td>
                    <td id="totalReceivable">0.00</td>
                    <td id="totalPayable">0.00</td>
                    <td id="totalIncome">0.00</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script>
    const existingLines = @json($linesJson);
    window.hotelsData = @json($hotels->map(fn ($h) => ['id' => $h->id, 'rooms' => $h->rooms->map(fn ($r) => ['id' => $r->id, 'room_type' => $r->room_type])->values()])->values());
    let paxIndex = {{ max($existingPassengers->count(), 1) }};
    let lineIndex = 0;

    function addPaxRow() {
        const tbody = document.querySelector('#paxTable tbody');
        const clone = tbody.querySelector('tr').cloneNode(true);
        clone.querySelectorAll('input, select').forEach(el => {
            el.name = el.name.replace(/passengers\[\d+\]/, `passengers[${paxIndex}]`);
            if (el.tagName === 'SELECT') el.selectedIndex = 0; else el.value = '';
        });
        tbody.appendChild(clone);
        paxIndex++;
    }

    function onHotelChange(select) {
        const card = select.closest('.line-card');
        const roomSelect = card.querySelector('.room-select');
        const hotelId = select.value;
        const hotel = (window.hotelsData || []).find(h => String(h.id) === String(hotelId));
        roomSelect.innerHTML = '<option value="">— None —</option>';
        (hotel ? hotel.rooms : []).forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.id;
            opt.textContent = r.room_type;
            roomSelect.appendChild(opt);
        });
        if (window.jQuery) $(roomSelect).trigger('change.select2') || $(roomSelect).select2({dropdownParent: card});
    }

    function onChargeTypeChange(select) {
        const opt = select.options[select.selectedIndex];
        const row = select.closest('tr');
        const valueInput = row.querySelector('.charge-value');
        if (opt && opt.dataset.default) {
            valueInput.value = opt.dataset.default;
        }
        recalcChargeRow(row, opt);
    }

    function recalcChargeRow(row, opt) {
        opt = opt || row.querySelector('.charge-type').selectedOptions[0];
        const card = row.closest('.line-card');
        const value = parseFloat(row.querySelector('.charge-value').value) || 0;
        const amountInput = row.querySelector('.charge-amount');
        if (opt && opt.dataset.calc === 'percentage') {
            const rate = parseFloat(card.querySelector('.line-rate').value) || 0;
            const recv = parseFloat(card.querySelector('.line-recv').value) || 0;
            amountInput.value = ((recv * rate) * value / 100).toFixed(2);
        }
        // 'fixed' charges leave the amount as whatever the user typed in Value.
        recalcCard(card);
    }

    function addChargeRow(card, data) {
        const tpl = card.querySelector('.charge-tpl');
        const tbody = card.querySelector('.charges-table tbody');
        const cIdx = parseInt(card.dataset.chargeIdx || '0', 10);
        const lineIdx = card.querySelector('input[name$="[service_type]"]').name.match(/lines\[(\d+)\]/)[1];
        const row = tpl.content.firstElementChild.cloneNode(true);
        row.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace('__IDX__', lineIdx).replace('__CIDX__', cIdx);
        });
        tbody.appendChild(row);
        card.dataset.chargeIdx = cIdx + 1;
        if (window.jQuery) $(row).find('select').select2({dropdownParent: card});

        if (data) {
            const sel = row.querySelector('.charge-type');
            sel.value = data.charge_type_id || '';
            row.querySelector('.charge-value').value = data.value ?? 0;
            row.querySelector('.charge-amount').value = data.computed_amount ?? 0;
            if (window.jQuery) $(sel).trigger('change.select2');
        }
        row.querySelector('.charge-value').addEventListener('input', () => recalcChargeRow(row));
        row.querySelector('.charge-amount').addEventListener('input', () => recalcCard(card));
    }

    function addFlightRow(card, data) {
        const tpl = card.querySelector('.flight-tpl');
        const tbody = card.querySelector('.flights-table tbody');
        const fIdx = parseInt(card.dataset.flightIdx || '0', 10);
        const lineIdx = card.querySelector('input[name$="[service_type]"]').name.match(/lines\[(\d+)\]/)[1];
        const row = tpl.content.firstElementChild.cloneNode(true);
        row.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace('__IDX__', lineIdx).replace('__FIDX__', fIdx);
        });
        tbody.appendChild(row);
        card.dataset.flightIdx = fIdx + 1;

        if (data) {
            row.querySelectorAll('[name]').forEach(el => {
                const key = el.name.match(/\[(\w+)\]$/)[1];
                if (data[key] !== undefined && data[key] !== null) el.value = data[key];
            });
        }
    }

    function addLineCard(type, data) {
        data = data || {};
        const tpl = document.getElementById('tpl-' + type);
        const card = tpl.content.firstElementChild.cloneNode(true);
        const idx = lineIndex++;
        card.querySelectorAll('[name]').forEach(el => { el.name = el.name.replace(/__IDX__/g, idx); });

        card.querySelector('.line-recv').value = data.receivable_f_amount ?? 0;
        card.querySelector('.line-pay').value = data.payable_f_amount ?? 0;
        card.querySelector('.line-rate').value = data.exchange_rate ?? 1;
        card.querySelector('.line-currency').value = data.currency ?? 'PKR';
        ['input', 'change'].forEach(evt => {
            card.querySelectorAll('.line-recv, .line-pay, .line-rate').forEach(el => el.addEventListener(evt, () => recalcCard(card)));
        });

        document.getElementById('cards-' + type).appendChild(card);

        if (data.supplier_id) card.querySelector('[name$="[supplier_id]"]').value = data.supplier_id;

        const detail = data.detail || {};
        card.querySelectorAll('[name*="[detail]["]').forEach(el => {
            const m = el.name.match(/\[detail\]\[(\w+)\]$/);
            if (!m) return;
            const key = m[1];
            if (detail[key] !== undefined && detail[key] !== null) el.value = detail[key];
        });
        if (type === 'hotel' && detail.hotel_id) {
            const hotelSelect = card.querySelector('.hotel-select');
            hotelSelect.value = detail.hotel_id;
            onHotelChange(hotelSelect);
            if (detail.hotel_room_id) card.querySelector('.room-select').value = detail.hotel_room_id;
        }
        (detail.flights || []).forEach(f => addFlightRow(card, f));
        (data.charges || []).forEach(c => addChargeRow(card, c));

        if (window.jQuery) $(card).find('.select2-js').select2({dropdownParent: card});
        recalcCard(card);
        return card;
    }

    function recalcCard(card) {
        const rate = parseFloat(card.querySelector('.line-rate').value) || 0;
        const recv = parseFloat(card.querySelector('.line-recv').value) || 0;
        const pay = parseFloat(card.querySelector('.line-pay').value) || 0;
        let chargesTotal = 0;
        card.querySelectorAll('.charge-amount').forEach(el => chargesTotal += (parseFloat(el.value) || 0));
        const income = (recv * rate) - (pay * rate) + chargesTotal;
        const el = card.querySelector('.line-income');
        el.textContent = income.toFixed(2);
        el.className = 'line-income fw-bold ' + (income < 0 ? 'negative' : 'positive');
        recalcTotals();
    }

    function recalcTotals() {
        let totalRecv = 0, totalPay = 0, totalIncome = 0;
        const rows = [];
        document.querySelectorAll('.line-card').forEach(card => {
            const rate = parseFloat(card.querySelector('.line-rate').value) || 0;
            const recv = parseFloat(card.querySelector('.line-recv').value) || 0;
            const pay = parseFloat(card.querySelector('.line-pay').value) || 0;
            let chargesTotal = 0;
            card.querySelectorAll('.charge-amount').forEach(el => chargesTotal += (parseFloat(el.value) || 0));
            const income = (recv * rate) - (pay * rate) + chargesTotal;
            totalRecv += recv * rate;
            totalPay += pay * rate;
            totalIncome += income;

            const supplierSelect = card.querySelector('[name$="[supplier_id]"]');
            const supplierLabel = supplierSelect.selectedOptions[0] ? supplierSelect.selectedOptions[0].textContent : '—';
            rows.push(`<tr><td>${rows.length + 1}</td><td>${card.dataset.type}</td><td>${supplierLabel}</td><td>${card.querySelector('.line-currency').value}</td><td>${(recv*rate).toFixed(2)}</td><td>${(pay*rate).toFixed(2)}</td><td class="${income < 0 ? 'text-danger' : 'text-success'}">${income.toFixed(2)}</td></tr>`);
        });
        document.getElementById('summaryBody').innerHTML = rows.length ? rows.join('') : '<tr><td colspan="7" class="text-center text-muted">No lines added yet.</td></tr>';
        document.getElementById('totalReceivable').textContent = totalRecv.toFixed(2);
        document.getElementById('totalPayable').textContent = totalPay.toFixed(2);
        document.getElementById('totalIncome').textContent = totalIncome.toFixed(2);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const grouped = {};
        existingLines.forEach(line => {
            grouped[line.service_type] = grouped[line.service_type] || [];
            grouped[line.service_type].push(line);
        });
        Object.keys(grouped).forEach(type => {
            if (document.getElementById('tpl-' + type)) {
                grouped[type].forEach(line => addLineCard(type, line));
            }
        });
        recalcTotals();
    });
</script>
