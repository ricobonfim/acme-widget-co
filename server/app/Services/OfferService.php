<?php

namespace App\Services;

use App\Services\Offers\Offer;

class OfferService
{
    /**
     * @param  iterable<Offer>  $offers  Active offers, registered in the
     *         service container. Order matters only if offers can interact;
     *         the current rule set is independent.
     */
    public function __construct(private iterable $offers = [])
    {
    }

    /**
     * Run every registered offer against the basket and collect discounts.
     *
     * @param  array  $lines  Basket lines: [{code, name, unit_price, quantity, line_total}, ...]
     * @return array{discounts: array<int, array{code: string, label: string, amount: int}>, total: int}
     *         `total` is the sum of all discount amounts (cents, positive).
     */
    public function apply(array $lines): array
    {
        $discounts = [];

        foreach ($this->offers as $offer) {
            $discount = $offer->applyTo($lines);
            if ($discount !== null) {
                $discounts[] = $discount;
            }
        }

        $total = array_sum(array_column($discounts, 'amount'));

        return [
            'discounts' => $discounts,
            'total'     => $total,
        ];
    }
}
