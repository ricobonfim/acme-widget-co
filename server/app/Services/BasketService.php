<?php

namespace App\Services;

use App\Models\Product;
use App\Services\Basket\BasketPricer;
use App\Services\Basket\BasketRepository;

/**
 * Coordinates basket mutations: validates the action, persists the change
 * via {@see BasketRepository}, and returns a fresh priced snapshot via
 * {@see BasketPricer}.
 *
 * This class deliberately holds no business logic — pricing rules live in
 * dedicated services, persistence lives in the repository.
 */
class BasketService
{
    public function __construct(
        private BasketRepository $repo,
        private BasketPricer $pricer,
    ) {
    }

    /**
     * Add a product (by code) to the basket. Increments quantity if it
     * already exists. Throws ModelNotFoundException for unknown codes.
     */
    public function add(string $code, int $qty = 1): array
    {
        $this->ensureProductExists($code);
        $this->repo->increment($code, $qty);

        return $this->snapshot();
    }

    /**
     * Set the absolute quantity for a product. Removes the line when qty <= 0.
     */
    public function setQuantity(string $code, int $qty): array
    {
        if ($qty > 0) {
            $this->ensureProductExists($code);
        }
        $this->repo->set($code, $qty);

        return $this->snapshot();
    }

    public function remove(string $code): array
    {
        $this->repo->remove($code);

        return $this->snapshot();
    }

    public function clear(): array
    {
        $this->repo->clear();

        return $this->snapshot();
    }

    /**
     * Build the current basket payload. All money values are integer cents.
     */
    public function snapshot(): array
    {
        return $this->pricer->priceFor($this->repo->all());
    }

    /**
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    private function ensureProductExists(string $code): void
    {
        Product::where('code', $code)->firstOrFail();
    }
}
