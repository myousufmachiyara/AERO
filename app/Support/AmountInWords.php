<?php

namespace App\Support;

/** "Rupees Ten Thousand Four Hundred Forty and Fifty Paisa Only" — for invoice and receipt prints. */
class AmountInWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    private const SCALES = [1000000000 => 'Billion', 1000000 => 'Million', 1000 => 'Thousand'];

    public static function rupees(float $amount): string
    {
        $amount = round(abs($amount), 2);
        $whole = (int) floor($amount);
        $paisa = (int) round(($amount - $whole) * 100);

        $words = 'Rupees ' . ($whole === 0 ? 'Zero' : self::words($whole));
        if ($paisa > 0) {
            $words .= ' and ' . self::words($paisa) . ' Paisa';
        }

        return $words . ' Only';
    }

    public static function words(int $n): string
    {
        $parts = [];
        foreach (self::SCALES as $value => $name) {
            if ($n >= $value) {
                $parts[] = self::belowThousand(intdiv($n, $value)) . ' ' . $name;
                $n %= $value;
            }
        }
        if ($n > 0) {
            $parts[] = self::belowThousand($n);
        }

        return implode(' ', $parts);
    }

    private static function belowThousand(int $n): string
    {
        $out = [];
        if ($n >= 100) {
            $out[] = self::ONES[intdiv($n, 100)] . ' Hundred';
            $n %= 100;
        }
        if ($n >= 20) {
            $out[] = self::TENS[intdiv($n, 10)] . ($n % 10 ? ' ' . self::ONES[$n % 10] : '');
        } elseif ($n > 0) {
            $out[] = self::ONES[$n];
        }

        return implode(' ', $out);
    }
}
