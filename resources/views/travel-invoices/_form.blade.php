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
    // Templates grouped by service_category so each tab's Template dropdown
    // only lists the rate cards built for that tab (e.g. "Catalyst Visa
    // 22/07/2026" only appears on the Visa tab) — matches the "Template"
    // dropdown seen in every reference screenshot.
    $chargeTemplatesByType = $chargeTemplates->groupBy('service_category');

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
                        // hotel_room_id/room_view_id/room_qty kept for
                        // back-compat with lines saved before Room Details
                        // became a grid (see InvoiceHotelRoom below) — the
                        // JS synthesizes one grid row from these if the
                        // line has no `rooms` of its own.
                        $detail = $line->hotelDetail->only([
                            'hotel_id', 'hotel_room_id', 'room_view_id', 'nights', 'room_qty', 'booking_name',
                            'receivable_currency', 'receivable_exchange_rate', 'payable_currency', 'payable_exchange_rate',
                            'agent_commission_percent', 'agent_commission_amount',
                        ]);
                        $detail['check_in'] = optional($line->hotelDetail->check_in)->format('Y-m-d');
                        $detail['check_out'] = optional($line->hotelDetail->check_out)->format('Y-m-d');
                        $detail['rooms'] = $line->hotelDetail->rooms->map(fn ($r) => [
                            'hotel_room_id' => $r->hotel_room_id,
                            'room_view_id' => $r->room_view_id,
                            'qty' => $r->qty,
                            'rate' => (float) $r->rate,
                            'cost' => (float) $r->cost,
                        ])->values();
                    }
                    break;
                case 'transport':
                    if ($line->transportDetail) {
                        // vehicle_id/sector kept here for back-compat with
                        // lines saved before Vehicle Details became a grid
                        // (see InvoiceTransportVehicle below) — the JS
                        // synthesizes one grid row from these if the line
                        // has no `vehicles` of its own. reference_no/
                        // category are real saved fields, plus the same
                        // dual receivable/payable currency+rate and agent
                        // commission fields as Hotel's "Charges Details".
                        $detail = $line->transportDetail->only([
                            'vehicle_id', 'sector', 'booking_name', 'reference_no', 'category',
                            'receivable_currency', 'receivable_exchange_rate', 'payable_currency', 'payable_exchange_rate',
                            'agent_commission_percent', 'agent_commission_amount',
                        ]);
                        $detail['vehicles'] = $line->transportDetail->vehicles->map(fn ($v) => [
                            'vehicle_id' => $v->vehicle_id,
                            'sector' => $v->sector,
                        ])->values();
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
                'charge_template_id' => $line->charge_template_id,
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
                'charge_template_id' => $line->charge_template_id ?? null,
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
    .not-persisted-field { background-image: linear-gradient(45deg, rgba(255,193,7,.08) 25%, transparent 25%, transparent 50%, rgba(255,193,7,.08) 50%, rgba(255,193,7,.08) 75%, transparent 75%, transparent); background-size: 8px 8px; }
    .field-note { font-size: .72rem; color: #997404; }
</style>
<div class="tabs">
    <ul class="nav nav-tabs" id="invoiceTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-general" type="button">General Information</button></li>
        @foreach($activeTabs as $type => $label)
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-{{ $type }}" type="button">{{ $label }}</button></li>
        @endforeach
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-summary" type="button">Invoice Summary</button></li>
    </ul>
</div>
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
        {{--
            Client fix: Cost Center removed from this tab per feedback.
            (Note: "Visa Type" was also asked to be removed from General
            Information, but no such field has ever existed here — it only
            exists inside the separate Visa tab below, where it stays, since
            removing it from the Visa tab wasn't asked for. Flagged in the
            delivery notes rather than guessed at.)
        --}}
        <div class="row">
            <div class="col-md-3 mb-3">
                <label class="form-label">Staff</label>
                <select name="staff_id" class="form-control select2-js">
                    <option value="">— None —</option>
                    @foreach($staffUsers as $u)<option value="{{ $u->id }}" @selected(old('staff_id', $invoice->staff_id ?? '') == $u->id)>{{ $u->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-9 mb-3">
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

    {{-- ============ Ticket tab: identical to the Ticket Sale Invoice (see _ticket_tab) ============ --}}
    @if(isset($activeTabs['ticket']))
    <div class="tab-pane fade" id="tab-ticket">
        @include('travel-invoices._ticket_tab')
    </div>
    @endif

    {{--
        ============ Hotel tab: dedicated layout (round 2 client fixes) ============
        Pulled out of the generic per-type loop below, same treatment as
        Ticket above. Broken into the 4 sections the client asked for:
        Booking Details / Room Details (now a repeatable grid, like the
        Charges grid) / Receivables / Payables. Costing Type removed.
        Nights is auto-calculated from Check-in/Check-out (readonly).
        Booking Name auto-fills from the Customer picked in General
        Information (still editable per line). PSF = Receivable(converted)
        − Payable(converted); Agent Commission is calculated and saved but
        NOT netted out of PSF, per client instruction.

        Judgment calls made here, flagged for review:
          1. "Template" and the "Other Charges / Discount" grid aren't in
             the client's 4-part field list, but are kept (Template at the
             end of Booking Details, Charges at the end of Payables) since
             nothing asked for their removal and they're real saved/useful
             features. Charges are intentionally NOT netted into PSF below
             (the client gave an exact "PSF = receivable − payable"
             formula) — easy to add back in if that's wanted.
          2. Receivable/Payable "Amount (Converted)" are shown live
             (receivable/payable × that side's own exchange rate) but not
             saved as their own column — they're just receivable/payable
             and the rate replayed through the same formula, so there's
             nothing to lose by recomputing them on load instead of
             storing a third copy.
          3. Agent Commission Amount IS saved (hidden field, client asked
             for "calculate commission and save it"), computed as
             PSF × Commission % ÷ 100.
    --}}
    @if(isset($activeTabs['hotel']))
    <div class="tab-pane fade" id="tab-hotel">
        <div id="cards-hotel"></div>
        <button type="button" class="btn btn-success btn-sm" onclick="addLineCard('hotel')">+ Add Hotel Line</button>
        <div class="card mt-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <span class="text-muted">Hotel lines total receivable (local currency)</span>
                <h5 class="mb-0" id="hotel-tab-total-receivable">0.00</h5>
            </div>
        </div>
    </div>

    <template id="tpl-hotel">
        <div class="line-card" data-type="hotel" data-charge-idx="0" data-room-idx="0">
            <input type="hidden" name="lines[__IDX__][service_type]" value="hotel">

            <h4 class="fw-bold text-uppercase text-dark mb-2">Booking Details</h4>
            <div class="row">
                <div class="col-md-2 mb-2"><label class="form-label">Check-in</label><input type="date" name="lines[__IDX__][detail][check_in]" class="form-control hotel-checkin" onchange="syncHotelNights(this.closest('.line-card'))"></div>
                <div class="col-md-2 mb-2"><label class="form-label">Check-out</label><input type="date" name="lines[__IDX__][detail][check_out]" class="form-control hotel-checkout" onchange="syncHotelNights(this.closest('.line-card'))"></div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Supplier</label>
                    <select name="lines[__IDX__][supplier_id]" class="form-control select2-js line-field">
                        <option value="">— None —</option>
                        @foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->is_flagged ? '⚠ ' : '' }}{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Hotel Name</label>
                    <select name="lines[__IDX__][detail][hotel_id]" class="form-control select2-js hotel-select" onchange="onHotelChange(this)">
                        <option value="">— None —</option>
                        @foreach($hotels as $h)<option value="{{ $h->id }}">{{ $h->name }} ({{ $h->city }})</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Category <small class="field-note">(not saved yet)</small></label>
                    <select class="form-control not-persisted-field">
                        @foreach(['umrah' => 'Umrah', 'hajj' => 'Hajj', 'holiday' => 'Holiday', 'tour' => 'Tour', 'visitor' => 'Visitor'] as $val => $lbl)
                        <option value="{{ $val }}">{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-2 mb-2">
                    <label class="form-label">No. of Nights <small class="field-note">(auto)</small></label>
                    <input type="number" min="0" name="lines[__IDX__][detail][nights]" class="form-control hotel-nights" value="0" readonly>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Booking Name <small class="field-note">(from Passengers)</small></label>
                    {{-- One passenger from General Information → Passengers; the
                         chosen name is what's saved (booking_name stays plain text). --}}
                    <select class="form-control hotel-booking-pax" onchange="onHotelBookingPaxChange(this)">
                        <option value="">— Select Passenger —</option>
                    </select>
                    <input type="hidden" name="lines[__IDX__][detail][booking_name]" class="hotel-booking-name">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Currency <small class="text-muted">(invoice)</small></label>
                    <select name="lines[__IDX__][currency]" class="form-control line-currency line-field">
                        @foreach($currencies as $cur)<option value="{{ $cur->code }}" @selected($cur->code === 'PKR')>{{ $cur->code }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2"><label class="form-label">Remarks</label><input type="text" name="lines[__IDX__][description]" class="form-control"></div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Booking Status<span class="text-danger">*</span> <small class="field-note">(not saved yet)</small></label>
                    <select class="form-control not-persisted-field">
                        <option value="confirmed" selected>Confirmed</option>
                        <option value="pending">Pending</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Template <small class="field-note">(optional — auto-fills Currency/Charges)</small></label>
                    <select class="form-control select2-js template-select" onchange="onTemplateChange(this)">
                        <option value="">— None —</option>
                        @foreach($chargeTemplatesByType->get('hotel', collect()) as $ct)
                        <option value="{{ $ct->id }}"
                            data-currency="{{ $ct->default_currency }}"
                            data-rate="{{ $ct->default_exchange_rate }}"
                            data-items='@json($ct->items->map(fn ($it) => ["charge_type_id" => $it->charge_type_id, "value" => (float) $it->value])->values())'>
                            {{ $ct->name }} ({{ optional($ct->effective_date)->format('d/m/Y') }})
                        </option>
                        @endforeach
                    </select>
                    <input type="hidden" name="lines[__IDX__][charge_template_id]" class="template-id-field">
                </div>
            </div>

            <hr class="my-2">
            <h4 class="fw-bold text-uppercase text-dark mb-2">Room Details</h4>
            {{-- Client fix: Rate (to customer) and Cost (to hotel) are per room per night.
                 Single Room Total = Rate × Nights, Rooms Total = Single Room Total × No. of Rooms
                 (same for Cost). Σ Rooms Total → Receivable, Σ Rooms Cost → Payable. --}}
            <div class="table-responsive">
            <table class="table table-bordered table-sm mini-table hotel-rooms-table align-middle">
                <thead>
                    <tr>
                        <th rowspan="2">Room Type</th><th rowspan="2">Room View</th><th rowspan="2" width="8%">No. of Rooms</th>
                        <th colspan="3" class="text-center table-success">Customer (Receivable)</th>
                        <th colspan="3" class="text-center table-warning">Hotel (Payable)</th>
                        <th rowspan="2" width="36"></th>
                    </tr>
                    <tr>
                        <th width="9%">Rate / Night</th><th width="9%">Single Room Total</th><th width="10%">Rooms Total</th>
                        <th width="9%">Cost / Night</th><th width="9%">Single Room Cost</th><th width="10%">Rooms Cost</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="3" class="text-end">Total <small class="text-muted fw-normal">(<span class="hotel-rooms-nights-label">0</span> night(s))</small></td>
                        <td></td><td></td><td class="text-end hotel-rooms-sum-total">0.00</td>
                        <td></td><td></td><td class="text-end hotel-rooms-sum-cost">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addHotelRoomRow(this.closest('.line-card'))">+ Add Room</button>
            <template class="hotel-room-tpl">
                <tr>
                    <td>
                        <select name="lines[__IDX__][detail][rooms][__RIDX__][hotel_room_id]" class="form-control form-control-sm select2-js hotel-room-type-select">
                            <option value="">— Select Hotel First —</option>
                        </select>
                    </td>
                    <td>
                        <select name="lines[__IDX__][detail][rooms][__RIDX__][room_view_id]" class="form-control form-control-sm select2-js hotel-room-view-select">
                            <option value="">— None —</option>
                            @foreach($roomViews as $rv)<option value="{{ $rv->id }}">{{ $rv->name }}</option>@endforeach
                        </select>
                    </td>
                    <td><input type="number" min="0" name="lines[__IDX__][detail][rooms][__RIDX__][qty]" class="form-control form-control-sm hotel-room-row-qty" value="1" oninput="recalcHotelRooms(this.closest('.line-card'), true)"></td>
                    <td><input type="number" step="any" min="0" name="lines[__IDX__][detail][rooms][__RIDX__][rate]" class="form-control form-control-sm hotel-room-row-rate" value="0" oninput="recalcHotelRooms(this.closest('.line-card'), true)"></td>
                    <td><input type="text" class="form-control form-control-sm text-end hotel-room-row-single" value="0.00" readonly tabindex="-1"></td>
                    <td><input type="number" step="any" name="lines[__IDX__][detail][rooms][__RIDX__][total_amount]" class="form-control form-control-sm text-end hotel-room-row-total" value="0" readonly tabindex="-1"></td>
                    <td><input type="number" step="any" min="0" name="lines[__IDX__][detail][rooms][__RIDX__][cost]" class="form-control form-control-sm hotel-room-row-cost" value="0" oninput="recalcHotelRooms(this.closest('.line-card'), true)"></td>
                    <td><input type="text" class="form-control form-control-sm text-end hotel-room-row-single-cost" value="0.00" readonly tabindex="-1"></td>
                    <td><input type="number" step="any" name="lines[__IDX__][detail][rooms][__RIDX__][total_cost]" class="form-control form-control-sm text-end hotel-room-row-total-cost" value="0" readonly tabindex="-1"></td>
                    <td><button type="button" class="btn btn-danger btn-sm" onclick="const c = this.closest('.line-card'); this.closest('tr').remove(); recalcHotelRooms(c, true);"><i class="fas fa-times"></i></button></td>
                </tr>
            </template>

            <hr class="my-2">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-uppercase text-muted small fw-bold mb-2">3. Receivables</h6>
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Receivable (F) <small class="field-note hotel-auto-note">(auto: Σ Rooms Total)</small></label>
                            <input type="number" step="any" name="lines[__IDX__][receivable_f_amount]" class="form-control line-recv line-field" value="0" oninput="recalcHotelFinance(this.closest('.line-card'))">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Exch. Rate</label>
                            <input type="number" step="any" name="lines[__IDX__][detail][receivable_exchange_rate]" class="form-control hotel-recv-rate" value="1" oninput="recalcHotelFinance(this.closest('.line-card'))">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Currency</label>
                            <select name="lines[__IDX__][detail][receivable_currency]" class="form-control hotel-recv-currency" onchange="recalcHotelFinance(this.closest('.line-card'))">
                                @foreach($currencies as $cur)<option value="{{ $cur->code }}" @selected($cur->code === 'PKR')>{{ $cur->code }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="text-end"><span class="text-muted">Receivable Amount (Converted):</span> <span class="fw-bold hotel-recv-converted">0.00</span></div>
                </div>
                <div class="col-md-6">
                    <h6 class="text-uppercase text-muted small fw-bold mb-2">4. Payables</h6>
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Payable (F) <small class="field-note hotel-auto-note">(auto: Σ Rooms Cost)</small></label>
                            <input type="number" step="any" name="lines[__IDX__][payable_f_amount]" class="form-control line-pay line-field" value="0" oninput="recalcHotelFinance(this.closest('.line-card'))">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Exch. Rate</label>
                            <input type="number" step="any" name="lines[__IDX__][detail][payable_exchange_rate]" class="form-control hotel-pay-rate" value="1" oninput="recalcHotelFinance(this.closest('.line-card'))">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Currency</label>
                            <select name="lines[__IDX__][detail][payable_currency]" class="form-control hotel-pay-currency" onchange="recalcHotelFinance(this.closest('.line-card'))">
                                @foreach($currencies as $cur)<option value="{{ $cur->code }}" @selected($cur->code === 'PKR')>{{ $cur->code }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="text-end"><span class="text-muted">Payable Amount (Converted):</span> <span class="fw-bold hotel-pay-converted">0.00</span></div>
                </div>
            </div>

            <hr class="my-2">
            <div class="row align-items-end">
                <div class="col-md-3 mb-2">
                    <label class="form-label">Agent Commission %</label>
                    <input type="number" step="any" min="0" max="100" name="lines[__IDX__][detail][agent_commission_percent]" class="form-control hotel-commission-pct" value="0" oninput="recalcHotelFinance(this.closest('.line-card'))">
                    <input type="hidden" name="lines[__IDX__][detail][agent_commission_amount]" class="hotel-commission-amount-field" value="0">
                </div>
                <div class="col-md-3 mb-2"><span class="text-muted">Commission Amount:</span> <span class="fw-bold hotel-commission-amount-display">0.00</span></div>
                <div class="col-md-4 mb-2 text-end">
                    <span class="text-muted">PSF (Receivable − Payable, local):</span> <span class="line-income fw-bold">0.00</span>
                </div>
                <div class="col-md-2 mb-2 text-end">
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.line-card').remove(); recalcTotals();"><i class="fas fa-times"></i></button>
                </div>
            </div>

            <div class="mb-1">
                <label class="form-label d-block">Other Charges / Discount (SPO / WHT / COM / PSF / Tax ...) <small class="field-note">(informational — not netted into PSF above)</small></label>
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
    @endif

    {{-- ============ Transport: dedicated block (same 3-part treatment as Hotel) ============
         1. Transport Details — Supplier / Booking Name / Currency / Reference No / Category
         2. Vehicle Details — Vehicle + Sector as a repeatable grid (more than
            one vehicle per line) / Charges (Other Charges / Discount mini-table)
         3. Charges Details — same dual receivable/payable currency+rate, Agent
            Commission %, and PSF formula as Hotel's Receivables/Payables/PSF.

         Judgment calls made here (flagged — easy to change if not wanted):
           - Reference No and Category are real, saved fields this round
             (unlike Hotel's Category, which stayed an informational,
             not-saved dropdown) — the client's field list named them
             alongside Supplier/Booking Name/Currency as core Transport
             Details, not as "not saved yet" placeholders like the old
             Transporter/Package/Inventory fields that were removed.
             Category is now a dropdown with the same umrah/hajj/holiday/
             tour/visitor options as Hotel's, plus a "— None —" option
             since this one is actually saved (Hotel's isn't).
           - "Vehicle details (vehicle, section, charges)" — "charges" here
             is read as the existing Other Charges/Discount mini-table
             (SPO/WHT/COM/PSF/Tax...), placed in this section; the full
             dual-currency/commission/PSF math lives in its own "Charges
             Details" section per point 6, matching Hotel's Receivables/
             Payables/Commission/PSF block exactly.
           - Vehicle + Sector is now a repeatable grid (one row per
             vehicle), same nested-<template> pattern as Hotel's Room
             Details grid — only the two existing fields, no extra
             qty/rate/total columns (those are Room Details-specific,
             not asked for here).
           - Template dropdown, Transporter, Package, and Inventory fields
             are removed per point 5 (Template's auto-fill-currency/charges
             role is no longer available on this tab — Currency and Charges
             are now set directly in Transport/Charges Details).
    --}}
    @if(isset($activeTabs['transport']))
    <div class="tab-pane fade" id="tab-transport">
        <div id="cards-transport"></div>
        <button type="button" class="btn btn-success btn-sm" onclick="addLineCard('transport')">+ Add Transport Line</button>
        <div class="card mt-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <span class="text-muted">Transport lines total receivable (local currency)</span>
                <h5 class="mb-0" id="transport-tab-total-receivable">0.00</h5>
            </div>
        </div>
    </div>

    <template id="tpl-transport">
        <div class="line-card" data-type="transport" data-charge-idx="0" data-vehicle-idx="0">
            <input type="hidden" name="lines[__IDX__][service_type]" value="transport">

            <h4 class="fw-bold text-uppercase text-dark mb-2">Transport Details</h4>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="form-label">Supplier</label>
                    <select name="lines[__IDX__][supplier_id]" class="form-control select2-js line-field">
                        <option value="">— None —</option>
                        @foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->is_flagged ? '⚠ ' : '' }}{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Booking Name</label>
                    <input type="text" name="lines[__IDX__][detail][booking_name]" class="form-control">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Currency <small class="text-muted">(invoice)</small></label>
                    <select name="lines[__IDX__][currency]" class="form-control line-currency line-field">
                        @foreach($currencies as $cur)<option value="{{ $cur->code }}" @selected($cur->code === 'PKR')>{{ $cur->code }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Reference No</label>
                    <input type="text" name="lines[__IDX__][detail][reference_no]" class="form-control">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Category</label>
                    <select name="lines[__IDX__][detail][category]" class="form-control">
                        <option value="">— None —</option>
                        @foreach(['umrah' => 'Umrah', 'hajj' => 'Hajj', 'holiday' => 'Holiday', 'tour' => 'Tour', 'visitor' => 'Visitor'] as $val => $lbl)
                        <option value="{{ $val }}">{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <hr class="my-2">
            <h4 class="fw-bold text-uppercase text-dark mb-2">Vehicle Details</h4>
            <table class="table table-bordered table-sm mini-table transport-vehicles-table">
                <thead><tr><th>Vehicle</th><th>Sector</th><th width="36"></th></tr></thead>
                <tbody></tbody>
            </table>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addTransportVehicleRow(this.closest('.line-card'))">+ Add Vehicle</button>
            <template class="transport-vehicle-tpl">
                <tr>
                    <td>
                        <select name="lines[__IDX__][detail][vehicles][__VIDX__][vehicle_id]" class="form-control form-control-sm select2-js">
                            <option value="">— None —</option>
                            @foreach($vehicles as $v)<option value="{{ $v->id }}">{{ $v->name }} ({{ $v->type }})</option>@endforeach
                        </select>
                    </td>
                    <td><input type="text" name="lines[__IDX__][detail][vehicles][__VIDX__][sector]" class="form-control form-control-sm"></td>
                    <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();"><i class="fas fa-times"></i></button></td>
                </tr>
            </template>

            <div class="mb-1">
                <label class="form-label d-block">Charges <small class="field-note">(informational — not netted into PSF below)</small></label>
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

            <hr class="my-2">
            <h4 class="fw-bold text-uppercase text-dark mb-2">Charges Details</h4>
            <div class="row">
                <div class="col-md-6">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Receivable (F)</label>
                            <input type="number" step="any" name="lines[__IDX__][receivable_f_amount]" class="form-control line-recv line-field" value="0" oninput="recalcTransportFinance(this.closest('.line-card'))">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Exch. Rate</label>
                            <input type="number" step="any" name="lines[__IDX__][detail][receivable_exchange_rate]" class="form-control transport-recv-rate" value="1" oninput="recalcTransportFinance(this.closest('.line-card'))">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Currency</label>
                            <select name="lines[__IDX__][detail][receivable_currency]" class="form-control transport-recv-currency" onchange="recalcTransportFinance(this.closest('.line-card'))">
                                @foreach($currencies as $cur)<option value="{{ $cur->code }}" @selected($cur->code === 'PKR')>{{ $cur->code }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="text-end"><span class="text-muted">Receivable Amount (Converted):</span> <span class="fw-bold transport-recv-converted">0.00</span></div>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Payable (F)</label>
                            <input type="number" step="any" name="lines[__IDX__][payable_f_amount]" class="form-control line-pay line-field" value="0" oninput="recalcTransportFinance(this.closest('.line-card'))">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Exch. Rate</label>
                            <input type="number" step="any" name="lines[__IDX__][detail][payable_exchange_rate]" class="form-control transport-pay-rate" value="1" oninput="recalcTransportFinance(this.closest('.line-card'))">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Currency</label>
                            <select name="lines[__IDX__][detail][payable_currency]" class="form-control transport-pay-currency" onchange="recalcTransportFinance(this.closest('.line-card'))">
                                @foreach($currencies as $cur)<option value="{{ $cur->code }}" @selected($cur->code === 'PKR')>{{ $cur->code }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="text-end"><span class="text-muted">Payable Amount (Converted):</span> <span class="fw-bold transport-pay-converted">0.00</span></div>
                </div>
            </div>

            <hr class="my-2">
            <div class="row align-items-end">
                <div class="col-md-3 mb-2">
                    <label class="form-label">Agent Commission %</label>
                    <input type="number" step="any" min="0" max="100" name="lines[__IDX__][detail][agent_commission_percent]" class="form-control transport-commission-pct" value="0" oninput="recalcTransportFinance(this.closest('.line-card'))">
                    <input type="hidden" name="lines[__IDX__][detail][agent_commission_amount]" class="transport-commission-amount-field" value="0">
                </div>
                <div class="col-md-3 mb-2"><span class="text-muted">Commission Amount:</span> <span class="fw-bold transport-commission-amount-display">0.00</span></div>
                <div class="col-md-4 mb-2 text-end">
                    <span class="text-muted">PSF (Receivable − Payable, local):</span> <span class="line-income fw-bold">0.00</span>
                </div>
                <div class="col-md-2 mb-2 text-end">
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.line-card').remove(); recalcTotals();"><i class="fas fa-times"></i></button>
                </div>
            </div>
        </div>
    </template>
    @endif

    {{-- ============ Visa: dedicated block (same pattern as Hotel / Transport) ============
         FRONTEND STRUCTURE ONLY (per client: backend is built once every tab
         is approved). Sections:
           1. Visa Details (common/master) — Supplier / Currency / Visa Agency /
              Category (Visa Type) / Reference No / Entry Date / Exit Date /
              Visa Rate / Remarks
           2. Passenger Visa Details — grid, one row per passenger picked from
              the Passengers table in General Information, with Type
           3. Charges Details — dual receivable/payable currency+rate, Agent
              Commission %, PSF (identical to Hotel / Transport)
           4. Other Charges — the same charges mini-table as Hotel / Transport

         Still saved by the existing backend: Supplier, Currency, Visa Type,
         Reference No, Remarks, Receivable (F), Payable (F), Other Charges.
         Everything else here is marked "(not saved yet)" and has no `name`
         attribute, so it neither posts nor fails validation.

         Template dropdown, Apply Date and Expiry Date are removed from the UI.
         Apply/Expiry stay as HIDDEN inputs so re-saving an older visa line
         doesn't wipe the dates it already has.
    --}}
    @if(isset($activeTabs['visa']))
    <div class="tab-pane fade" id="tab-visa">
        <div id="cards-visa"></div>
        <button type="button" class="btn btn-success btn-sm" onclick="addLineCard('visa')">+ Add Visa Line</button>
        <div class="card mt-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <span class="text-muted">Visa lines total receivable (local currency)</span>
                <h5 class="mb-0" id="visa-tab-total-receivable">0.00</h5>
            </div>
        </div>
    </div>

    <template id="tpl-visa">
        <div class="line-card" data-type="visa" data-charge-idx="0">
            <input type="hidden" name="lines[__IDX__][service_type]" value="visa">
            <input type="hidden" name="lines[__IDX__][detail][apply_date]">
            <input type="hidden" name="lines[__IDX__][detail][expiry_date]">

            <h6 class="text-uppercase text-muted small fw-bold mb-2">1. Visa Details</h6>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="form-label">Supplier</label>
                    <select name="lines[__IDX__][supplier_id]" class="form-control select2-js line-field">
                        <option value="">— None —</option>
                        @foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->is_flagged ? '⚠ ' : '' }}{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Currency <small class="text-muted">(invoice)</small></label>
                    <select name="lines[__IDX__][currency]" class="form-control line-currency line-field">
                        @foreach($currencies as $cur)<option value="{{ $cur->code }}" @selected($cur->code === 'PKR')>{{ $cur->code }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Visa Agency <small class="field-note">(not saved yet)</small></label>
                    <select class="form-control not-persisted-field">
                        <option value="">— None —</option>
                        @foreach($suppliers->where('type', 'visa_agency') as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Category / Visa Type</label>
                    <select name="lines[__IDX__][detail][visa_type_id]" class="form-control select2-js">
                        <option value="">— None —</option>
                        @foreach($visaTypes as $vt)<option value="{{ $vt->id }}">{{ $vt->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Reference No</label>
                    <input type="text" name="lines[__IDX__][detail][reference_no]" class="form-control">
                </div>
            </div>
            <div class="row">
                <div class="col-md-2 mb-2"><label class="form-label">Entry Date <small class="field-note">(not saved yet)</small></label><input type="date" class="form-control not-persisted-field"></div>
                <div class="col-md-2 mb-2"><label class="form-label">Exit Date <small class="field-note">(not saved yet)</small></label><input type="date" class="form-control not-persisted-field"></div>
                <div class="col-md-2 mb-2"><label class="form-label">Visa Rate <small class="field-note">(not saved yet)</small></label><input type="number" step="any" min="0" value="0" class="form-control not-persisted-field"></div>
                <div class="col-md-6 mb-2"><label class="form-label">Remarks</label><input type="text" name="lines[__IDX__][description]" class="form-control"></div>
            </div>

            <hr class="my-2">
            <h6 class="text-uppercase text-muted small fw-bold mb-2">2. Passenger Visa Details <small class="field-note">(not saved yet)</small></h6>
            <div class="small text-muted mb-1">Passengers are picked from the Passengers list in General Information.</div>
            <table class="table table-bordered table-sm mini-table visa-pax-table">
                <thead><tr><th>Passenger</th><th width="20%">Type</th><th width="36"></th></tr></thead>
                <tbody></tbody>
            </table>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addVisaPaxRow(this.closest('.line-card'))">+ Add Passenger</button>
            <template class="visa-pax-tpl">
                <tr>
                    <td>
                        <select class="form-control form-control-sm visa-pax-select not-persisted-field" onchange="onVisaPaxChange(this)">
                            <option value="">— Select Passenger —</option>
                        </select>
                    </td>
                    <td>
                        <select class="form-control form-control-sm visa-pax-type not-persisted-field">
                            <option value="adult">Adult</option><option value="child">Child</option><option value="infant">Infant</option>
                        </select>
                    </td>
                    <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();"><i class="fas fa-times"></i></button></td>
                </tr>
            </template>

            <hr class="my-2">
            <h4 class="fw-bold text-uppercase text-dark mb-2">Charges Details</h4>
            <div class="row">
                <div class="col-md-6">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Receivable (F)</label>
                            <input type="number" step="any" name="lines[__IDX__][receivable_f_amount]" class="form-control line-recv line-field" value="0" oninput="recalcVisaFinance(this.closest('.line-card'))">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Exch. Rate</label>
                            <input type="number" step="any" class="form-control visa-recv-rate not-persisted-field" value="1" oninput="recalcVisaFinance(this.closest('.line-card'))">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Currency</label>
                            <select class="form-control visa-recv-currency not-persisted-field">
                                @foreach($currencies as $cur)<option value="{{ $cur->code }}" @selected($cur->code === 'PKR')>{{ $cur->code }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="text-end"><span class="text-muted">Receivable Amount (Converted):</span> <span class="fw-bold visa-recv-converted">0.00</span></div>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Payable (F)</label>
                            <input type="number" step="any" name="lines[__IDX__][payable_f_amount]" class="form-control line-pay line-field" value="0" oninput="recalcVisaFinance(this.closest('.line-card'))">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Exch. Rate</label>
                            <input type="number" step="any" class="form-control visa-pay-rate not-persisted-field" value="1" oninput="recalcVisaFinance(this.closest('.line-card'))">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Currency</label>
                            <select class="form-control visa-pay-currency not-persisted-field">
                                @foreach($currencies as $cur)<option value="{{ $cur->code }}" @selected($cur->code === 'PKR')>{{ $cur->code }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="text-end"><span class="text-muted">Payable Amount (Converted):</span> <span class="fw-bold visa-pay-converted">0.00</span></div>
                </div>
            </div>

            <hr class="my-2">
            <div class="row align-items-end">
                <div class="col-md-3 mb-2">
                    <label class="form-label">Agent Commission %</label>
                    <input type="number" step="any" min="0" max="100" class="form-control visa-commission-pct not-persisted-field" value="0" oninput="recalcVisaFinance(this.closest('.line-card'))">
                </div>
                <div class="col-md-3 mb-2"><span class="text-muted">Commission Amount:</span> <span class="fw-bold visa-commission-amount-display">0.00</span></div>
                <div class="col-md-4 mb-2 text-end">
                    <span class="text-muted">PSF (Receivable − Payable, local):</span> <span class="line-income fw-bold">0.00</span>
                </div>
                <div class="col-md-2 mb-2 text-end">
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.line-card').remove(); recalcTotals();"><i class="fas fa-times"></i></button>
                </div>
            </div>

            <hr class="my-2">
            <h6 class="text-uppercase text-muted small fw-bold mb-2">4. Other Charges</h6>
            <div class="mb-1">
                <label class="form-label d-block">Other Charges / Discount (SPO / WHT / COM / PSF / Tax ...) <small class="field-note">(informational — not netted into PSF above)</small></label>
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
    @endif

    {{-- ============ One tab + one <template> per remaining service type ============ --}}
    @foreach($activeTabs as $type => $label)
    @continue($type === 'ticket' || $type === 'hotel' || $type === 'transport' || $type === 'visa')
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
                <div class="col-md-3 mb-2">
                    <label class="form-label">Template</label>
                    <select class="form-control select2-js template-select" onchange="onTemplateChange(this)">
                        <option value="">— None —</option>
                        @foreach($chargeTemplatesByType->get($type, collect()) as $ct)
                        <option value="{{ $ct->id }}"
                            data-currency="{{ $ct->default_currency }}"
                            data-rate="{{ $ct->default_exchange_rate }}"
                            data-items='@json($ct->items->map(fn ($it) => ["charge_type_id" => $it->charge_type_id, "value" => (float) $it->value])->values())'>
                            {{ $ct->name }} ({{ optional($ct->effective_date)->format('d/m/Y') }})
                        </option>
                        @endforeach
                    </select>
                    <input type="hidden" name="lines[__IDX__][charge_template_id]" class="template-id-field">
                </div>
                <div class="col-md-1 mb-2">
                    <label class="form-label">Currency</label>
                    <select name="lines[__IDX__][currency]" class="form-control line-currency line-field">
                        @foreach($currencies as $cur)<option value="{{ $cur->code }}" @selected($cur->code === 'PKR')>{{ $cur->code }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-1 mb-2">
                    <label class="form-label">Exch. Rate</label>
                    <input type="number" step="any" name="lines[__IDX__][exchange_rate]" class="form-control line-rate line-field" value="1">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Receivable (F)</label>
                    <input type="number" step="any" name="lines[__IDX__][receivable_f_amount]" class="form-control line-recv line-field" value="0">
                </div>
                <div class="col-md-1 mb-2">
                    <label class="form-label">Payable (F)</label>
                    <input type="number" step="any" name="lines[__IDX__][payable_f_amount]" class="form-control line-pay line-field" value="0">
                </div>
                <div class="col-md-1 mb-2 text-end">
                    <label class="form-label d-block">&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.line-card').remove(); recalcTotals();"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 mb-2 text-end">
                    <span class="text-muted">Income (Local):</span> <span class="line-income fw-bold">0.00</span>
                </div>
            </div>

            @if($type === 'ticket')
            {{-- Dead branch: the Ticket service type is now rendered by its
                 own Master+Grid block above, never through this generic
                 per-line template. Left here only so a stray existing
                 ticket-type ServiceLine (none expected) wouldn't silently
                 vanish if ever routed through addLineCard() by mistake. --}}
            @endif

            {{-- Dead branch: Transport is now rendered by its own dedicated
                 3-section block above (tpl-transport), never through this
                 generic per-line template — left here only so a stray
                 existing transport-type ServiceLine wouldn't silently
                 vanish if ever routed through addLineCard() by mistake. --}}
            @if($type === 'transport')
            @endif

            {{-- Dead branch: Visa is now rendered by its own dedicated block
                 above (tpl-visa), never through this generic template. --}}
            @if($type === 'visa')
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
                <label class="form-label d-block">{{ $type === 'other' ? 'Charges (SPO / WHT / COM / PSF / Tax ...)' : 'Other Charges / Discount (SPO / WHT / COM / PSF / Tax ...)' }}</label>
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
        delete clone.dataset.paxUid; // cloneNode copies data-* — the Visa grid keys passengers by this id
        clone.querySelectorAll('input, select').forEach(el => {
            el.name = el.name.replace(/passengers\[\d+\]/, `passengers[${paxIndex}]`);
            if (el.tagName === 'SELECT') el.selectedIndex = 0; else el.value = '';
        });
        tbody.appendChild(clone);
        paxIndex++;
    }

    // Room Type options on each Room Details grid row depend on which
    // Hotel is picked up in Booking Details — re-populated whenever the
    // Hotel changes, and whenever a new room row is added.
    function populateHotelRoomTypeOptions(select, hotelId) {
        const hotel = (window.hotelsData || []).find(h => String(h.id) === String(hotelId));
        const current = select.value;
        select.innerHTML = '<option value="">— None —</option>';
        (hotel ? hotel.rooms : []).forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.id;
            opt.textContent = r.room_type;
            select.appendChild(opt);
        });
        if (current) select.value = current;
    }

    function onHotelChange(select) {
        const card = select.closest('.line-card');
        const hotelId = select.value;
        card.querySelectorAll('.hotel-room-type-select').forEach(roomSelect => {
            populateHotelRoomTypeOptions(roomSelect, hotelId);
            if (window.jQuery) $(roomSelect).trigger('change.select2') || $(roomSelect).select2({dropdownParent: card});
        });
    }

    // Client fix: "no of night should be auto calculated using check in
    // and check out date" — Nights is readonly, this is the only thing
    // that writes to it.
    function syncHotelNights(card) {
        if (!card) return;
        const inEl = card.querySelector('.hotel-checkin');
        const outEl = card.querySelector('.hotel-checkout');
        const nightsEl = card.querySelector('.hotel-nights');
        if (!inEl || !outEl || !nightsEl) return;
        const inDate = inEl.value ? new Date(inEl.value + 'T00:00:00') : null;
        const outDate = outEl.value ? new Date(outEl.value + 'T00:00:00') : null;
        let nights = 0;
        if (inDate && outDate && outDate > inDate) {
            nights = Math.round((outDate - inDate) / 86400000);
        }
        nightsEl.value = nights;
        recalcHotelRooms(card, true);
    }

    // Client fix — Room Details, per row (Rate/Cost are per room per night):
    //   Single Room Total = Rate × Nights,  Rooms Total = Single Room Total × No. of Rooms
    //   Single Room Cost  = Cost × Nights,  Rooms Cost  = Single Room Cost × No. of Rooms
    // Σ Rooms Total → Receivable (F), Σ Rooms Cost → Payable (F). Same math
    // as InvoiceHotelRoom::totalsFor() on the server.
    //
    // While any room has a Rate (or Cost) the Receivable (or Payable) is
    // calculated and locked. An older line whose rooms carry no rate/cost
    // (priced by hand before this fix) keeps its typed amount, editable.
    // `userEdited`: if the user removes the last rate/cost, the auto amount
    // drops back to 0 rather than leaving a stale total behind.
    function recalcHotelRooms(card, userEdited) {
        if (!card) return;
        const r2 = n => Math.round((n + Number.EPSILON) * 100) / 100;
        const nights = parseFloat(card.querySelector('.hotel-nights')?.value) || 0;
        let sumTotal = 0, sumCost = 0, hasRate = false, hasCost = false;

        card.querySelectorAll('.hotel-rooms-table tbody tr').forEach(row => {
            const qty = parseFloat(row.querySelector('.hotel-room-row-qty')?.value) || 0;
            const rate = parseFloat(row.querySelector('.hotel-room-row-rate')?.value) || 0;
            const cost = parseFloat(row.querySelector('.hotel-room-row-cost')?.value) || 0;
            const single = r2(rate * nights), singleCost = r2(cost * nights);
            const total = r2(single * qty), totalCost = r2(singleCost * qty);
            row.querySelector('.hotel-room-row-single').value = single.toFixed(2);
            row.querySelector('.hotel-room-row-total').value = total.toFixed(2);
            row.querySelector('.hotel-room-row-single-cost').value = singleCost.toFixed(2);
            row.querySelector('.hotel-room-row-total-cost').value = totalCost.toFixed(2);
            sumTotal += total; sumCost += totalCost;
            hasRate = hasRate || rate > 0; hasCost = hasCost || cost > 0;
        });

        card.querySelector('.hotel-rooms-sum-total').textContent = r2(sumTotal).toFixed(2);
        card.querySelector('.hotel-rooms-sum-cost').textContent = r2(sumCost).toFixed(2);
        card.querySelector('.hotel-rooms-nights-label').textContent = nights;

        const recvEl = card.querySelector('.line-recv');
        const payEl = card.querySelector('.line-pay');
        if (hasRate || (userEdited && card.dataset.recvAuto === '1')) recvEl.value = r2(sumTotal).toFixed(2);
        if (hasCost || (userEdited && card.dataset.payAuto === '1')) payEl.value = r2(sumCost).toFixed(2);
        card.dataset.recvAuto = hasRate ? '1' : '0';
        card.dataset.payAuto = hasCost ? '1' : '0';
        recvEl.readOnly = hasRate;
        payEl.readOnly = hasCost;
        recvEl.style.backgroundColor = hasRate ? '#eef1f4' : '';
        payEl.style.backgroundColor = hasCost ? '#eef1f4' : '';
        recvEl.title = hasRate ? 'Calculated from Room Details (Σ Rooms Total)' : '';
        payEl.title = hasCost ? 'Calculated from Room Details (Σ Rooms Cost)' : '';

        recalcHotelFinance(card);
    }

    // Client fix: "break Room Details into a grid... same as charges
    // grid" — same nested-<template> pattern as addChargeRow/addFlightRow.
    function addHotelRoomRow(card, data) {
        data = data || {};
        const tpl = card.querySelector('.hotel-room-tpl');
        const tbody = card.querySelector('.hotel-rooms-table tbody');
        const rIdx = parseInt(card.dataset.roomIdx || '0', 10);
        const lineIdx = card.querySelector('input[name$="[service_type]"]').name.match(/lines\[(\d+)\]/)[1];
        const row = tpl.content.firstElementChild.cloneNode(true);
        row.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace('__IDX__', lineIdx).replace('__RIDX__', rIdx);
        });
        tbody.appendChild(row);
        card.dataset.roomIdx = rIdx + 1;

        const hotelSelect = card.querySelector('.hotel-select');
        populateHotelRoomTypeOptions(row.querySelector('.hotel-room-type-select'), hotelSelect ? hotelSelect.value : '');
        if (data.hotel_room_id) row.querySelector('.hotel-room-type-select').value = data.hotel_room_id;
        if (data.room_view_id) row.querySelector('.hotel-room-view-select').value = data.room_view_id;
        row.querySelector('.hotel-room-row-qty').value = data.qty ?? 1;
        row.querySelector('.hotel-room-row-rate').value = data.rate ?? 0;
        row.querySelector('.hotel-room-row-cost').value = data.cost ?? 0;
        recalcHotelRooms(card, false);

        if (window.jQuery) $(row).find('select').select2({dropdownParent: card});
        return row;
    }

    // Client fix: "same as room details grid in hotel tab ... make vehicle
    // grid, with only existing fields, i.e vehicle and sector" — same
    // nested-<template> pattern as addHotelRoomRow, just the two fields
    // (no qty/rate/total — those are Room Details-specific).
    function addTransportVehicleRow(card, data) {
        data = data || {};
        const tpl = card.querySelector('.transport-vehicle-tpl');
        const tbody = card.querySelector('.transport-vehicles-table tbody');
        const vIdx = parseInt(card.dataset.vehicleIdx || '0', 10);
        const lineIdx = card.querySelector('input[name$="[service_type]"]').name.match(/lines\[(\d+)\]/)[1];
        const row = tpl.content.firstElementChild.cloneNode(true);
        row.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace('__IDX__', lineIdx).replace('__VIDX__', vIdx);
        });
        tbody.appendChild(row);
        card.dataset.vehicleIdx = vIdx + 1;

        if (data.vehicle_id) row.querySelector('[name*="[vehicle_id]"]').value = data.vehicle_id;
        if (data.sector) row.querySelector('[name*="[sector]"]').value = data.sector;

        if (window.jQuery) $(row).find('select').select2({dropdownParent: card});
        return row;
    }

    // Client fix: Receivable/Payable each get their own currency+exchange
    // rate (what the customer/vendor is actually billed in), converted to
    // local via "Amount (Converted) = amount × that side's exchange rate".
    // PSF = Receivable(converted) − Payable(converted); Agent Commission
    // is computed from PSF but intentionally NOT subtracted from it.
    function recalcHotelFinance(card) {
        if (!card) return;
        const recv = parseFloat(card.querySelector('.line-recv')?.value) || 0;
        const recvRate = parseFloat(card.querySelector('.hotel-recv-rate')?.value) || 0;
        const pay = parseFloat(card.querySelector('.line-pay')?.value) || 0;
        const payRate = parseFloat(card.querySelector('.hotel-pay-rate')?.value) || 0;
        const commissionPct = parseFloat(card.querySelector('.hotel-commission-pct')?.value) || 0;

        const recvConverted = recv * recvRate;
        const payConverted = pay * payRate;
        const psf = recvConverted - payConverted;
        const commissionAmount = psf * commissionPct / 100;

        const recvConvertedEl = card.querySelector('.hotel-recv-converted');
        if (recvConvertedEl) recvConvertedEl.textContent = recvConverted.toFixed(2);
        const payConvertedEl = card.querySelector('.hotel-pay-converted');
        if (payConvertedEl) payConvertedEl.textContent = payConverted.toFixed(2);

        const psfEl = card.querySelector('.line-income');
        if (psfEl) {
            psfEl.textContent = psf.toFixed(2);
            psfEl.className = 'line-income fw-bold ' + (psf < 0 ? 'negative' : 'positive');
        }

        const commissionDisplayEl = card.querySelector('.hotel-commission-amount-display');
        if (commissionDisplayEl) commissionDisplayEl.textContent = commissionAmount.toFixed(2);
        const commissionFieldEl = card.querySelector('.hotel-commission-amount-field');
        if (commissionFieldEl) commissionFieldEl.value = commissionAmount.toFixed(2);

        recalcTotals();
    }

    // Transport tab rework: "charges details ... follow same as hotel tab
    // charges section" — identical dual-currency/PSF/commission math as
    // recalcHotelFinance above, duplicated (not shared) so Hotel's own
    // markup/classes/behavior stay untouched.
    function recalcTransportFinance(card) {
        if (!card) return;
        const recv = parseFloat(card.querySelector('.line-recv')?.value) || 0;
        const recvRate = parseFloat(card.querySelector('.transport-recv-rate')?.value) || 0;
        const pay = parseFloat(card.querySelector('.line-pay')?.value) || 0;
        const payRate = parseFloat(card.querySelector('.transport-pay-rate')?.value) || 0;
        const commissionPct = parseFloat(card.querySelector('.transport-commission-pct')?.value) || 0;

        const recvConverted = recv * recvRate;
        const payConverted = pay * payRate;
        const psf = recvConverted - payConverted;
        const commissionAmount = psf * commissionPct / 100;

        const recvConvertedEl = card.querySelector('.transport-recv-converted');
        if (recvConvertedEl) recvConvertedEl.textContent = recvConverted.toFixed(2);
        const payConvertedEl = card.querySelector('.transport-pay-converted');
        if (payConvertedEl) payConvertedEl.textContent = payConverted.toFixed(2);

        const psfEl = card.querySelector('.line-income');
        if (psfEl) {
            psfEl.textContent = psf.toFixed(2);
            psfEl.className = 'line-income fw-bold ' + (psf < 0 ? 'negative' : 'positive');
        }

        const commissionDisplayEl = card.querySelector('.transport-commission-amount-display');
        if (commissionDisplayEl) commissionDisplayEl.textContent = commissionAmount.toFixed(2);
        const commissionFieldEl = card.querySelector('.transport-commission-amount-field');
        if (commissionFieldEl) commissionFieldEl.value = commissionAmount.toFixed(2);

        recalcTotals();
    }

    // Visa tab: same dual-currency / PSF / commission math as Hotel and
    // Transport, under `.visa-*` classes. Written once, parameterised by
    // class prefix, so Hotel's and Transport's approved functions above stay
    // exactly as they are.
    function recalcDualFinance(card, prefix) {
        if (!card) return;
        const q = sel => card.querySelector(sel);
        const recv = parseFloat(q('.line-recv')?.value) || 0;
        const recvRate = parseFloat(q(`.${prefix}-recv-rate`)?.value) || 0;
        const pay = parseFloat(q('.line-pay')?.value) || 0;
        const payRate = parseFloat(q(`.${prefix}-pay-rate`)?.value) || 0;
        const commissionPct = parseFloat(q(`.${prefix}-commission-pct`)?.value) || 0;

        const recvConverted = recv * recvRate;
        const payConverted = pay * payRate;
        const psf = recvConverted - payConverted;

        const set = (sel, v) => { const el = q(sel); if (el) el.textContent = v.toFixed(2); };
        set(`.${prefix}-recv-converted`, recvConverted);
        set(`.${prefix}-pay-converted`, payConverted);
        set(`.${prefix}-commission-amount-display`, psf * commissionPct / 100);

        const psfEl = q('.line-income');
        if (psfEl) {
            psfEl.textContent = psf.toFixed(2);
            psfEl.className = 'line-income fw-bold ' + (psf < 0 ? 'negative' : 'positive');
        }
        recalcTotals();
    }

    function recalcVisaFinance(card) { recalcDualFinance(card, 'visa'); }

    function recalcVisaTabTotal() {
        const totalEl = document.getElementById('visa-tab-total-receivable');
        if (!totalEl) return;
        let total = 0;
        document.querySelectorAll('#cards-visa .line-card').forEach(card => {
            total += (parseFloat(card.querySelector('.line-recv')?.value) || 0) * (parseFloat(card.querySelector('.visa-recv-rate')?.value) || 0);
        });
        totalEl.textContent = total.toFixed(2);
    }

    // ---- Visa: Passenger grid, fed from the General Information passengers table ----
    let paxUidSeq = 0;
    function visaPaxList() {
        return [...document.querySelectorAll('#paxTable tbody tr')].map((tr, i) => {
            if (!tr.dataset.paxUid) tr.dataset.paxUid = ++paxUidSeq;
            const name = tr.querySelector('input[name$="[name]"]')?.value.trim();
            return {
                uid: tr.dataset.paxUid,
                name: name || '',
                label: name || `Passenger ${i + 1}`,
                type: tr.querySelector('select[name$="[pax_type]"]')?.value || 'adult',
            };
        });
    }

    function fillVisaPaxSelect(select, list) {
        const current = select.value;
        select.innerHTML = '<option value="">— Select Passenger —</option>';
        list.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.uid;
            opt.textContent = p.label;
            select.appendChild(opt);
        });
        if (list.some(p => p.uid === current)) select.value = current;
    }

    // Re-sync every Visa grid row with the passengers table: refresh the
    // name options (keeping each row's pick) and the Type of the picked pax.
    function refreshVisaPaxRows() {
        const list = visaPaxList();
        document.querySelectorAll('.visa-pax-table tbody tr').forEach(row => {
            const select = row.querySelector('.visa-pax-select');
            fillVisaPaxSelect(select, list);
            const pax = list.find(p => p.uid === select.value);
            if (pax) row.querySelector('.visa-pax-type').value = pax.type;
        });
    }

    function onVisaPaxChange(select) {
        const pax = visaPaxList().find(p => p.uid === select.value);
        if (pax) select.closest('tr').querySelector('.visa-pax-type').value = pax.type;
    }

    function addVisaPaxRow(card) {
        const tpl = card.querySelector('.visa-pax-tpl');
        const row = tpl.content.firstElementChild.cloneNode(true);
        card.querySelector('.visa-pax-table tbody').appendChild(row);
        fillVisaPaxSelect(row.querySelector('.visa-pax-select'), visaPaxList());
        return row;
    }

    // ---- Hotel: Booking Name = one passenger from General Information ----
    // Options are keyed by the passenger row (so renaming a passenger keeps
    // the pick); the hidden .hotel-booking-name holds the name that's saved.
    // A new line pre-selects the first named passenger until the user picks.
    // A saved name that's no longer in the list (e.g. older lines that used
    // the Customer's name) is kept as a "(saved)" option.
    function fillHotelBookingPax(card) {
        const select = card.querySelector('.hotel-booking-pax');
        const hidden = card.querySelector('.hotel-booking-name');
        if (!select || !hidden) return;

        const list = visaPaxList().filter(p => p.name !== '');
        const currentUid = select.value;
        let savedName = hidden.value.trim();

        // The passenger that was picked has been removed (or its name cleared) → drop the pick.
        if (currentUid && currentUid !== '__saved' && !list.some(p => p.uid === currentUid)) {
            savedName = '';
            hidden.value = '';
        }

        select.innerHTML = '<option value="">— Select Passenger —</option>';
        list.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.uid;
            opt.textContent = p.label;
            select.appendChild(opt);
        });

        let pick = currentUid ? list.find(p => p.uid === currentUid) : null;
        if (!pick && savedName) pick = list.find(p => p.name === savedName);
        if (!pick && !savedName && card.dataset.bookingTouched !== '1') pick = list[0];

        if (pick) {
            select.value = pick.uid;
            hidden.value = pick.name;
        } else if (savedName) {
            const opt = document.createElement('option');
            opt.value = '__saved';
            opt.textContent = savedName + ' (saved)';
            select.appendChild(opt);
            select.value = '__saved';
        } else {
            select.value = '';
        }
    }

    function refreshHotelBookingPax() {
        document.querySelectorAll('.line-card[data-type="hotel"]').forEach(fillHotelBookingPax);
    }

    function onHotelBookingPaxChange(select) {
        const card = select.closest('.line-card');
        card.dataset.bookingTouched = '1';
        if (select.value === '__saved') return;
        const pax = visaPaxList().find(p => p.uid === select.value);
        card.querySelector('.hotel-booking-name').value = pax ? pax.name : '';
    }

    function onTemplateChange(select) {
        const card = select.closest('.line-card');
        const opt = select.options[select.selectedIndex];
        card.querySelector('.template-id-field').value = opt.value || '';
        if (!opt.value) return;
        if (opt.dataset.currency) card.querySelector('.line-currency').value = opt.dataset.currency;
        // Hotel cards have no single `.line-rate` (separate Receivable/
        // Payable exchange rates instead) — only set it where it exists.
        const rateEl = card.querySelector('.line-rate');
        if (opt.dataset.rate && rateEl) rateEl.value = opt.dataset.rate;
        const tbody = card.querySelector('.charges-table tbody');
        tbody.innerHTML = '';
        const items = JSON.parse(opt.dataset.items || '[]');
        items.forEach(it => addChargeRow(card, { charge_type_id: it.charge_type_id, value: it.value, computed_amount: 0 }));
        tbody.querySelectorAll('tr').forEach(row => recalcChargeRow(row));
        recalcCard(card);
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
            // Hotel/Transport cards have no single `.line-rate` — fall back
            // to that card's own Receivable exchange rate for the
            // percentage base instead.
            const rateEl = card.querySelector('.line-rate') || card.querySelector('.hotel-recv-rate') || card.querySelector('.transport-recv-rate') || card.querySelector('.visa-recv-rate');
            const rate = parseFloat(rateEl?.value) || 0;
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
        // Hotel cards have no single `.line-rate` (separate Receivable/
        // Payable exchange rates instead, set further down).
        const rateEl = card.querySelector('.line-rate');
        if (rateEl) rateEl.value = data.exchange_rate ?? 1;
        card.querySelector('.line-currency').value = data.currency ?? 'PKR';
        ['input', 'change'].forEach(evt => {
            card.querySelectorAll('.line-recv, .line-pay, .line-rate').forEach(el => el.addEventListener(evt, () => recalcCard(card)));
        });

        document.getElementById('cards-' + type).appendChild(card);

        if (data.supplier_id) card.querySelector('[name$="[supplier_id]"]').value = data.supplier_id;
        if (data.charge_template_id) {
            const templateSelect = card.querySelector('.template-select');
            if (templateSelect) templateSelect.value = data.charge_template_id;
            const hiddenField = card.querySelector('.template-id-field');
            if (hiddenField) hiddenField.value = data.charge_template_id;
        }
        const descriptionField = card.querySelector('[name$="[description]"]');
        if (descriptionField && data.description) descriptionField.value = data.description;

        const detail = data.detail || {};
        card.querySelectorAll('[name*="[detail]["]').forEach(el => {
            const m = el.name.match(/\[detail\]\[(\w+)\]$/);
            if (!m) return;
            const key = m[1];
            if (detail[key] !== undefined && detail[key] !== null) el.value = detail[key];
        });
        (detail.flights || []).forEach(f => addFlightRow(card, f));
        (data.charges || []).forEach(c => addChargeRow(card, c));

        if (type === 'hotel') {
            // Room Details is now a grid — prefer the saved `rooms` array,
            // but synthesize one row from the old single-room fields for
            // a hotel line saved before this fix, so that data isn't lost
            // from view.
            let rooms = detail.rooms || [];
            if (!rooms.length && (detail.hotel_room_id || detail.room_view_id || detail.room_qty)) {
                rooms = [{ hotel_room_id: detail.hotel_room_id, room_view_id: detail.room_view_id, qty: detail.room_qty || 1, rate: 0 }];
            }
            rooms.forEach(r => addHotelRoomRow(card, r));

            // Client fix: Booking Name = one passenger from General
            // Information (a saved line keeps the name it was booked under,
            // already put in the hidden field by the generic [detail] loop).
            fillHotelBookingPax(card);
        }

        if (type === 'transport') {
            // Vehicle Details is now a grid — prefer the saved `vehicles`
            // array, but synthesize one row from the old single Vehicle/
            // Sector fields for a transport line saved before this fix,
            // so that data isn't lost from view.
            let vehicles = detail.vehicles || [];
            if (!vehicles.length && (detail.vehicle_id || detail.sector)) {
                vehicles = [{ vehicle_id: detail.vehicle_id, sector: detail.sector }];
            }
            vehicles.forEach(v => addTransportVehicleRow(card, v));
        }

        if (window.jQuery) $(card).find('.select2-js').select2({dropdownParent: card});

        recalcCard(card);
        return card;
    }

    function recalcCard(card) {
        // Hotel/Transport cards use their own dual-currency PSF calculation
        // instead (no single `.line-rate`, and PSF excludes charges per the
        // client's exact "PSF = receivable − payable" formula).
        if (card.dataset.type === 'hotel') {
            recalcHotelFinance(card);
            return;
        }
        if (card.dataset.type === 'transport') {
            recalcTransportFinance(card);
            return;
        }
        if (card.dataset.type === 'visa') {
            recalcVisaFinance(card);
            return;
        }
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

    function recalcHotelTabTotal() {
        const totalEl = document.getElementById('hotel-tab-total-receivable');
        if (!totalEl) return;
        let total = 0;
        document.querySelectorAll('#cards-hotel .line-card').forEach(card => {
            const recv = parseFloat(card.querySelector('.line-recv')?.value) || 0;
            const rate = parseFloat(card.querySelector('.hotel-recv-rate')?.value) || 0;
            total += recv * rate;
        });
        totalEl.textContent = total.toFixed(2);
    }

    function recalcTransportTabTotal() {
        const totalEl = document.getElementById('transport-tab-total-receivable');
        if (!totalEl) return;
        let total = 0;
        document.querySelectorAll('#cards-transport .line-card').forEach(card => {
            const recv = parseFloat(card.querySelector('.line-recv')?.value) || 0;
            const rate = parseFloat(card.querySelector('.transport-recv-rate')?.value) || 0;
            total += recv * rate;
        });
        totalEl.textContent = total.toFixed(2);
    }

    // ============ Invoice Summary tab: grouped by service type ============
    function recalcTotals() {
        const groups = {};
        const pushRow = (type, label, currency, recv, pay, income) => {
            groups[type] = groups[type] || { rows: [], recv: 0, pay: 0, income: 0 };
            groups[type].rows.push({ label, currency, recv, pay, income });
            groups[type].recv += recv;
            groups[type].pay += pay;
            groups[type].income += income;
        };

        document.querySelectorAll('.line-card').forEach(card => {
            const type = card.dataset.type;
            const recvAmt = parseFloat(card.querySelector('.line-recv')?.value) || 0;
            const payAmt = parseFloat(card.querySelector('.line-pay')?.value) || 0;
            let recv, pay, income;
            if (type === 'hotel' || type === 'transport' || type === 'visa') {
                // Dual currency: Receivable and Payable each convert with
                // their own exchange rate, and PSF (shown here as the
                // row's "Income") excludes charges per the client's exact
                // "PSF = receivable − payable" formula. Same rule for
                // Transport as Hotel, just under its own `.transport-*` classes.
                const prefix = type; // hotel | transport | visa
                const recvRate = parseFloat(card.querySelector(`.${prefix}-recv-rate`)?.value) || 0;
                const payRate = parseFloat(card.querySelector(`.${prefix}-pay-rate`)?.value) || 0;
                recv = recvAmt * recvRate;
                pay = payAmt * payRate;
                income = recv - pay;
            } else {
                const rate = parseFloat(card.querySelector('.line-rate')?.value) || 0;
                let chargesTotal = 0;
                card.querySelectorAll('.charge-amount').forEach(el => chargesTotal += (parseFloat(el.value) || 0));
                recv = recvAmt * rate;
                pay = payAmt * rate;
                income = recv - pay + chargesTotal;
            }
            const supplierSelect = card.querySelector('[name$="[supplier_id]"]');
            const supplierLabel = supplierSelect && supplierSelect.selectedOptions[0] ? supplierSelect.selectedOptions[0].textContent : '—';
            pushRow(type, supplierLabel, card.querySelector('.line-currency')?.value || '', recv, pay, income);
        });

        // Ticket tab rows (Ticket Sale Invoice layout): Receivable = Ticket Total, Payable = Supplier Net.
        if (window.TourTicketTab) {
            window.TourTicketTab.summaryRows().forEach(r => pushRow('ticket', r.label, r.currency, r.recv, r.pay, r.income));
        }

        const typeLabels = { ticket: 'Ticket', hotel: 'Hotel', transport: 'Transport', visa: 'Visa', other: 'Other Services' };
        let html = '';
        let totalRecv = 0, totalPay = 0, totalIncome = 0;
        Object.keys(typeLabels).forEach(type => {
            const g = groups[type];
            if (!g) return;
            html += `<tr class="table-light"><td colspan="7"><strong>${typeLabels[type]} Booking</strong></td></tr>`;
            g.rows.forEach((r, i) => {
                html += `<tr><td>${i + 1}</td><td>${typeLabels[type]}</td><td>${r.label}</td><td>${r.currency}</td><td>${r.recv.toFixed(2)}</td><td>${r.pay.toFixed(2)}</td><td class="${r.income < 0 ? 'text-danger' : 'text-success'}">${r.income.toFixed(2)}</td></tr>`;
            });
            html += `<tr class="fw-bold"><td colspan="4" class="text-end">${typeLabels[type]} Subtotal</td><td>${g.recv.toFixed(2)}</td><td>${g.pay.toFixed(2)}</td><td>${g.income.toFixed(2)}</td></tr>`;
            totalRecv += g.recv; totalPay += g.pay; totalIncome += g.income;
        });

        document.getElementById('summaryBody').innerHTML = html || '<tr><td colspan="7" class="text-center text-muted">No lines added yet.</td></tr>';
        document.getElementById('totalReceivable').textContent = totalRecv.toFixed(2);
        document.getElementById('totalPayable').textContent = totalPay.toFixed(2);
        document.getElementById('totalIncome').textContent = totalIncome.toFixed(2);
        recalcHotelTabTotal();
        recalcTransportTabTotal();
        recalcVisaTabTotal();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const grouped = {};
        existingLines.forEach(line => {
            grouped[line.service_type] = grouped[line.service_type] || [];
            grouped[line.service_type].push(line);
        });
        Object.keys(grouped).forEach(type => {
            if (type !== 'ticket' && document.getElementById('tpl-' + type)) {
                grouped[type].forEach(line => addLineCard(type, line));
            }
        });

        // Visa tab Passenger grid and Hotel Booking Name both follow the
        // General Information passengers table (typing a name, changing a
        // type, adding or removing a passenger).
        const paxTbody = document.querySelector('#paxTable tbody');
        if (paxTbody) {
            const syncPax = () => { refreshVisaPaxRows(); refreshHotelBookingPax(); };
            ['input', 'change'].forEach(evt => paxTbody.addEventListener(evt, syncPax));
            new MutationObserver(syncPax).observe(paxTbody, { childList: true });
        }

        recalcTotals();
    });
</script>

@if(isset($activeTabs['ticket']))
@include('travel-invoices._ticket_tab_script')
@endif