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
                        ])->values();
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

    {{--
        ============ Ticket tab: Master Details + Grid ============
        Pulled out of the generic per-type loop below — it now has a
        fundamentally different shape (one shared Master Details block +
        one lean grid row per passenger) copied from the already
        client-approved Ticket Sale Invoice layout, instead of one
        line-card per ticket.
    --}}
    @if(isset($activeTabs['ticket']))
    <div class="tab-pane fade" id="tab-ticket">
        <div class="alert alert-info small mb-3">
            Master Details below are shared by every ticket in the grid — fill in the shared supplier / flight / charge information once, then add one grid row per passenger. Layout mirrors the Ticket Sale Invoice form already approved by the client.
        </div>

        <section class="card mb-3">
            <header class="card-header"><h3 class="card-title h6 mb-0">Master Details <small class="text-muted">— shared by every ticket below</small></h3></header>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Supplier</label>
                        <select class="form-control select2-js" id="tk-supplier">
                            <option value="">— None —</option>
                            @foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->is_flagged ? '⚠ ' : '' }}{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Template</label>
                        <select class="form-control select2-js" id="tk-template" onchange="onTicketTemplateChange(this)">
                            <option value="">— None —</option>
                            @foreach($chargeTemplatesByType->get('ticket', collect()) as $ct)
                            <option value="{{ $ct->id }}"
                                data-currency="{{ $ct->default_currency }}"
                                data-rate="{{ $ct->default_exchange_rate }}"
                                data-items='@json($ct->items->map(fn ($it) => ["charge_type_id" => $it->charge_type_id, "value" => (float) $it->value])->values())'>
                                {{ $ct->name }} ({{ optional($ct->effective_date)->format('d/m/Y') }})
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">Currency</label>
                        <select class="form-control" id="tk-currency">
                            @foreach($currencies as $cur)<option value="{{ $cur->code }}" @selected($cur->code === 'PKR')>{{ $cur->code }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">Exch. Rate</label>
                        <input type="number" step="any" class="form-control" id="tk-rate" value="1">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">Airline</label>
                        <input type="text" class="form-control" id="tk-airline">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-2 mb-2"><label class="form-label">GDS</label><input type="text" class="form-control" id="tk-gds"></div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">Ticket Type</label>
                        <select class="form-control" id="tk-ticket-type">
                            <option value="international">International</option>
                            <option value="domestic">Domestic</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2"><label class="form-label">Issue Date</label><input type="date" class="form-control" id="tk-issue-date"></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Sector</label><input type="text" class="form-control" id="tk-sector" placeholder="e.g. KHI-JED-KHI"></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Tour Code</label><input type="text" class="form-control" id="tk-tour-code"></div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-2"><label class="form-label">PNR</label><input type="text" class="form-control" id="tk-pnr"></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Receivable (F) <small class="text-muted">per ticket</small></label><input type="number" step="any" class="form-control" id="tk-recv" value="0"></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Payable (F) <small class="text-muted">per ticket</small></label><input type="number" step="any" class="form-control" id="tk-pay" value="0"></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Income (Local) <small class="text-muted">per ticket</small></label><div class="form-control-plaintext fw-bold line-income" id="tk-income">0.00</div></div>
                </div>

                <div class="mb-2">
                    <label class="form-label d-block">Flight Legs <small class="text-muted">(shared by every ticket)</small></label>
                    <table class="table table-bordered table-sm mini-table" id="tk-flights-table">
                        <thead><tr><th>City</th><th>Flight No</th><th>Dep Date</th><th>Dep Time</th><th>Arr Time</th><th>Fare Basis</th><th width="36"></th></tr></thead>
                        <tbody></tbody>
                    </table>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="tkAddFlightRow()">+ Add Flight Leg</button>
                </div>
                <template id="tk-flight-tpl">
                    <tr>
                        <td><input type="text" class="form-control form-control-sm tk-f-city"></td>
                        <td><input type="text" class="form-control form-control-sm tk-f-flightno"></td>
                        <td><input type="date" class="form-control form-control-sm tk-f-depdate"></td>
                        <td><input type="text" class="form-control form-control-sm tk-f-deptime" placeholder="HH:MM"></td>
                        <td><input type="text" class="form-control form-control-sm tk-f-arrtime" placeholder="HH:MM"></td>
                        <td><input type="text" class="form-control form-control-sm tk-f-farebasis"></td>
                        <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); tkSyncAll();"><i class="fas fa-times"></i></button></td>
                    </tr>
                </template>

                <div class="mb-1">
                    <label class="form-label d-block">Charges (SPO / WHT / COM / PSF / Tax ...) <small class="text-muted">(shared by every ticket)</small></label>
                    <table class="table table-bordered table-sm mini-table" id="tk-charges-table">
                        <thead><tr><th>Charge Type</th><th width="18%">Value</th><th width="20%">Amount (+/-)</th><th width="36"></th></tr></thead>
                        <tbody></tbody>
                    </table>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="tkAddChargeRow()">+ Add Charge</button>
                </div>
                <template id="tk-charge-tpl">
                    <tr>
                        <td>
                            <select class="form-control form-control-sm tk-c-type" onchange="tkOnChargeTypeChange(this)">
                                <option value="">— Select —</option>
                                @foreach($chargeTypes as $ct)<option value="{{ $ct->id }}" data-calc="{{ $ct->calculation_type }}" data-default="{{ $ct->default_value }}">{{ $ct->name }}</option>@endforeach
                            </select>
                        </td>
                        <td><input type="number" step="any" class="form-control form-control-sm tk-c-value" value="0"></td>
                        <td><input type="number" step="any" class="form-control form-control-sm tk-c-amount" value="0"></td>
                        <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); tkRecalcMaster();"><i class="fas fa-times"></i></button></td>
                    </tr>
                </template>
            </div>
        </section>

        <section class="card mb-3">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title h6 mb-0">Tickets <small class="text-muted">— one row per passenger</small></h3>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="tkAddTicketRow()"><i class="fas fa-plus"></i> Add Ticket</button>
            </header>
            <div class="card-body">
                <p class="text-muted small mb-2">Passenger Name is free text, same as the approved Ticket Sale Invoice layout. Ticket # auto-fills from the first row's number — type it once and the rest increment; editing any row by hand stops it being overwritten. <span class="field-note">Pax Type here is visual only for now — it isn't saved yet.</span></p>
                <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0" id="tk-tickets-table">
                    <thead>
                        <tr>
                            <th style="width:3%;">#</th>
                            <th>Passenger Name</th>
                            <th style="width:16%;">Pax Type</th>
                            <th style="width:18%;">Ticket #</th>
                            <th style="width:12%;" class="text-end">Income</th>
                            <th style="width:5%;"></th>
                        </tr>
                    </thead>
                    <tbody id="tk-tickets-wrap"></tbody>
                </table>
                </div>
            </div>
        </section>

        <template id="tk-ticket-row-tpl">
            <tr>
                <td class="align-middle"><span class="tk-row-index"></span></td>
                <td><input type="text" class="form-control form-control-sm tk-row-name" placeholder="Passenger name"></td>
                <td>
                    <select class="form-control form-control-sm tk-row-paxtype not-persisted-field">
                        <option value="adult">Adult</option><option value="child">Child</option><option value="infant">Infant</option>
                    </select>
                </td>
                <td><input type="text" class="form-control form-control-sm tk-row-ticketno" placeholder="Ticket #"></td>
                <td class="text-end align-middle line-income fw-bold">0.00</td>
                <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger" onclick="tkRemoveRow(this)"><i class="fas fa-trash"></i></button></td>
            </tr>
        </template>

        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <span class="text-muted"><span id="tk-ticket-count">0</span> ticket(s) &times; <span id="tk-per-ticket">0.00</span></span>
                <h5 class="mb-0">Ticket Tab Total: <span id="tk-tab-total">0.00</span></h5>
            </div>
        </div>
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

            <h6 class="text-uppercase text-muted small fw-bold mb-2">1. Booking Details</h6>
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
                    <label class="form-label">Booking Name <small class="field-note">(auto from Customer)</small></label>
                    <input type="text" name="lines[__IDX__][detail][booking_name]" class="form-control hotel-booking-name">
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
            <h6 class="text-uppercase text-muted small fw-bold mb-2">2. Room Details</h6>
            <table class="table table-bordered table-sm mini-table hotel-rooms-table">
                <thead><tr><th>Room Type</th><th>Room View</th><th width="12%">No. of Room</th><th width="16%">Rate</th><th width="16%">Total Amount</th><th width="36"></th></tr></thead>
                <tbody></tbody>
            </table>
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
                    <td><input type="number" min="0" name="lines[__IDX__][detail][rooms][__RIDX__][qty]" class="form-control form-control-sm hotel-room-row-qty" value="1" oninput="recalcHotelRoomRow(this.closest('tr'))"></td>
                    <td><input type="number" step="any" min="0" name="lines[__IDX__][detail][rooms][__RIDX__][rate]" class="form-control form-control-sm hotel-room-row-rate" value="0" oninput="recalcHotelRoomRow(this.closest('tr'))"></td>
                    <td><input type="number" step="any" name="lines[__IDX__][detail][rooms][__RIDX__][total_amount]" class="form-control form-control-sm hotel-room-row-total" value="0" readonly></td>
                    <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();"><i class="fas fa-times"></i></button></td>
                </tr>
            </template>

            <hr class="my-2">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-uppercase text-muted small fw-bold mb-2">3. Receivables</h6>
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Receivable (F)</label>
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
                            <label class="form-label">Payable (F)</label>
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

    {{-- ============ One tab + one <template> per remaining service type ============ --}}
    @foreach($activeTabs as $type => $label)
    @continue($type === 'ticket' || $type === 'hotel')
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
            <div class="row">
                <div class="col-md-3 mb-2"><label class="form-label">Transporter <small class="field-note">(not saved yet)</small></label><input type="text" class="form-control not-persisted-field"></div>
                <div class="col-md-3 mb-2"><label class="form-label">Package <small class="field-note">(not saved yet)</small></label><input type="text" class="form-control not-persisted-field"></div>
                <div class="col-md-2 mb-2"><label class="form-label">Inventory <small class="field-note">(not saved yet)</small></label><input type="text" class="form-control not-persisted-field"></div>
                <div class="col-md-2 mb-2"><label class="form-label">Reference No <small class="field-note">(not saved yet)</small></label><input type="text" class="form-control not-persisted-field"></div>
                <div class="col-md-2 mb-2"><label class="form-label">Category <small class="field-note">(not saved yet)</small></label><input type="text" class="form-control not-persisted-field"></div>
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
    }

    function recalcHotelRoomRow(row) {
        const qty = parseFloat(row.querySelector('.hotel-room-row-qty')?.value) || 0;
        const rate = parseFloat(row.querySelector('.hotel-room-row-rate')?.value) || 0;
        const totalField = row.querySelector('.hotel-room-row-total');
        if (totalField) totalField.value = (qty * rate).toFixed(2);
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
        recalcHotelRoomRow(row);

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
            // Hotel cards have no single `.line-rate` — fall back to the
            // Receivable exchange rate for the percentage base there.
            const rateEl = card.querySelector('.line-rate') || card.querySelector('.hotel-recv-rate');
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

            // Client fix: Booking Name auto-picks up the Customer selected
            // in General Information — only default it on a brand-new
            // line (nothing saved yet); an existing saved line keeps
            // whatever name was actually booked under, already set above
            // by the generic [detail][...] loop.
            if (!detail.booking_name) {
                const bookingNameEl = card.querySelector('.hotel-booking-name');
                const customerSelect = document.querySelector('select[name="customer_id"]');
                const label = customerSelect?.selectedOptions[0]?.textContent.trim();
                if (bookingNameEl && label && label !== 'Select Customer') bookingNameEl.value = label;
            }
        }

        if (window.jQuery) $(card).find('.select2-js').select2({dropdownParent: card});

        recalcCard(card);
        return card;
    }

    function recalcCard(card) {
        // Hotel cards use their own dual-currency PSF calculation instead
        // (no single `.line-rate`, and PSF excludes charges per the
        // client's exact "PSF = receivable − payable" formula).
        if (card.dataset.type === 'hotel') {
            recalcHotelFinance(card);
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

    // ============ Ticket tab: Master Details + Grid ============
    let tkInitialized = false;

    function tkAddFlightRow(data) {
        const tpl = document.getElementById('tk-flight-tpl');
        const tbody = document.querySelector('#tk-flights-table tbody');
        const row = tpl.content.firstElementChild.cloneNode(true);
        tbody.appendChild(row);
        if (data) {
            row.querySelector('.tk-f-city').value = data.city || '';
            row.querySelector('.tk-f-flightno').value = data.flight_no || '';
            row.querySelector('.tk-f-depdate').value = data.dep_date || '';
            row.querySelector('.tk-f-deptime').value = data.dep_time || '';
            row.querySelector('.tk-f-arrtime').value = data.arr_time || '';
            row.querySelector('.tk-f-farebasis').value = data.fare_basis || '';
        }
        row.querySelectorAll('input').forEach(el => el.addEventListener('input', tkSyncAll));
        tkSyncAll();
    }

    function tkOnChargeTypeChange(select) {
        const opt = select.options[select.selectedIndex];
        const row = select.closest('tr');
        if (opt && opt.dataset.default) row.querySelector('.tk-c-value').value = opt.dataset.default;
        tkRecalcChargeRow(row, opt);
    }

    function tkRecalcChargeRow(row, opt) {
        opt = opt || row.querySelector('.tk-c-type').selectedOptions[0];
        const value = parseFloat(row.querySelector('.tk-c-value').value) || 0;
        if (opt && opt.dataset.calc === 'percentage') {
            const rate = parseFloat(document.getElementById('tk-rate').value) || 0;
            const recv = parseFloat(document.getElementById('tk-recv').value) || 0;
            row.querySelector('.tk-c-amount').value = ((recv * rate) * value / 100).toFixed(2);
        }
        tkRecalcMaster();
    }

    function tkAddChargeRow(data) {
        const tpl = document.getElementById('tk-charge-tpl');
        const tbody = document.querySelector('#tk-charges-table tbody');
        const row = tpl.content.firstElementChild.cloneNode(true);
        tbody.appendChild(row);
        if (data) {
            row.querySelector('.tk-c-type').value = data.charge_type_id || '';
            row.querySelector('.tk-c-value').value = data.value ?? 0;
            row.querySelector('.tk-c-amount').value = data.computed_amount ?? 0;
        }
        row.querySelector('.tk-c-value').addEventListener('input', () => tkRecalcChargeRow(row));
        row.querySelector('.tk-c-amount').addEventListener('input', tkRecalcMaster);
        if (window.jQuery) $(row).find('select').select2({dropdownParent: row});
        tkRecalcMaster();
    }

    function onTicketTemplateChange(select) {
        const opt = select.options[select.selectedIndex];
        if (!opt || !opt.value) { tkRecalcMaster(); return; }
        if (opt.dataset.currency) document.getElementById('tk-currency').value = opt.dataset.currency;
        if (opt.dataset.rate) document.getElementById('tk-rate').value = opt.dataset.rate;
        const tbody = document.querySelector('#tk-charges-table tbody');
        tbody.innerHTML = '';
        const items = JSON.parse(opt.dataset.items || '[]');
        items.forEach(it => tkAddChargeRow({ charge_type_id: it.charge_type_id, value: it.value, computed_amount: 0 }));
        tbody.querySelectorAll('tr').forEach(row => tkRecalcChargeRow(row));
        tkRecalcMaster();
    }

    function tkRecalcMaster() {
        const rate = parseFloat(document.getElementById('tk-rate').value) || 0;
        const recv = parseFloat(document.getElementById('tk-recv').value) || 0;
        const pay = parseFloat(document.getElementById('tk-pay').value) || 0;
        let chargesTotal = 0;
        document.querySelectorAll('#tk-charges-table .tk-c-amount').forEach(el => chargesTotal += (parseFloat(el.value) || 0));
        const income = (recv * rate) - (pay * rate) + chargesTotal;
        document.getElementById('tk-income').textContent = income.toFixed(2);
        tkRecalcGrandTotal(income);
        tkSyncAll();
    }

    function tkRecalcGrandTotal(perTicketIncome) {
        const rows = document.querySelectorAll('#tk-tickets-wrap tr');
        rows.forEach(row => { row.querySelector('.line-income').textContent = perTicketIncome.toFixed(2); });
        document.getElementById('tk-ticket-count').textContent = rows.length;
        document.getElementById('tk-per-ticket').textContent = perTicketIncome.toFixed(2);
        document.getElementById('tk-tab-total').textContent = (perTicketIncome * rows.length).toFixed(2);
        recalcTotals();
    }

    function tkAutoSequence() {
        const rows = Array.from(document.querySelectorAll('#tk-tickets-wrap tr'));
        if (!rows.length) return;
        const first = rows[0].querySelector('.tk-row-ticketno').value.trim();
        const digitsMatch = first.match(/\d+$/);
        if (!digitsMatch) { tkSyncAll(); return; }
        const base = parseInt(digitsMatch[0], 10);
        const prefix = first.slice(0, first.length - digitsMatch[0].length);
        const pad = digitsMatch[0].length;
        rows.forEach((row, i) => {
            if (i === 0) return;
            const input = row.querySelector('.tk-row-ticketno');
            const isBlank = input.value.trim().length === 0;
            if (isBlank || input.dataset.autoFilled === '1') {
                input.value = prefix + String(base + i).padStart(pad, '0');
                input.dataset.autoFilled = '1';
            }
        });
        tkSyncAll();
    }

    function tkReindexRows() {
        document.querySelectorAll('#tk-tickets-wrap tr').forEach((row, i) => {
            row.querySelector('.tk-row-index').textContent = i + 1;
        });
    }

    function tkAddTicketRow(data) {
        data = data || {};
        const tpl = document.getElementById('tk-ticket-row-tpl');
        const row = tpl.content.firstElementChild.cloneNode(true);
        row.dataset.lineIdx = lineIndex++;
        document.getElementById('tk-tickets-wrap').appendChild(row);
        row.querySelector('.tk-row-name').value = data.description || '';
        row.querySelector('.tk-row-ticketno').value = (data.detail && data.detail.ticket_no) || '';
        row.querySelector('.tk-row-name').addEventListener('input', tkSyncAll);
        row.querySelector('.tk-row-paxtype').addEventListener('change', tkSyncAll);
        row.querySelector('.tk-row-ticketno').addEventListener('input', e => {
            e.target.dataset.autoFilled = '';
            if (row === document.querySelector('#tk-tickets-wrap tr')) {
                tkAutoSequence();
            } else {
                tkSyncAll();
            }
        });
        tkReindexRows();
        tkRecalcMaster();
    }

    function tkRemoveRow(btn) {
        btn.closest('tr').remove();
        tkReindexRows();
        tkRecalcMaster();
    }

    // Rebuilds the real lines[] hidden inputs for every ticket row,
    // mirroring Master Details into each one — called whenever Master or
    // any row's own fields change, so the submitted payload always
    // reflects the latest state. Mirrors how the Ticket Sale Invoice page
    // cascades its own Master Details into every ticket row.
    function tkSyncAll() {
        const flights = Array.from(document.querySelectorAll('#tk-flights-table tbody tr')).map(r => ({
            city: r.querySelector('.tk-f-city').value,
            flight_no: r.querySelector('.tk-f-flightno').value,
            dep_date: r.querySelector('.tk-f-depdate').value,
            dep_time: r.querySelector('.tk-f-deptime').value,
            arr_time: r.querySelector('.tk-f-arrtime').value,
            fare_basis: r.querySelector('.tk-f-farebasis').value,
        }));
        const charges = Array.from(document.querySelectorAll('#tk-charges-table tbody tr'))
            .map(r => ({
                charge_type_id: r.querySelector('.tk-c-type').value,
                value: r.querySelector('.tk-c-value').value,
                computed_amount: r.querySelector('.tk-c-amount').value,
            }))
            .filter(c => c.charge_type_id);

        document.querySelectorAll('#tk-tickets-wrap tr').forEach(row => {
            row.querySelectorAll('.tk-hidden-field').forEach(el => el.remove());
            const idx = row.dataset.lineIdx;
            const fields = {
                'service_type': 'ticket',
                'supplier_id': document.getElementById('tk-supplier').value,
                'charge_template_id': document.getElementById('tk-template').value,
                'description': row.querySelector('.tk-row-name').value,
                'currency': document.getElementById('tk-currency').value,
                'exchange_rate': document.getElementById('tk-rate').value,
                'receivable_f_amount': document.getElementById('tk-recv').value,
                'payable_f_amount': document.getElementById('tk-pay').value,
                'detail[pnr]': document.getElementById('tk-pnr').value,
                'detail[gds]': document.getElementById('tk-gds').value,
                'detail[airline]': document.getElementById('tk-airline').value,
                'detail[ticket_type]': document.getElementById('tk-ticket-type').value,
                'detail[issue_date]': document.getElementById('tk-issue-date').value,
                'detail[sector]': document.getElementById('tk-sector').value,
                'detail[tour_code]': document.getElementById('tk-tour-code').value,
                'detail[ticket_no]': row.querySelector('.tk-row-ticketno').value,
            };
            Object.keys(fields).forEach(key => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.className = 'tk-hidden-field';
                input.name = `lines[${idx}][${key}]`;
                input.value = fields[key] ?? '';
                row.appendChild(input);
            });
            flights.forEach((f, fi) => {
                Object.keys(f).forEach(key => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.className = 'tk-hidden-field';
                    input.name = `lines[${idx}][detail][flights][${fi}][${key}]`;
                    input.value = f[key] ?? '';
                    row.appendChild(input);
                });
            });
            charges.forEach((c, ci) => {
                Object.keys(c).forEach(key => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.className = 'tk-hidden-field';
                    input.name = `lines[${idx}][charges][${ci}][${key}]`;
                    input.value = c[key] ?? '';
                    row.appendChild(input);
                });
            });
        });
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
            if (type === 'hotel') {
                // Dual currency: Receivable and Payable each convert with
                // their own exchange rate, and PSF (shown here as the
                // row's "Income") excludes charges per the client's exact
                // "PSF = receivable − payable" formula.
                const recvRate = parseFloat(card.querySelector('.hotel-recv-rate')?.value) || 0;
                const payRate = parseFloat(card.querySelector('.hotel-pay-rate')?.value) || 0;
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

        const tkRows = document.querySelectorAll('#tk-tickets-wrap tr');
        if (tkRows.length) {
            const rate = parseFloat(document.getElementById('tk-rate')?.value) || 0;
            const recv = parseFloat(document.getElementById('tk-recv')?.value) || 0;
            const pay = parseFloat(document.getElementById('tk-pay')?.value) || 0;
            let chargesTotal = 0;
            document.querySelectorAll('#tk-charges-table .tk-c-amount').forEach(el => chargesTotal += (parseFloat(el.value) || 0));
            const incomeEach = (recv * rate) - (pay * rate) + chargesTotal;
            tkRows.forEach(row => {
                const name = row.querySelector('.tk-row-name').value || '—';
                pushRow('ticket', name, document.getElementById('tk-currency').value, recv * rate, pay * rate, incomeEach);
            });
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

        if (document.getElementById('tk-tickets-table')) {
            const tkLines = grouped['ticket'] || [];
            if (tkLines.length) {
                const first = tkLines[0];
                document.getElementById('tk-supplier').value = first.supplier_id || '';
                document.getElementById('tk-template').value = first.charge_template_id || '';
                document.getElementById('tk-currency').value = first.currency || 'PKR';
                document.getElementById('tk-rate').value = first.exchange_rate ?? 1;
                document.getElementById('tk-recv').value = first.receivable_f_amount ?? 0;
                document.getElementById('tk-pay').value = first.payable_f_amount ?? 0;
                const d = first.detail || {};
                document.getElementById('tk-airline').value = d.airline || '';
                document.getElementById('tk-gds').value = d.gds || '';
                document.getElementById('tk-ticket-type').value = d.ticket_type || 'international';
                document.getElementById('tk-issue-date').value = d.issue_date || '';
                document.getElementById('tk-sector').value = d.sector || '';
                document.getElementById('tk-tour-code').value = d.tour_code || '';
                document.getElementById('tk-pnr').value = d.pnr || '';
                (d.flights || []).forEach(f => tkAddFlightRow(f));
                (first.charges || []).forEach(c => tkAddChargeRow(c));
                tkLines.forEach(line => tkAddTicketRow(line));
                if (window.jQuery) { $('#tk-supplier, #tk-template').trigger('change.select2'); }
            } else {
                tkAddTicketRow();
            }

            ['tk-supplier', 'tk-currency', 'tk-rate', 'tk-airline', 'tk-gds', 'tk-ticket-type', 'tk-issue-date', 'tk-sector', 'tk-tour-code', 'tk-pnr', 'tk-recv', 'tk-pay'].forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                el.addEventListener('input', tkRecalcMaster);
                el.addEventListener('change', tkRecalcMaster);
            });
        }

        // Client fix: Hotel's Booking Name auto-picks up the Customer
        // selected in General Information — re-synced into every current
        // Hotel line whenever the Customer changes (staff can still edit
        // any individual line's Booking Name afterward).
        const customerSelect = document.querySelector('select[name="customer_id"]');
        if (customerSelect) {
            customerSelect.addEventListener('change', function () {
                const label = this.selectedOptions[0] ? this.selectedOptions[0].textContent.trim() : '';
                if (!label || label === 'Select Customer') return;
                document.querySelectorAll('.hotel-booking-name').forEach(el => { el.value = label; });
            });
        }

        recalcTotals();
    });
</script>
