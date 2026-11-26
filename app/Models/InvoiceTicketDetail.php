<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One ticket on a Tour Invoice (one ServiceLine each). Since Nov 2026 the
 * Tour Invoice Ticket tab is identical to the Ticket Sale Invoice, so this
 * carries the same pricing inputs as TicketSaleInvoiceLine — and prices
 * itself with that model's own recalculate()/supplierNet() (see
 * applyTicketSalePricing()) so the two modules can never disagree.
 *
 * pnr/gds/airline/ticket_no/ticket_type/sector/tour_code/issue_date and
 * the flights() legs are the original columns, kept for older invoices
 * and for the reports/print that read them (airline + sector are filled
 * from the new fields on save).
 */
class InvoiceTicketDetail extends Model
{
    /** The pricing fields shared 1:1 with TicketSaleInvoiceLine. */
    public const PRICING_FIELDS = [
        'fare_amount', 'tax_amount',
        'apt_percent', 'apt_charges', 'apt_input_mode',
        'commission_percent', 'commission_amount', 'commission_input_mode',
        'wht_percent', 'wht_amount', 'wht_input_mode',
        'psf_percent', 'psf_amount', 'psf_basis', 'psf_input_mode',
        'discount_percent', 'discount_amount', 'discount_input_mode',
        'agent_commission_percent', 'agent_commission_amount', 'agent_commission_input_mode',
        'total_amount',
    ];

    protected $fillable = [
        'service_line_id', 'pnr', 'gds', 'airline', 'ticket_no',
        'ticket_type', 'sector', 'tour_code', 'issue_date',
        'pax_type', 'airline_id', 'trip_type',
        'leg1_from', 'leg1_stay', 'leg1_to', 'leg1_departure_date', 'leg1_departure_time',
        'leg2_from', 'leg2_stay', 'leg2_to', 'leg2_departure_date', 'leg2_departure_time',
        'fare_amount', 'tax_amount',
        'apt_percent', 'apt_charges', 'apt_input_mode',
        'commission_percent', 'commission_amount', 'commission_input_mode',
        'wht_percent', 'wht_amount', 'wht_input_mode',
        'psf_percent', 'psf_amount', 'psf_basis', 'psf_input_mode',
        'discount_percent', 'discount_amount', 'discount_input_mode',
        'sales_agent_id', 'agent_commission_percent', 'agent_commission_amount', 'agent_commission_input_mode',
        'total_amount', 'supplier_net',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'leg1_departure_date' => 'date',
        'leg2_departure_date' => 'date',
        'fare_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'apt_percent' => 'decimal:2',
        'apt_charges' => 'decimal:2',
        'commission_percent' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'wht_percent' => 'decimal:2',
        'wht_amount' => 'decimal:2',
        'psf_percent' => 'decimal:2',
        'psf_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'agent_commission_percent' => 'decimal:2',
        'agent_commission_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'supplier_net' => 'decimal:2',
    ];

    public function serviceLine()
    {
        return $this->belongsTo(ServiceLine::class);
    }

    public function flights()
    {
        return $this->hasMany(InvoiceTicketFlight::class)->orderBy('sort_order');
    }

    public function airlineRecord()
    {
        return $this->belongsTo(Airline::class, 'airline_id');
    }

    public function salesAgent()
    {
        return $this->belongsTo(User::class, 'sales_agent_id');
    }

    /** True for tickets saved with the Ticket Sale Invoice layout (vs. older Receivable/Payable-only tickets). */
    public function hasTicketSalePricing(): bool
    {
        return (float) $this->fare_amount > 0 || (float) $this->total_amount > 0;
    }

    /** "KHI-DXB-LHR" / "KHI-LHR/LHR-KHI" — same as TicketSaleInvoiceLine::citiesLabel(). */
    public function citiesLabel(): string
    {
        $leg1 = implode('-', array_filter([$this->leg1_from, $this->leg1_stay, $this->leg1_to], fn ($v) => filled($v)));
        if ($this->trip_type !== 'return') {
            return $leg1;
        }
        $leg2 = implode('-', array_filter([$this->leg2_from, $this->leg2_stay, $this->leg2_to], fn ($v) => filled($v)));

        return $leg2 !== '' ? "{$leg1}/{$leg2}" : $leg1;
    }

    /**
     * Price a ticket from raw form input using the Ticket Sale Invoice's
     * own math, returning the input with every derived figure filled in
     * (percent ↔ amount pairs, total_amount, supplier_net).
     */
    public static function applyTicketSalePricing(array $input): array
    {
        $calc = new TicketSaleInvoiceLine();
        foreach (self::PRICING_FIELDS as $f) {
            if (array_key_exists($f, $input) && $input[$f] !== null && $input[$f] !== '') {
                $calc->{$f} = $input[$f];
            }
        }
        $calc->fare_amount = (float) ($input['fare_amount'] ?? 0);
        $calc->tax_amount = (float) ($input['tax_amount'] ?? 0);
        $calc->psf_basis = ($input['psf_basis'] ?? 'fare') === 'total' ? 'total' : 'fare';
        $calc->recalculate();

        foreach (self::PRICING_FIELDS as $f) {
            $input[$f] = $calc->{$f};
            if (str_ends_with($f, '_input_mode') && !in_array($input[$f], TicketSaleInvoiceLine::INPUT_MODES, true)) {
                $input[$f] = TicketSaleInvoiceLine::INPUT_MODE_PERCENT; // not sent → the column's default
            }
        }
        $input['psf_basis'] = $calc->psf_basis;
        $input['supplier_net'] = $calc->supplierNet();

        return $input;
    }
}
