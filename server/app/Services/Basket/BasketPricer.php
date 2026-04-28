<?php

namespace App\Services\Basket;

use App\Models\Product;
use App\Services\DeliveryService;
use App\Services\OfferService;

/**
 * Turns a raw [code => qty] map into a fully-priced basket snapshot.
 *
 * Pricing pipeline (each step is its own method):
 *   1. hydrateLines()     resolve products, build line items, sum subtotal
 *   2. applyOffers()      delegate to OfferService for discounts
 *   3. calculateDelivery() delegate to DeliveryService using post-discount sub
 *   4. assemble()         shape the final response array
 *
 * All money values are integer cents.
 */
class BasketPricer
{
    public function __construct(
        private OfferService $offers,
        private DeliveryService $delivery,
    ) {
    }

    /**
     * @param  array<string, int>  $items  Map of code → quantity.
     */
    public function priceFor(array $items): array
    {
        if (empty($items)) {
            return $this->emptySnapshot();
        }

        ['lines' => $lines, 'subtotal' => $subtotal] = $this->hydrateLines($items);

        $offerResult     = $this->applyOffers($lines);
        $discountedSub   = max(0, $subtotal - $offerResult['total']);
        $deliveryCost    = $this->calculateDelivery($discountedSub);

        return $this->assemble($lines, $subtotal, $offerResult, $deliveryCost);
    }

    /**
     * Resolve products from the DB and build line items in a single query.
     * Codes that no longer exist (e.g., product deleted) are silently skipped.
     *
     * @return array{lines: array, subtotal: int}
     */
    private function hydrateLines(array $items): array
    {
        $products = Product::whereIn('code', array_keys($items))->get()->keyBy('code');

        $lines    = [];
        $subtotal = 0;

        foreach ($items as $code => $qty) {
            if (! $products->has($code)) {
                continue;
            }

            $product   = $products[$code];
            $lineTotal = $product->price * $qty;
            $subtotal += $lineTotal;

            $lines[] = [
                'code'       => $product->code,
                'name'       => $product->name,
                'unit_price' => $product->price,
                'quantity'   => $qty,
                'line_total' => $lineTotal,
            ];
        }

        return ['lines' => $lines, 'subtotal' => $subtotal];
    }

    /**
     * @return array{discounts: array, total: int}
     */
    private function applyOffers(array $lines): array
    {
        return $this->offers->apply($lines);
    }

    private function calculateDelivery(int $discountedSubtotal): int
    {
        return $this->delivery->costFor($discountedSubtotal);
    }

    private function assemble(array $lines, int $subtotal, array $offerResult, int $delivery): array
    {
        $discountTotal = $offerResult['total'];
        $discountedSub = max(0, $subtotal - $discountTotal);

        return [
            'items'          => $lines,
            'subtotal'       => $subtotal,
            'discounts'      => $offerResult['discounts'],
            'discount_total' => $discountTotal,
            'delivery'       => $delivery,
            'total'          => $discountedSub + $delivery,
        ];
    }

    private function emptySnapshot(): array
    {
        return [
            'items'          => [],
            'subtotal'       => 0,
            'discounts'      => [],
            'discount_total' => 0,
            'delivery'       => 0,
            'total'          => 0,
        ];
    }
}
