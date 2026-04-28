<?php

namespace App\Services\Basket;

use Illuminate\Contracts\Session\Session as SessionContract;

/**
 * Persists the raw basket — a [code => quantity] map — in the user's session.
 *
 * No knowledge of products, pricing, or anything beyond storage. This is the
 * only class that reads/writes the session key.
 */
class BasketRepository
{
    private const SESSION_KEY = 'basket.items';

    public function __construct(private SessionContract $session)
    {
    }

    /**
     * @return array<string, int>  Map of product code → quantity.
     */
    public function all(): array
    {
        return $this->session->get(self::SESSION_KEY, []);
    }

    /**
     * Increment a code's quantity by `$qty` (creating the line if needed).
     * Quantities below 1 are clamped to 1 to keep the contract simple.
     */
    public function increment(string $code, int $qty = 1): void
    {
        $items = $this->all();
        $items[$code] = ($items[$code] ?? 0) + max(1, $qty);

        $this->save($items);
    }

    /**
     * Set an absolute quantity for a code, or remove the line if `$qty <= 0`.
     */
    public function set(string $code, int $qty): void
    {
        $items = $this->all();

        if ($qty <= 0) {
            unset($items[$code]);
        } else {
            $items[$code] = $qty;
        }

        $this->save($items);
    }

    public function remove(string $code): void
    {
        $items = $this->all();
        unset($items[$code]);

        $this->save($items);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
        $this->session->save();
    }

    private function save(array $items): void
    {
        $this->session->put(self::SESSION_KEY, $items);
        $this->session->save();
    }
}
