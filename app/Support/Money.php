<?php

namespace App\Support;

/**
 * One colour rule for every screen, report, invoice, print and statement
 * (client rule, Nov 2026):
 *   - an amount WE HAVE TO PAY      → shown negative, in red    (-1,250.00)
 *   - an amount WE HAVE TO RECEIVE  → shown positive, in green  (1,250.00)
 *
 * receivable($v): $v > 0 means they owe us (green); $v < 0 means we owe them (red, minus).
 * payable($v):    $v > 0 means we owe them (red, minus); $v < 0 means they owe us (green).
 * Zero prints plain. Output is HTML (inline styles, so it also works in TCPDF and prints);
 * use {!! !!} in Blade.
 */
class Money
{
    public const RED = '#c0392b';
    public const GREEN = '#1e8449';

    /** A figure from the "they owe us" side (customer due, receivable, outstanding to collect). */
    public static function receivable($value, int $decimals = 2): string
    {
        $v = round((float) $value, $decimals);
        if (abs($v) < 0.5 / (10 ** $decimals)) {
            return number_format(0, $decimals);
        }

        return $v > 0 ? self::green(number_format($v, $decimals)) : self::red('-' . number_format(abs($v), $decimals));
    }

    /** A figure from the "we owe them" side (supplier/airline payable, cost to pay). */
    public static function payable($value, int $decimals = 2): string
    {
        return self::receivable(-(float) $value, $decimals);
    }

    /**
     * A ledger balance where debit is positive (Σ Dr − Σ Cr) on a party
     * account: debit = they owe us (green), credit = we owe them (red, minus).
     */
    public static function partyBalance($debitMinusCredit, int $decimals = 2): string
    {
        return self::receivable($debitMinusCredit, $decimals);
    }

    /** receivable() or payable() by name — 'receivable' | 'payable'. */
    public static function side(string $side, $value, int $decimals = 2): string
    {
        return $side === 'payable' ? self::payable($value, $decimals) : self::receivable($value, $decimals);
    }

    public static function green(string $text): string
    {
        return '<span style="color:' . self::GREEN . ';font-weight:bold;">' . $text . '</span>';
    }

    public static function red(string $text): string
    {
        return '<span style="color:' . self::RED . ';font-weight:bold;">' . $text . '</span>';
    }
}
