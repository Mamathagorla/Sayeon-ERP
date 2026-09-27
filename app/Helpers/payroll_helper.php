<?php

if (! function_exists('amount_in_words')) {
    /**
     * Whole-rupee amount spelled out using the Indian numbering system
     * (Lakh/Crore, not Million/Billion) — used on the printable payslip.
     * Paise are dropped (payslip amounts are already whole rupees in
     * practice); negative/zero amounts return 'Zero Rupees Only'.
     */
    function amount_in_words(float $amount): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $twoDigits = static function (int $n) use ($ones, $tens): string {
            if ($n < 20) {
                return $ones[$n];
            }

            return trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
        };

        $threeDigits = static function (int $n) use ($twoDigits, $ones): string {
            $parts = [];
            if ($n >= 100) {
                $parts[] = $ones[intdiv($n, 100)] . ' Hundred';
                $n %= 100;
            }
            if ($n > 0) {
                $parts[] = $twoDigits($n);
            }

            return implode(' ', $parts);
        };

        $n = (int) round(max(0, $amount));

        if ($n === 0) {
            return 'Zero Rupees Only';
        }

        $crore    = intdiv($n, 10000000);
        $lakh     = intdiv($n % 10000000, 100000);
        $thousand = intdiv($n % 100000, 1000);
        $hundred  = $n % 1000;

        $parts = [];
        if ($crore > 0) {
            $parts[] = $threeDigits($crore) . ' Crore';
        }
        if ($lakh > 0) {
            $parts[] = $threeDigits($lakh) . ' Lakh';
        }
        if ($thousand > 0) {
            $parts[] = $threeDigits($thousand) . ' Thousand';
        }
        if ($hundred > 0) {
            $parts[] = $threeDigits($hundred);
        }

        return implode(' ', $parts) . ' Rupees Only';
    }
}
