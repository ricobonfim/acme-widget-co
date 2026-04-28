<?php

namespace App\Services\Offers;

interface Offer
{
    /**
     * Inspect the basket lines and return a discount descriptor if the offer
     * applies, or null when the conditions aren't met.
     *
     * @param  array<int, array{code: string, name: string, unit_price: int, quantity: int, line_total: int}>  $lines
     * @return array{code: string, label: string, amount: int, times_applied?: int}|null
     *         `amount`        is the discount in integer cents (positive number).
     *         `times_applied` is optional — how many times the offer fired
     *                         (e.g. number of BOGO pairs). Useful for the UI
     *                         to show "applied 3×". May be omitted for
     *                         all-or-nothing offers like a flat coupon.
     */
    public function applyTo(array $lines): ?array;
}
