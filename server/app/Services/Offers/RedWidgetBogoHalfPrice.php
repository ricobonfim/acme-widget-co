<?php

namespace App\Services\Offers;

/**
 * "Buy one Red Widget, get the second half price."
 *
 * For every pair of R01 in the basket, the second one is sold at half its
 * unit price. Odd-priced products round in the customer's favor (intdiv).
 */
class RedWidgetBogoHalfPrice implements Offer
{
    private const CODE        = 'R01_BOGO_HALF';
    private const LABEL       = 'Red Widget: 2nd half price';
    private const TARGET_CODE = 'R01';

    public function applyTo(array $lines): ?array
    {
        $line = null;
        foreach ($lines as $candidate) {
            if ($candidate['code'] === self::TARGET_CODE) {
                $line = $candidate;
                break;
            }
        }

        if ($line === null) {
            return null;
        }

        $pairs = intdiv($line['quantity'], 2);
        if ($pairs <= 0) {
            return null;
        }

        $perPair = intdiv($line['unit_price'], 2);
        $amount  = $pairs * $perPair;

        if ($amount <= 0) {
            return null;
        }

        return [
            'code'   => self::CODE,
            'label'  => self::LABEL,
            'amount' => $amount,
        ];
    }
}
