<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Session;

class BasketService
{
    private const SESSION_KEY = 'basket.items'; // [code => qty]

    public function __construct(private DeliveryService $delivery)
    {
    }

    /**
     * Add a product (by code) to the basket. Increments qty if it already exists.
     */
    public function add(string $code, int $qty = 1): array
    {
        $product = Product::where('code', $code)->firstOrFail();

        $items = Session::get(self::SESSION_KEY, []);
        $items[$product->code] = ($items[$product->code] ?? 0) + max(1, $qty);

        Session::put(self::SESSION_KEY, $items);
        Session::save();

        return $this->snapshot();
    }

    /**
     * Set the quantity for a product. Removes the line if qty <= 0.
     */
    public function setQuantity(string $code, int $qty): array
    {
        $items = Session::get(self::SESSION_KEY, []);

        if ($qty <= 0) {
            unset($items[$code]);
        } else {
            // Make sure the product exists before storing it
            Product::where('code', $code)->firstOrFail();
            $items[$code] = $qty;
        }

        Session::put(self::SESSION_KEY, $items);
        Session::save();

        return $this->snapshot();
    }

    /**
     * Remove a single line from the basket.
     */
    public function remove(string $code): array
    {
        $items = Session::get(self::SESSION_KEY, []);
        unset($items[$code]);

        Session::put(self::SESSION_KEY, $items);
        Session::save();

        return $this->snapshot();
    }

    /**
     * Clear the entire basket.
     */
    public function clear(): array
    {
        Session::forget(self::SESSION_KEY);
        Session::save();

        return $this->snapshot();
    }

    /**
     * Build the current basket payload, hydrating product info and computing totals.
     * All money values are returned as integer cents.
     */
    public function snapshot(): array
    {
        $items = Session::get(self::SESSION_KEY, []);

        if (empty($items)) {
            return [
                'items'    => [],
                'subtotal' => 0,
                'delivery' => 0,
                'total'    => 0,
            ];
        }

        $products = Product::whereIn('code', array_keys($items))->get()->keyBy('code');

        $lines = [];
        $subtotal = 0;

        foreach ($items as $code => $qty) {
            if (! $products->has($code)) {
                continue; // product was removed in DB; skip silently
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

        $delivery = $this->delivery->costFor($subtotal);

        return [
            'items'    => $lines,
            'subtotal' => $subtotal,
            'delivery' => $delivery,
            'total'    => $subtotal + $delivery, // tax rules will be applied later
        ];
    }
}
