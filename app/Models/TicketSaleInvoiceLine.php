<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketSaleInvoiceLine extends Model
{
    protected $fillable = [
        'ticket_sale_invoice_id', 'supplier_id', 'airline_id',
        'pax_name', 'pax_type', 'pnr', 'ticket_no',
        'trip_type', 'leg1_from', 'leg1_stay', 'leg1_to',
        'leg2_from', 'leg2_stay', 'leg2_to',
        'fare_amount', 'tax_amount', 'apt_charges', 'apt_percent', 'commission_percent',
        'commission_amount', 'wht_amount', 'wht_percent', 'psf_amount', 'psf_percent', 'psf_basis', 'discount_amount',
        'total_amount', 'sales_agent_id', 'agent_commission_amount', 'agent_commission_percent',
        'status',
        'refund_date', 'refund_adjustment_date', 'refund_fare_amount',
        'refund_tax_amount', 'refund_charges', 'refund_amount', 'refund_profit',
        'void_date', 'void_deduction_supplier', 'void_deduction_company', 'void_total_deduction',
        'sort_order', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'fare_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'apt_charges' => 'decimal:2',
        'apt_percent' => 'decimal:2',
        'commission_percent' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'wht_amount' => 'decimal:2',
        'wht_percent' => 'decimal:2',
        'psf_amount' => 'decimal:2',
        'psf_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'agent_commission_amount' => 'decimal:2',
        'agent_commission_percent' => 'decimal:2',
        'refund_date' => 'date',
        'refund_adjustment_date' => 'date',
        'refund_fare_amount' => 'decimal:2',
        'refund_tax_amount' => 'decimal:2',
        'refund_charges' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'refund_profit' => 'decimal:2',
        'void_date' => 'date',
        'void_deduction_supplier' => 'decimal:2',
        'void_deduction_company' => 'decimal:2',
        'void_total_deduction' => 'decimal:2',
    ];

    public const PAX_TYPES = ['adult', 'child', 'infant'];
    public const TRIP_TYPES = ['one_way', 'return'];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_VOIDED = 'voided';
    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_REFUNDED, self::STATUS_VOIDED];

    /**
     * What PSF % is calculated against. 'fare' = Fare Amount (most common).
     * 'total' = the ticket's fare+tax+APT subtotal — used for the airlines/
     * suppliers that price PSF off the fuller amount rather than the bare
     * fare. Deliberately excludes PSF and discount themselves from that
     * subtotal (a PSF-on-total-including-PSF would be circular).
     */
    public const PSF_BASIS_FARE = 'fare';
    public const PSF_BASIS_TOTAL = 'total';
    public const PSF_BASES = [self::PSF_BASIS_FARE, self::PSF_BASIS_TOTAL];

    public function invoice()
    {
        return $this->belongsTo(TicketSaleInvoice::class, 'ticket_sale_invoice_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function airline()
    {
        return $this->belongsTo(Airline::class);
    }

    public function salesAgent()
    {
        return $this->belongsTo(User::class, 'sales_agent_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Normalize a typed ticket number into the canonical 13-digit,
     * dash-separated format (3-4-3-3), e.g. "2201234567890" or
     * "220-1234-567-890" both become "220-1234-567-890". Returns null for
     * blank input and throws for anything that isn't exactly 13 digits,
     * so a malformed ticket # never silently saves.
     */
    public static function normalizeTicketNo(?string $raw): ?string
    {
        if (blank($raw)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw);
        $segments = config('travel.ticket_no_segments', [3, 4, 3, 3]);
        $expectedLength = array_sum($segments);

        if (strlen($digits) !== $expectedLength) {
            throw new \InvalidArgumentException("Ticket # must be exactly {$expectedLength} digits (format " . implode('-', array_fill(0, count($segments), '')) . ").");
        }

        $parts = [];
        $offset = 0;
        foreach ($segments as $len) {
            $parts[] = substr($digits, $offset, $len);
            $offset += $len;
        }

        return implode('-', $parts);
    }

    /** The first segment (airline numeric code) of a ticket #, digits only. */
    public static function airlineCodeFromTicketNo(?string $ticketNo): ?string
    {
        if (blank($ticketNo)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $ticketNo);
        $segments = config('travel.ticket_no_segments', [3, 4, 3, 3]);

        return strlen($digits) >= $segments[0] ? substr($digits, 0, $segments[0]) : null;
    }

    /**
     * Cities, formatted per the client's convention: "From-Stay-To" for a
     * one-way ticket, "From-Stay-To/From-Stay-To" for a return.
     */
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
     * Recompute every derived amount from the raw inputs (fare, tax, and
     * the four percentages). Called before every save so stored figures
     * never drift from what was actually typed in.
     *
     * Client fix: APT charges, PSF, WHT, and agent commission are no
     * longer typed in directly — each is now a percentage of some base
     * amount:
     *   apt_charges              = fare_amount * apt_percent / 100
     *   psf_amount                = psfBasisAmount * psf_percent / 100
     *   commission_amount          = fare_amount * commission_percent / 100   (airline commission)
     *   wht_amount                 = commission_amount * wht_percent / 100    (withholding on that commission)
     *   total_amount               = fare + tax + apt_charges + psf_amount − discount
     *   agent_commission_amount   = total_amount * agent_commission_percent / 100
     *
     * Order matters: apt_charges is computed before psf_amount (PSF can be
     * based on the fare+tax+APT subtotal); commission_amount is computed
     * before wht_amount (WHT is a % of the commission, not the fare); and
     * total_amount is computed before agent_commission_amount (which is a
     * % of the final total, not of the fare) — so nothing here is circular.
     */
    public function recalculate(): void
    {
        $fare = (float) $this->fare_amount;
        $tax = (float) $this->tax_amount;

        $this->apt_charges = round($fare * ((float) $this->apt_percent) / 100, 2);

        $psfBasisAmount = $this->psf_basis === self::PSF_BASIS_TOTAL
            ? round($fare + $tax + ((float) $this->apt_charges), 2)
            : $fare;
        $this->psf_amount = round($psfBasisAmount * ((float) $this->psf_percent) / 100, 2);

        $this->commission_amount = round($fare * ((float) $this->commission_percent) / 100, 2);
        $this->wht_amount = round(((float) $this->commission_amount) * ((float) $this->wht_percent) / 100, 2);

        $this->total_amount = round(
            $fare
            + $tax
            + ((float) $this->apt_charges)
            + ((float) $this->psf_amount)
            - ((float) $this->discount_amount),
            2
        );

        $this->agent_commission_amount = round(((float) $this->total_amount) * ((float) $this->agent_commission_percent) / 100, 2);
    }

    /**
     * What this ticket actually contributes to the customer's receivable
     * right now: the full total_amount while active, the retained
     * refund_profit once refunded, or the flat void_total_deduction once
     * voided (all other amounts on a voided ticket are treated as zero —
     * client feedback: "all amount of that ticket will be 0, only
     * deduction amount will be receivable from customer").
     */
    public function effectiveReceivable(): float
    {
        return match ($this->status) {
            self::STATUS_REFUNDED => (float) ($this->refund_profit ?? 0),
            self::STATUS_VOIDED => (float) ($this->void_total_deduction ?? 0),
            default => (float) $this->total_amount,
        };
    }

    /**
     * Net commission receivable from the airline (commission less WHT
     * withheld at source) — zero once a ticket is refunded or voided,
     * since the underlying airline transaction was reversed and no
     * commission is earned on it.
     */
    public function effectiveNetCommission(): float
    {
        if (!$this->isActive()) {
            return 0.0;
        }

        return round(((float) $this->commission_amount) - ((float) $this->wht_amount), 2);
    }

    /** Agent commission, same all-or-nothing rule as the airline commission above. */
    public function effectiveAgentCommission(): float
    {
        return $this->isActive() ? (float) $this->agent_commission_amount : 0.0;
    }

    /**
     * What this ticket contributes to "amount payable to the supplier" for
     * vendor/accounting reporting. There's no dedicated cost field on this
     * line (client feedback removed the old shared payable amount), so:
     * - active: approximated as fare + tax + apt charges + PSF — the raw
     *   cost components actually paid out to the supplier for the ticket.
     * - refunded: 0 — assumed the supplier fully unwinds their side of a
     *   refunded ticket; there's no separate supplier-refund tracking.
     * - voided: the explicit void_deduction_supplier — the one amount the
     *   supplier actually keeps once the ticket is voided.
     * This is a disclosed judgment call, not a figure the client specified.
     */
    public function effectiveSupplierPayable(): float
    {
        return match ($this->status) {
            self::STATUS_REFUNDED => 0.0,
            self::STATUS_VOIDED => (float) ($this->void_deduction_supplier ?? 0),
            default => round(
                ((float) $this->fare_amount)
                + ((float) $this->tax_amount)
                + ((float) $this->apt_charges)
                + ((float) $this->psf_amount),
                2
            ),
        };
    }
}
