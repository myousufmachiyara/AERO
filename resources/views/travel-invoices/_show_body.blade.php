@php($badge = ['draft' => 'bg-secondary', 'confirmed' => 'bg-success', 'cancelled' => 'bg-danger'])
@php($linesByType = $invoice->serviceLines->groupBy('service_type'))
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="card-title">
                    {{ $invoice->invoice_no }}
                    <span class="badge {{ $badge[$invoice->status] ?? 'bg-secondary' }}">{{ ucfirst($invoice->status) }}</span>
                </h2>
                <div>
                    @can($routeUri . '.print')
                    <a href="{{ route($routeUri . '.print', $invoice->id) }}" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                    @endcan
                    @can($routeUri . '.edit')
                        @if($invoice->isEditable())
                        <a href="{{ route($routeUri . '.edit', $invoice->id) }}" class="btn btn-primary"><i class="fa fa-edit"></i> Edit</a>
                        @endif
                    @endcan
                </div>
            </header>
            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

                <div class="row">
                    <div class="col-md-2 mb-2"><strong>Date:</strong> {{ $invoice->invoice_date->format('d/m/Y') }}</div>
                    <div class="col-md-2 mb-2"><strong>Customer:</strong> {{ $invoice->customer->name ?? '—' }}</div>
                    <div class="col-md-2 mb-2"><strong>Visit Type:</strong> {{ $invoice->visit_type ?: '—' }}</div>
                    <div class="col-md-2 mb-2"><strong>Payment Mode:</strong> {{ ucfirst($invoice->payment_mode) }}</div>
                    <div class="col-md-2 mb-2"><strong>Staff:</strong> {{ $invoice->staff->name ?? '—' }}</div>
                    <div class="col-md-2 mb-2"><strong>Cost Center:</strong> {{ $invoice->cost_center ?: '—' }}</div>
                    @if($invoice->quotation)
                    <div class="col-md-4 mb-2"><strong>From Quotation:</strong> <a href="{{ route('quotations.show', $invoice->quotation_id) }}">{{ $invoice->quotation->quotation_no }}</a></div>
                    @endif
                </div>

                @can($routeUri . '.edit')
                <hr>
                <div class="d-flex gap-2 flex-wrap">
                    @if($invoice->status === 'draft')
                    <form action="{{ route($routeUri . '.status', $invoice->id) }}" method="POST">@csrf @method('PATCH')<input type="hidden" name="status" value="confirmed">
                        <button class="btn btn-sm btn-success">Confirm Invoice</button>
                    </form>
                    <form action="{{ route($routeUri . '.status', $invoice->id) }}" method="POST">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled">
                        <button class="btn btn-sm btn-danger">Cancel Invoice</button>
                    </form>
                    @endif
                </div>
                @endcan

                <hr>
                <h5>Passengers</h5>
                <table class="table table-bordered table-sm">
                    <thead><tr><th>Name</th><th>Passport/NIC</th><th>Type</th><th>Nationality</th></tr></thead>
                    <tbody>
                        @forelse($invoice->passengers as $pax)
                        <tr><td>{{ $pax->name }}</td><td>{{ $pax->passport_no_nic }}</td><td>{{ ucfirst($pax->pax_type) }}</td><td>{{ $pax->nationality }}</td></tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted">No passengers added.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- Tickets (Ticket Sale Invoice layout): Master Details once + one row per passenger. --}}
                @php($tkLines = $linesByType->get('ticket', collect()))
                @php($tkFirst = optional($tkLines->first())->ticketDetail)
                @if($tkLines->isNotEmpty() && $tkFirst && $tkFirst->hasTicketSalePricing())
                    <h5>Tickets</h5>
                    <div class="row small mb-2">
                        <div class="col-md-3"><strong>Supplier:</strong> {{ $tkLines->first()->supplier->name ?? '—' }}</div>
                        <div class="col-md-3"><strong>Airline:</strong> {{ $tkFirst->airlineRecord->name ?? ($tkFirst->airline ?: '—') }}</div>
                        <div class="col-md-2"><strong>PNR:</strong> {{ $tkFirst->pnr ?: '—' }}</div>
                        <div class="col-md-2"><strong>Ticket Type:</strong> {{ ucfirst($tkFirst->ticket_type) }}</div>
                        <div class="col-md-2"><strong>Trip Type:</strong> {{ $tkFirst->trip_type === 'return' ? 'Return' : 'One Way' }}</div>
                        <div class="col-md-6"><strong>Cities:</strong> {{ $tkFirst->citiesLabel() ?: '—' }}</div>
                        <div class="col-md-6"><strong>Departure:</strong>
                            {{ optional($tkFirst->leg1_departure_date)->format('d/m/Y') ?? '—' }} {{ $tkFirst->leg1_departure_time ? substr($tkFirst->leg1_departure_time, 0, 5) : '' }}
                            @if($tkFirst->trip_type === 'return') / Return: {{ optional($tkFirst->leg2_departure_date)->format('d/m/Y') ?? '—' }} {{ $tkFirst->leg2_departure_time ? substr($tkFirst->leg2_departure_time, 0, 5) : '' }}@endif
                        </div>
                        <div class="col-md-2"><strong>Fare:</strong> {{ number_format($tkFirst->fare_amount, 2) }}</div>
                        <div class="col-md-2"><strong>Tax:</strong> {{ number_format($tkFirst->tax_amount, 2) }}</div>
                        <div class="col-md-2"><strong>APT:</strong> {{ number_format($tkFirst->apt_charges, 2) }} <small class="text-muted">({{ number_format($tkFirst->apt_percent, 2) }}%)</small></div>
                        <div class="col-md-3"><strong>Commission:</strong> {{ number_format($tkFirst->commission_amount, 2) }} <small class="text-muted">({{ number_format($tkFirst->commission_percent, 2) }}%)</small></div>
                        <div class="col-md-3"><strong>WHT:</strong> {{ number_format($tkFirst->wht_amount, 2) }} <small class="text-muted">({{ number_format($tkFirst->wht_percent, 2) }}% on comm)</small></div>
                        <div class="col-md-2"><strong>PSF:</strong> {{ number_format($tkFirst->psf_amount, 2) }} <small class="text-muted">({{ number_format($tkFirst->psf_percent, 2) }}% {{ $tkFirst->psf_basis === 'total' ? 'on total' : 'on fare' }})</small></div>
                        <div class="col-md-2"><strong>Discount:</strong> {{ number_format($tkFirst->discount_amount, 2) }} <small class="text-muted">({{ number_format($tkFirst->discount_percent, 2) }}%)</small></div>
                        <div class="col-md-3"><strong>Sale Agent (SPO):</strong> {{ $tkFirst->salesAgent->name ?? '—' }}</div>
                        <div class="col-md-3"><strong>Sale Commission:</strong> {{ number_format($tkFirst->agent_commission_amount, 2) }} <small class="text-muted">({{ number_format($tkFirst->agent_commission_percent, 2) }}% on PSF)</small></div>
                    </div>
                    <table class="table table-bordered table-sm">
                        <thead><tr><th>#</th><th>Passenger Name</th><th>Passenger Type</th><th>Ticket #</th><th class="text-end">Ticket Total</th><th class="text-end">Supplier Net</th><th class="text-end">Income</th></tr></thead>
                        <tbody>
                            @foreach($tkLines as $i => $line)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $line->description ?: '—' }}</td>
                                <td>{{ ucfirst($line->ticketDetail->pax_type ?? 'adult') }}</td>
                                <td>{{ $line->ticketDetail->ticket_no ?? '—' }}</td>
                                <td class="text-end">{{ number_format($line->receivable_l_amount, 2) }}</td>
                                <td class="text-end">{{ number_format($line->payable_l_amount, 2) }}</td>
                                <td class="text-end {{ $line->income_l_amount < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($line->income_l_amount, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="4" class="text-end">Tickets Total ({{ $tkLines->count() }})</td>
                                <td class="text-end">{{ number_format($tkLines->sum('receivable_l_amount'), 2) }}</td>
                                <td class="text-end">{{ number_format($tkLines->sum('payable_l_amount'), 2) }}</td>
                                <td class="text-end">{{ number_format($tkLines->sum('income_l_amount'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                    @php($linesByType = collect($linesByType->all())->except('ticket'))
                @endif

                @foreach(['ticket' => 'Ticket', 'hotel' => 'Hotel', 'transport' => 'Transport', 'visa' => 'Visa', 'other' => 'Other Services'] as $type => $label)
                    @if($linesByType->has($type))
                    <h5>{{ $label }}</h5>
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Supplier</th>
                                @if($type === 'ticket')<th>PNR</th><th>Airline</th><th>Sector</th><th>Ticket No</th>@endif
                                @if($type === 'hotel')<th>Hotel</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Nights</th>@endif
                                @if($type === 'transport')<th>Vehicle</th><th>Sector</th><th>Booking Name</th><th>Remarks</th>@endif
                                @if($type === 'visa')<th>Visa Type</th><th>Apply Date</th><th>Expiry Date</th>@endif
                                @if($type === 'other')<th>Service</th><th>Qty</th><th>Booking Name</th><th>Travel Date</th><th>Remarks</th>@endif
                                <th>Currency</th><th>Receivable</th><th>Payable</th><th>Charges</th><th>Income</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($linesByType[$type] as $line)
                            <tr>
                                <td>{{ $line->supplier->name ?? '—' }}</td>
                                @if($type === 'ticket')
                                    <td>{{ $line->ticketDetail->pnr ?? '—' }}</td>
                                    <td>{{ $line->ticketDetail->airline ?? '—' }}</td>
                                    <td>{{ $line->ticketDetail->sector ?? '—' }}</td>
                                    <td>{{ $line->ticketDetail->ticket_no ?? '—' }}</td>
                                @endif
                                @if($type === 'hotel')
                                    <td>{{ $line->hotelDetail->hotel->name ?? '—' }}</td>
                                    <td>
                                        {{-- Room Details is now a grid (a booking can cover more than one room type) — prefer it, falling back to the old single-room field for lines saved before this fix. --}}
                                        @php($roomNames = $line->hotelDetail ? $line->hotelDetail->rooms->map(fn ($r) => optional($r->hotelRoom)->room_type)->filter()->values() : collect())
                                        @if($line->hotelDetail && $line->hotelDetail->rooms->contains(fn ($r) => (float) $r->rate > 0 || (float) $r->cost > 0))
                                            @foreach($line->hotelDetail->rooms as $r)
                                            <div class="small text-nowrap">
                                                <strong>{{ optional($r->hotelRoom)->room_type ?? 'Room' }}</strong>{{ $r->roomView ? ' (' . $r->roomView->name . ')' : '' }}
                                                × {{ $r->qty }} · {{ $line->hotelDetail->nights }} night(s)<br>
                                                <span class="text-success">Rate {{ number_format($r->rate, 2) }} → {{ number_format($r->total_amount, 2) }}</span>
                                                · <span class="text-danger">Cost {{ number_format($r->cost, 2) }} → {{ number_format($r->total_cost, 2) }}</span>
                                            </div>
                                            @endforeach
                                        @else
                                        {{ $roomNames->isNotEmpty() ? $roomNames->implode(', ') : ($line->hotelDetail->hotelRoom->room_type ?? '—') }}
                                        @endif
                                    </td>
                                    <td>{{ optional($line->hotelDetail?->check_in)->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ optional($line->hotelDetail?->check_out)->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ $line->hotelDetail->nights ?? '—' }}</td>
                                @endif
                                @if($type === 'transport')
                                    {{-- Vehicle Details is now a grid (a line can cover more than one vehicle) — prefer it, falling back to the old single-vehicle fields for lines saved before this fix. --}}
                                    @php($transportVehicles = $line->transportDetail ? $line->transportDetail->vehicles : collect())
                                    @php($vehicleNames = $transportVehicles->map(fn ($v) => optional($v->vehicle)->name)->filter()->values())
                                    @php($vehicleSectors = $transportVehicles->map(fn ($v) => $v->sector)->filter()->values())
                                    <td>{{ $vehicleNames->isNotEmpty() ? $vehicleNames->implode(', ') : ($line->transportDetail->vehicle->name ?? '—') }}</td>
                                    <td>{{ $vehicleSectors->isNotEmpty() ? $vehicleSectors->implode(', ') : ($line->transportDetail->sector ?? '—') }}</td>
                                    <td>{{ $line->transportDetail->booking_name ?? '—' }}</td>
                                    <td>{{ $line->description ?: '—' }}</td>
                                @endif
                                @if($type === 'visa')
                                    <td>{{ $line->visaDetail->visaType->name ?? '—' }}</td>
                                    <td>{{ optional($line->visaDetail?->apply_date)->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ optional($line->visaDetail?->expiry_date)->format('d/m/Y') ?? '—' }}</td>
                                @endif
                                @if($type === 'other')
                                    <td>{{ $line->otherDetail->service->name ?? '—' }}</td>
                                    <td>{{ $line->otherDetail->qty ?? '—' }}</td>
                                    <td>{{ $line->otherDetail->booking_name ?? '—' }}</td>
                                    <td>{{ optional($line->otherDetail?->travel_date)->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ $line->description ?: '—' }}</td>
                                @endif
                                <td>{{ $line->currency }}</td>
                                <td>{{ number_format($line->receivable_l_amount, 2) }}</td>
                                <td>{{ number_format($line->payable_l_amount, 2) }}</td>
                                <td>
                                    @forelse($line->charges as $charge)
                                        <div class="small">{{ $charge->chargeType->name ?? '—' }}: {{ number_format($charge->computed_amount, 2) }}</div>
                                    @empty
                                        —
                                    @endforelse
                                </td>
                                <td class="{{ $line->income_l_amount < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($line->income_l_amount, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                @endforeach

                <div class="row">
                    <div class="col-md-6">
                        @if($invoice->remarks)<strong>Remarks:</strong> {{ $invoice->remarks }}@endif
                    </div>
                    <div class="col-md-6 text-end">
                        <table class="table table-borderless mb-0" style="max-width:320px;margin-left:auto;">
                            <tr><th>Total Receivable</th><td class="text-end">{{ number_format($invoice->total_receivable, 2) }}</td></tr>
                            <tr><th>Total Payable</th><td class="text-end">{{ number_format($invoice->total_payable, 2) }}</td></tr>
                            <tr><th>Total Income</th><td class="text-end"><strong>{{ number_format($invoice->total_income, 2) }}</strong></td></tr>
                            <tr><th>Received</th><td class="text-end text-success">{{ number_format($invoice->receivedAmount(), 2) }}</td></tr>
                            <tr><th>Outstanding</th><td class="text-end"><strong class="{{ $invoice->outstandingAmount() > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($invoice->outstandingAmount(), 2) }}</strong></td></tr>
                        </table>
                    </div>
                </div>

                <hr>
                <h5 style="display:flex;justify-content:space-between;align-items:center;">
                    Payments
                    @can('invoice_payments.create')
                    @if($invoice->outstandingAmount() > 0)
                    <a href="{{ route('invoice_payments.create', ['direction' => 'receipt', 'customer_id' => $invoice->customer_id, 'travel_invoice_id' => $invoice->id]) }}" class="btn btn-sm btn-success">+ Record Receipt</a>
                    @endif
                    @endcan
                </h5>
                <table class="table table-bordered table-sm">
                    <thead><tr><th>Date</th><th>Amount</th><th>Mode</th><th>Reference</th></tr></thead>
                    <tbody>
                        @forelse($invoice->payments as $p)
                        <tr><td>{{ $p->payment_date->format('d/m/Y') }}</td><td>{{ number_format($p->amount, 2) }}</td><td>{{ ucfirst($p->payment_mode) }}</td><td>{{ $p->reference ?: '—' }}</td></tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted">No payments recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
