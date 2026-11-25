<?php

namespace App\Services;

use App\Models\InvoiceHotelDetail;
use App\Models\InvoiceHotelRoom;
use App\Models\InvoiceOtherDetail;
use App\Models\InvoiceTicketDetail;
use App\Models\InvoiceTicketFlight;
use App\Models\InvoiceTransportDetail;
use App\Models\InvoiceTransportVehicle;
use App\Models\InvoiceVisaDetail;
use App\Models\ServiceLine;
use App\Models\ServiceLineCharge;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the shared service-line rows (Ticket/Hotel/Transport/Visa/Other)
 * for whichever "linkable" owns them — a Quotation, or (Phase 3) a Sale/
 * Tour Invoice. Kept as one class so every call site computes
 * local-currency amounts and income the same way.
 *
 * Quotation rows only ever send the flat fields (service_type, supplier,
 * amounts, description). Invoice rows additionally may send a nested
 * `detail` array (shaped for the line's service_type) and a `charges`
 * array (open SPO1-6/WHT/COM/PSF list) — both are simply absent/empty for
 * a Quotation line, so this class handles both without a subclass.
 */
class ServiceLineWriter
{
    /**
     * Replace all service lines belonging to $linkable with $linesInput.
     * A full replace (delete + recreate) rather than a diff — mirrors how
     * ChargeTemplateController handles its items, and keeps the form's
     * indexed array the single source of truth on every save.
     */
    public function sync(Model $linkable, array $linesInput): void
    {
        $linkable->serviceLines()->delete();

        foreach (array_values($linesInput) as $i => $row) {
            if (empty($row['service_type'])) {
                continue;
            }

            // Tour Invoice Ticket tab (Ticket Sale Invoice layout): price
            // the ticket with the Ticket Sale Invoice's own math, then
            // carry it on the service line as Receivable = Ticket Total
            // (customer), Payable = Supplier Net (airline/supplier), in PKR.
            // Quotation ticket lines never send `fare_amount`, so they are
            // untouched.
            if ($row['service_type'] === 'ticket' && is_array($row['detail'] ?? null) && array_key_exists('fare_amount', $row['detail'])) {
                $row['detail'] = InvoiceTicketDetail::applyTicketSalePricing($row['detail']);
                $row['currency'] = 'PKR';
                $row['exchange_rate'] = 1;
                $row['receivable_f_amount'] = $row['detail']['total_amount'];
                $row['payable_f_amount'] = $row['detail']['supplier_net'];
                $row['charges'] = [];
            }

            // Hotel tab (client fix): Receivable = Σ Rooms Total and Payable =
            // Σ Rooms Cost from Room Details (rate/cost per night × nights ×
            // rooms). Only applied when the grid actually carries a rate /
            // cost, so older lines priced by hand keep their amounts.
            if ($row['service_type'] === 'hotel' && is_array($row['detail'] ?? null) && !empty($row['detail']['rooms'])) {
                $row = $this->priceHotelRooms($row);
            }

            $line = new ServiceLine([
                'service_type' => $row['service_type'],
                'supplier_id' => $row['supplier_id'] ?? null,
                'charge_template_id' => $row['charge_template_id'] ?? null,
                'description' => $row['description'] ?? null,
                'currency' => $row['currency'] ?? 'PKR',
                'exchange_rate' => $row['exchange_rate'] ?? 1,
                'receivable_f_amount' => $row['receivable_f_amount'] ?? 0,
                'payable_f_amount' => $row['payable_f_amount'] ?? 0,
                'sort_order' => $i,
            ]);

            $chargesInput = $row['charges'] ?? [];
            $chargesTotal = $this->chargesTotal($chargesInput);

            $line->recalculate($chargesTotal);

            // Transport tab rework: a transport line has no single
            // lines[idx][exchange_rate] field (Receivable/Payable each
            // carry their own rate instead, same as Hotel) — so the
            // generic recalculate() above would wrongly convert at rate 1.
            // Override receivable/payable/income here from the detail's
            // own rates so the stored totals (Show page, Summary tab)
            // match the dual-currency PSF shown live on the Transport tab.
            if ($row['service_type'] === 'transport') {
                $detail = $row['detail'] ?? [];
                $recvRate = (float) ($detail['receivable_exchange_rate'] ?? 1);
                $payRate = (float) ($detail['payable_exchange_rate'] ?? 1);
                $line->receivable_l_amount = round(((float) ($row['receivable_f_amount'] ?? 0)) * $recvRate, 2);
                $line->payable_l_amount = round(((float) ($row['payable_f_amount'] ?? 0)) * $payRate, 2);
                $line->income_l_amount = round($line->receivable_l_amount - $line->payable_l_amount + $chargesTotal, 2);
            }

            $linkable->serviceLines()->save($line);

            $this->syncCharges($line, $chargesInput);
            $this->syncDetail($line, $row['detail'] ?? []);
        }

        if (method_exists($linkable, 'recalculateTotals')) {
            $linkable->recalculateTotals();
        }
    }

    /**
     * Signed sum of an incoming charges array before the parent line has
     * been saved (and therefore before the charge rows themselves exist),
     * so the line's income already reflects them at save time.
     */
    protected function chargesTotal(array $chargesInput): float
    {
        $total = 0.0;
        foreach ($chargesInput as $charge) {
            if (empty($charge['charge_type_id'])) {
                continue;
            }
            $total += (float) ($charge['computed_amount'] ?? 0);
        }

        return round($total, 2);
    }

    protected function syncCharges(ServiceLine $line, array $chargesInput): void
    {
        foreach ($chargesInput as $charge) {
            if (empty($charge['charge_type_id'])) {
                continue;
            }

            ServiceLineCharge::create([
                'service_line_id' => $line->id,
                'charge_type_id' => $charge['charge_type_id'],
                'value' => $charge['value'] ?? 0,
                'computed_amount' => $charge['computed_amount'] ?? 0,
            ]);
        }
    }

    /**
     * Persist the one-to-one structured detail row for this line's
     * service_type. Silently does nothing for a Quotation line (no
     * `detail` payload sent) or when the detail array is empty.
     */
    protected function syncDetail(ServiceLine $line, array $detail): void
    {
        if (empty($detail)) {
            return;
        }

        switch ($line->service_type) {
            case 'ticket':
                $ticketAttrs = [
                    'service_line_id' => $line->id,
                    'pnr' => $detail['pnr'] ?? null,
                    'gds' => $detail['gds'] ?? null,
                    'airline' => $detail['airline'] ?? null,
                    'ticket_no' => $detail['ticket_no'] ?? null,
                    'ticket_type' => $detail['ticket_type'] ?? 'international',
                    'sector' => $detail['sector'] ?? null,
                    'tour_code' => $detail['tour_code'] ?? null,
                    'issue_date' => $detail['issue_date'] ?? null,
                ];

                if (array_key_exists('fare_amount', $detail)) {
                    // Ticket Sale Invoice layout — every field it has.
                    foreach ([
                        'pax_type', 'airline_id', 'trip_type',
                        'leg1_from', 'leg1_stay', 'leg1_to', 'leg1_departure_date', 'leg1_departure_time',
                        'leg2_from', 'leg2_stay', 'leg2_to', 'leg2_departure_date', 'leg2_departure_time',
                        'sales_agent_id', 'supplier_net',
                    ] as $f) {
                        $ticketAttrs[$f] = filled($detail[$f] ?? null) ? $detail[$f] : null;
                    }
                    foreach (InvoiceTicketDetail::PRICING_FIELDS as $f) {
                        $ticketAttrs[$f] = $detail[$f];
                    }
                    $ticketAttrs['psf_basis'] = $detail['psf_basis'];
                    $ticketAttrs['pax_type'] = $ticketAttrs['pax_type'] ?: 'adult';
                    $ticketAttrs['trip_type'] = $ticketAttrs['trip_type'] ?: 'one_way';
                    if ($ticketAttrs['trip_type'] !== 'return') {
                        foreach (['leg2_from', 'leg2_stay', 'leg2_to', 'leg2_departure_date', 'leg2_departure_time'] as $f) {
                            $ticketAttrs[$f] = null;
                        }
                    }
                    // Keep the original text columns filled so the show/print
                    // pages and reports that read them keep working.
                    $ticketAttrs['airline'] = $ticketAttrs['airline_id']
                        ? optional(\App\Models\Airline::find($ticketAttrs['airline_id']))->name
                        : null;
                    $ticketAttrs['sector'] = (new InvoiceTicketDetail($ticketAttrs))->citiesLabel() ?: null;
                }

                $ticket = InvoiceTicketDetail::create($ticketAttrs);

                foreach (array_values($detail['flights'] ?? []) as $j => $flight) {
                    if (empty($flight['city']) && empty($flight['flight_no'])) {
                        continue;
                    }
                    InvoiceTicketFlight::create([
                        'invoice_ticket_detail_id' => $ticket->id,
                        'city' => $flight['city'] ?? null,
                        'flight_no' => $flight['flight_no'] ?? null,
                        'dep_date' => $flight['dep_date'] ?? null,
                        'dep_time' => $flight['dep_time'] ?? null,
                        'arr_time' => $flight['arr_time'] ?? null,
                        'fare_basis' => $flight['fare_basis'] ?? null,
                        'sort_order' => $j,
                    ]);
                }
                break;

            case 'hotel':
                // hotel_room_id/room_view_id/room_qty kept here for
                // backward compatibility (older data, and any direct API
                // caller still sending the old single-room shape) — the
                // Hotel tab UI itself no longer sends these at this level,
                // it sends a `rooms` array instead (one row per room
                // type), written to invoice_hotel_rooms below.
                $hotelDetail = InvoiceHotelDetail::create([
                    'service_line_id' => $line->id,
                    'hotel_id' => $detail['hotel_id'] ?? null,
                    'hotel_room_id' => $detail['hotel_room_id'] ?? null,
                    'room_view_id' => $detail['room_view_id'] ?? null,
                    'check_in' => $detail['check_in'] ?? null,
                    'check_out' => $detail['check_out'] ?? null,
                    'nights' => self::hotelNights($detail),
                    'room_qty' => $detail['room_qty'] ?? 1,
                    'extra_bed_qty' => $detail['extra_bed_qty'] ?? 0,
                    'booking_name' => $detail['booking_name'] ?? null,
                    'receivable_currency' => $detail['receivable_currency'] ?? 'PKR',
                    'receivable_exchange_rate' => $detail['receivable_exchange_rate'] ?? 1,
                    'payable_currency' => $detail['payable_currency'] ?? 'PKR',
                    'payable_exchange_rate' => $detail['payable_exchange_rate'] ?? 1,
                    'agent_commission_percent' => $detail['agent_commission_percent'] ?? 0,
                    'agent_commission_amount' => $detail['agent_commission_amount'] ?? 0,
                ]);

                foreach (array_values($detail['rooms'] ?? []) as $j => $room) {
                    if (empty($room['hotel_room_id']) && empty($room['room_view_id']) && empty($room['qty']) && empty($room['rate']) && empty($room['cost'])) {
                        continue;
                    }
                    [$roomsTotal, $roomsCost] = InvoiceHotelRoom::totalsFor(
                        $room['qty'] ?? 1, $room['rate'] ?? 0, $room['cost'] ?? 0, $hotelDetail->nights
                    );
                    InvoiceHotelRoom::create([
                        'invoice_hotel_detail_id' => $hotelDetail->id,
                        'hotel_room_id' => $room['hotel_room_id'] ?? null,
                        'room_view_id' => $room['room_view_id'] ?? null,
                        'qty' => $room['qty'] ?? 1,
                        'rate' => $room['rate'] ?? 0,
                        'cost' => $room['cost'] ?? 0,
                        'total_amount' => $roomsTotal,
                        'total_cost' => $roomsCost,
                        'sort_order' => $j,
                    ]);
                }
                break;

            case 'transport':
                // Transport tab rework: Reference No / Category are now
                // real, saved fields (unlike Hotel's Category, kept
                // informational-only), plus the same dual receivable/
                // payable currency+rate and agent commission fields as
                // Hotel's "Charges Details" section. vehicle_id/sector
                // kept here for back-compat (older data, any direct API
                // caller still sending the old single-vehicle shape) — the
                // Transport tab UI itself no longer sends these at this
                // level, it sends a `vehicles` array instead (one row per
                // vehicle), written to invoice_transport_vehicles below.
                $transportDetail = InvoiceTransportDetail::create([
                    'service_line_id' => $line->id,
                    'vehicle_id' => $detail['vehicle_id'] ?? null,
                    'sector' => $detail['sector'] ?? null,
                    'booking_name' => $detail['booking_name'] ?? null,
                    'reference_no' => $detail['reference_no'] ?? null,
                    'category' => $detail['category'] ?? null,
                    'receivable_currency' => $detail['receivable_currency'] ?? 'PKR',
                    'receivable_exchange_rate' => $detail['receivable_exchange_rate'] ?? 1,
                    'payable_currency' => $detail['payable_currency'] ?? 'PKR',
                    'payable_exchange_rate' => $detail['payable_exchange_rate'] ?? 1,
                    'agent_commission_percent' => $detail['agent_commission_percent'] ?? 0,
                    'agent_commission_amount' => $detail['agent_commission_amount'] ?? 0,
                ]);

                foreach (array_values($detail['vehicles'] ?? []) as $j => $veh) {
                    if (empty($veh['vehicle_id']) && empty($veh['sector'])) {
                        continue;
                    }
                    InvoiceTransportVehicle::create([
                        'invoice_transport_detail_id' => $transportDetail->id,
                        'vehicle_id' => $veh['vehicle_id'] ?? null,
                        'sector' => $veh['sector'] ?? null,
                        'sort_order' => $j,
                    ]);
                }
                break;

            case 'visa':
                InvoiceVisaDetail::create([
                    'service_line_id' => $line->id,
                    'visa_type_id' => $detail['visa_type_id'] ?? null,
                    'apply_date' => $detail['apply_date'] ?? null,
                    'expiry_date' => $detail['expiry_date'] ?? null,
                    'reference_no' => $detail['reference_no'] ?? null,
                ]);
                break;

            case 'other':
                InvoiceOtherDetail::create([
                    'service_line_id' => $line->id,
                    'service_id' => $detail['service_id'] ?? null,
                    'qty' => $detail['qty'] ?? 1,
                ]);
                break;
        }
    }

    /** Nights from Check-in/Check-out when both are given (same as the form), else the sent value. */
    public static function hotelNights(array $detail): int
    {
        if (!empty($detail['check_in']) && !empty($detail['check_out'])) {
            $in = \Illuminate\Support\Carbon::parse($detail['check_in'])->startOfDay();
            $out = \Illuminate\Support\Carbon::parse($detail['check_out'])->startOfDay();

            return $out->greaterThan($in) ? (int) $in->diffInDays($out) : 0;
        }

        return (int) ($detail['nights'] ?? 0);
    }

    /** Receivable (F) = Σ Rooms Total, Payable (F) = Σ Rooms Cost, when the grid has a rate / cost. */
    protected function priceHotelRooms(array $row): array
    {
        $nights = self::hotelNights($row['detail']);
        $receivable = 0.0;
        $payable = 0.0;
        $hasRate = false;
        $hasCost = false;

        foreach ($row['detail']['rooms'] as $room) {
            [$total, $cost] = InvoiceHotelRoom::totalsFor($room['qty'] ?? 1, $room['rate'] ?? 0, $room['cost'] ?? 0, $nights);
            $receivable += $total;
            $payable += $cost;
            $hasRate = $hasRate || (float) ($room['rate'] ?? 0) > 0;
            $hasCost = $hasCost || (float) ($room['cost'] ?? 0) > 0;
        }

        if ($hasRate) {
            $row['receivable_f_amount'] = round($receivable, 2);
        }
        if ($hasCost) {
            $row['payable_f_amount'] = round($payable, 2);
        }

        return $row;
    }
}
