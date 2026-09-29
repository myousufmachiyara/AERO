<?php

namespace App\Support;

/**
 * Spells out a whole number in English words, Western scale
 * (Thousand/Million/Billion — not Lakh/Crore), e.g. 186200 -> "One Hundred
 * Eighty Six Thousand Two Hundred". No currency name or "Only" suffix is
 * added; callers that want either wrap the result themselves.
 *
 * Matches the exact wording on the AERO sample invoice (186,200.00 ->
 * "One Hundred Eighty Six Thousand Two Hundred") for the range it was
 * verified against. If your invoices are meant to read in Lakh/Crore
 * instead, say so and this can be swapped.
 */
class NumberWords
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    /** Amount-in-words for a money value: rounds to whole currency units, drops any fraction. */
    public static function forAmount(float $amount): string
    {
        $number = (int) round($amount);

        return $number <= 0 ? 'Zero' : self::spell($number);
    }

    public static function spell(int $number): string
    {
        if ($number === 0) {
            return '';
        }

        if ($number < 0) {
            return 'Negative ' . self::spell(-$number);
        }

        if ($number < 20) {
            return self::ONES[$number];
        }

        if ($number < 100) {
            return trim(self::TENS[intdiv($number, 10)] . ' ' . self::ONES[$number % 10]);
        }

        if ($number < 1000) {
            return trim(self::ONES[intdiv($number, 100)] . ' Hundred ' . self::spell($number % 100));
        }

        if ($number < 1_000_000) {
            return trim(self::spell(intdiv($number, 1000)) . ' Thousand ' . self::spell($number % 1000));
        }

        if ($number < 1_000_000_000) {
            return trim(self::spell(intdiv($number, 1_000_000)) . ' Million ' . self::spell($number % 1_000_000));
        }

        return trim(self::spell(intdiv($number, 1_000_000_000)) . ' Billion ' . self::spell($number % 1_000_000_000));
    }
}
