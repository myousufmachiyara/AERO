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

                @foreach(['ticket' => 'Ticket', 'hotel' => 'Hotel', 'transport' => 'Transport', 'visa' => 'Visa', 'other' => 'Other Services'] as $type => $label)
                    @if($linesByType->has($type))
                    <h5>{{ $label }}</h5>
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Supplier</th>
                                @if($type === 'ticket')<th>PNR</th><th>Airline</th><th>Sector</th><th>Ticket No</th>@endif
                                @if($type === 'hotel')<th>Hotel</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Nights</th>@endif
                                @if($type === 'transport')<th>Vehicle</th><th>Sector</th>@endif
                                @if($type === 'visa')<th>Visa Type</th><th>Apply Date</th><th>Expiry Date</th>@endif
                                @if($type === 'other')<th>Service</th><th>Qty</th>@endif
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
                                    <td>{{ $line->hotelDetail->hotelRoom->room_type ?? '—' }}</td>
                                    <td>{{ optional($line->hotelDetail?->check_in)->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ optional($line->hotelDetail?->check_out)->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ $line->hotelDetail->nights ?? '—' }}</td>
                                @endif
                                @if($type === 'transport')
                                    <td>{{ $line->transportDetail->vehicle->name ?? '—' }}</td>
                                    <td>{{ $line->transportDetail->sector ?? '—' }}</td>
                                @endif
                                @if($type === 'visa')
                                    <td>{{ $line->visaDetail->visaType->name ?? '—' }}</td>
                                    <td>{{ optional($line->visaDetail?->apply_date)->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ optional($line->visaDetail?->expiry_date)->format('d/m/Y') ?? '—' }}</td>
                                @endif
                                @if($type === 'other')
                                    <td>{{ $line->otherDetail->service->name ?? '—' }}</td>
                                    <td>{{ $line->otherDetail->qty ?? '—' }}</td>
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
