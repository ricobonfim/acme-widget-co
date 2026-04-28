<?php

namespace App\Services\Offers;

interface Offer
{
    /**
     * Inspect the basket lines and return a discount descriptor if the offer
     * applies, or null when the conditions aren't met.
     *
     * @param  array<int, array{code: string, name: string, unit_price: int, quantity: int, line_total: int}>  $lines
     * @return array{code: string, label: string, amount: int}|null
     *         `amount` is the discount in integer cents (positive number).
     */
    public function applyTo(array $lines): ?array;
}
