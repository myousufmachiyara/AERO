<?php

namespace App\Services;

use App\Models\InvoiceHotelDetail;
use App\Models\InvoiceOtherDetail;
use App\Models\InvoiceTicketDetail;
use App\Models\InvoiceTicketFlight;
use App\Models\InvoiceTransportDetail;
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
                $ticket = InvoiceTicketDetail::create([
                    'service_line_id' => $line->id,
                    'pnr' => $detail['pnr'] ?? null,
                    'gds' => $detail['gds'] ?? null,
                    'airline' => $detail['airline'] ?? null,
                    'ticket_no' => $detail['ticket_no'] ?? null,
                    'ticket_type' => $detail['ticket_type'] ?? 'international',
                    'sector' => $detail['sector'] ?? null,
                    'tour_code' => $detail['tour_code'] ?? null,
                    'issue_date' => $detail['issue_date'] ?? null,
                ]);

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
                InvoiceHotelDetail::create([
                    'service_line_id' => $line->id,
                    'hotel_id' => $detail['hotel_id'] ?? null,
                    'hotel_room_id' => $detail['hotel_room_id'] ?? null,
                    'check_in' => $detail['check_in'] ?? null,
                    'check_out' => $detail['check_out'] ?? null,
                    'nights' => $detail['nights'] ?? 0,
                    'room_qty' => $detail['room_qty'] ?? 1,
                    'extra_bed_qty' => $detail['extra_bed_qty'] ?? 0,
                    'booking_name' => $detail['booking_name'] ?? null,
                ]);
                break;

            case 'transport':
                InvoiceTransportDetail::create([
                    'service_line_id' => $line->id,
                    'vehicle_id' => $detail['vehicle_id'] ?? null,
                    'sector' => $detail['sector'] ?? null,
                    'booking_name' => $detail['booking_name'] ?? null,
                ]);
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
}
