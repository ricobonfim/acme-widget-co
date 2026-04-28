<?php

namespace App\Services\Offers;

/**
 * "Buy one Red Widget, get the second half price."
 *
 * For every pair of R01 in the basket, the second one is sold at half its
 * unit price. When the unit price is odd (e.g. 3295¢), we round the
 * discount UP so the customer pays slightly less — i.e. the discount per
 * pair is ceil(unit_price / 2).
 *
 * Example: 2 × R01 @ 3295¢
 *   subtotal      = 6590¢
 *   discount/pair = ceil(3295/2) = 1648¢
 *   times_applied = 1
 *   amount        = 1648¢ → net 4942¢ ($49.42)
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

        // ceil(n/2) for non-negative ints, no floats: (n + 1) intdiv 2.
        $perPair = intdiv($line['unit_price'] + 1, 2);
        $amount  = $pairs * $perPair;

        if ($amount <= 0) {
            return null;
        }

        return [
            'code'          => self::CODE,
            'label'         => self::LABEL,
            'amount'        => $amount,
            'times_applied' => $pairs,
        ];
    }
}
