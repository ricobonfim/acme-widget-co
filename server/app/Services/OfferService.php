<?php

namespace App\Services;

class OfferService
{
    /**
     * Apply all active offers to the given basket lines.
     *
     * @param  array  $lines  Basket lines: [{code, name, unit_price, quantity, line_total}, ...]
     * @return array{discounts: array<int, array{code: string, label: string, amount: int}>, total: int}
     *         `total` is the sum of all discount amounts (in cents, positive number).
     */
    public function apply(array $lines): array
    {
        $byCode = [];
        foreach ($lines as $line) {
            $byCode[$line['code']] = $line;
        }

        $discounts = [];

        // ── Offer: Buy one Red Widget, get the second half price ────────────
        // For every pair of R01 in the basket, the second one is 50% off.
        if (isset($byCode['R01'])) {
            $r01      = $byCode['R01'];
            $pairs    = intdiv($r01['quantity'], 2);
            if ($pairs > 0) {
                // Half off the unit price, per pair. Use intdiv to keep integer cents;
                // odd unit prices round in the customer's favor (cheaper).
                $perPair = intdiv($r01['unit_price'], 2);
                $amount  = $pairs * $perPair;

                $discounts[] = [
                    'code'   => 'R01_BOGO_HALF',
                    'label'  => 'Red Widget: 2nd half price',
                    'amount' => $amount,
                ];
            }
        }

        $total = array_sum(array_column($discounts, 'amount'));

        return [
            'discounts' => $discounts,
            'total'     => $total,
        ];
    }
}
