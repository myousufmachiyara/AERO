{{-- Customer copy of a Tour Invoice: what was booked and what the customer pays — no supplier, cost or margin. --}}
@extends('prints._document', ['docTitle' => 'INVOICE', 'docNo' => $invoice->invoice_no])

@php
    $linesByType = $invoice->serviceLines->groupBy('service_type');
    $labels = ['ticket' => 'Air Tickets', 'hotel' => 'Hotel', 'transport' => 'Transport', 'visa' => 'Visa', 'other' => 'Other Services'];
    $received = $invoice->receivedAmount();
    $balance = round((float) $invoice->total_receivable - $received, 2);
@endphp

@section('stamp')
    @if($invoice->status === 'cancelled')<span class="stamp" style="color:#c0392b">CANCELLED</span>
    @elseif($invoice->status === 'draft')<span class="stamp" style="color:#8a96a3">DRAFT</span>
    @elseif($balance <= 0.001)<span class="stamp" style="color:#1e8449">PAID</span>@endif
@endsection

@section('meta')
    <div><span>Invoice No</span> <strong>{{ $invoice->invoice_no }}</strong></div>
    <div><span>Date</span> {{ $invoice->invoice_date->format('d/m/Y') }}</div>
    <div><span>Customer</span> <strong>{{ $invoice->customer->name ?? '—' }}</strong></div>
    <div><span>Name on Invoice</span> {{ $invoice->name_on_invoice ?: ($invoice->customer->name ?? '—') }}</div>
    <div><span>Visit Type</span> {{ $invoice->visit_type ?: '—' }}</div>
    <div><span>Payment Terms</span> {{ ucfirst($invoice->payment_mode) }}</div>
@endsection

@section('body')
    @if($invoice->passengers->isNotEmpty())
    <h3>Passengers</h3>
    <table>
        <thead><tr><th style="width:30px">#</th><th>Name</th><th>Passport / NIC</th><th>Type</th><th>Nationality</th></tr></thead>
        <tbody>
            @foreach($invoice->passengers as $i => $pax)
            <tr><td>{{ $i + 1 }}</td><td>{{ $pax->name }}</td><td>{{ $pax->passport_no_nic ?: '—' }}</td><td>{{ ucfirst($pax->pax_type) }}</td><td>{{ $pax->nationality ?: '—' }}</td></tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @foreach($labels as $type => $label)
        @php($lines = $linesByType->get($type, collect()))
        @continue($lines->isEmpty())
        <h3>{{ $label }}</h3>
        <table>
            <thead><tr><th style="width:30px">#</th><th>Details</th><th class="num" style="width:120px">Amount (PKR)</th></tr></thead>
            <tbody>
            @foreach($lines as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                    @switch($type)
                        @case('ticket')
                            @php($d = $line->ticketDetail)
                            <strong>{{ $line->description ?: '—' }}</strong>
                            @if($d)
                                <div class="muted">
                                    {{ $d->airlineRecord->name ?? $d->airline ?? '' }}
                                    @if($d->ticket_no) · Ticket {{ $d->ticket_no }}@endif
                                    @if($d->citiesLabel() !== '') · {{ $d->citiesLabel() }}@elseif($d->sector) · {{ $d->sector }}@endif
                                    @if($d->leg1_departure_date) · Departs {{ $d->leg1_departure_date->format('d/m/Y') }}@endif
                                    @if($d->pnr) · PNR {{ $d->pnr }}@endif
                                </div>
                            @endif
                            @break
                        @case('hotel')
                            @php($d = $line->hotelDetail)
                            <strong>{{ $d->hotel->name ?? ($line->description ?: 'Hotel') }}</strong>@if($d?->hotel?->city), {{ $d->hotel->city }}@endif
                            @if($d)
                                <div class="muted">
                                    @if($d->check_in){{ $d->check_in->format('d/m/Y') }} → {{ $d->check_out?->format('d/m/Y') }} · @endif{{ $d->nights }} night(s)
                                    @if($d->booking_name) · Booking name: {{ $d->booking_name }}@endif
                                </div>
                                @foreach($d->rooms as $r)
                                    <div class="muted">{{ $r->qty }} × {{ $r->hotelRoom->room_type ?? 'Room' }}{{ $r->roomView ? ' (' . $r->roomView->name . ')' : '' }}</div>
                                @endforeach
                            @endif
                            @break
                        @case('transport')
                            @php($d = $line->transportDetail)
                            <strong>{{ $line->description ?: 'Transport' }}</strong>
                            @if($d)
                                @foreach($d->vehicles as $v)
                                    <div class="muted">{{ $v->vehicle->name ?? 'Vehicle' }}{{ $v->sector ? ' — ' . $v->sector : '' }}</div>
                                @endforeach
                                @if($d->vehicles->isEmpty() && ($d->vehicle || $d->sector))<div class="muted">{{ $d->vehicle->name ?? '' }} {{ $d->sector }}</div>@endif
                                @if($d->booking_name)<div class="muted">Booking name: {{ $d->booking_name }}</div>@endif
                            @endif
                            @break
                        @case('visa')
                            @php($d = $line->visaDetail)
                            <strong>{{ $d->visaType->name ?? 'Visa' }}</strong>@if($line->description) — {{ $line->description }}@endif
                            @if($d && ($d->apply_date || $d->reference_no))
                                <div class="muted">{{ collect([$d->apply_date ? 'Applied ' . $d->apply_date->format('d/m/Y') : null, $d->reference_no ? 'Ref ' . $d->reference_no : null])->filter()->implode(' · ') }}</div>
                            @endif
                            @break
                        @default
                            @php($d = $line->otherDetail)
                            <strong>{{ $d->service->name ?? ($line->description ?: 'Service') }}</strong>@if($d && $d->qty > 1) × {{ $d->qty }}@endif
                            <div class="muted">{{ collect([
                                $d?->travel_date ? 'Travel ' . $d->travel_date->format('d/m/Y') : null,
                                $d?->booking_name ? 'Booking name: ' . $d->booking_name : null,
                                $d?->service ? $line->description : null,
                            ])->filter()->implode(' · ') }}</div>
                    @endswitch
                    </td>
                    <td class="num">{{ number_format((float) $line->receivable_l_amount, 2) }}</td>
                </tr>
            @endforeach
                <tr class="sub"><td colspan="2" style="text-align:right">{{ $label }} total</td><td class="num">{!! \App\Support\Money::receivable((float) $lines->sum('receivable_l_amount')) !!}</td></tr>
            </tbody>
        </table>
    @endforeach

    <table class="totals">
        <tr class="grand"><td>Invoice Total</td><td class="num">{!! \App\Support\Money::receivable((float) $invoice->total_receivable) !!}</td></tr>
        <tr><td>Received</td><td class="num">{{ number_format($received, 2) }}</td></tr>
        <tr><td><strong>Balance Due</strong></td><td class="num"><strong>{!! \App\Support\Money::receivable($balance) !!}</strong></td></tr>
    </table>
    <div class="words">{{ \App\Support\AmountInWords::rupees((float) $invoice->total_receivable) }}</div>

    @if($invoice->remarks)<p><strong>Remarks:</strong> {{ $invoice->remarks }}</p>@endif

    <div class="signs"><div>Customer Signature</div><div>For {{ config('travel.company_name') }}</div></div>
@endsection
