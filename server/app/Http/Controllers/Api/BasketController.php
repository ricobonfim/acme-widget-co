<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BasketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BasketController extends Controller
{
    public function __construct(private BasketService $basket)
    {
    }

    /**
     * GET /api/basket
     */
    public function show(): JsonResponse
    {
        return response()->json($this->basket->snapshot());
    }

    /**
     * POST /api/basket/items   { code: string, quantity?: int }
     * Adds a product to the basket (increments quantity if already present).
     */
    public function add(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'     => ['required', 'string'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
        ]);

        $snapshot = $this->basket->add($data['code'], $data['quantity'] ?? 1);

        return response()->json($snapshot);
    }

    /**
     * PATCH /api/basket/items/{code}   { quantity: int }
     * Sets the quantity for a basket line. Removes the line when quantity <= 0.
     */
    public function update(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        return response()->json(
            $this->basket->setQuantity($code, $data['quantity'])
        );
    }

    /**
     * DELETE /api/basket/items/{code}
     */
    public function remove(string $code): JsonResponse
    {
        return response()->json($this->basket->remove($code));
    }

    /**
     * DELETE /api/basket
     */
    public function clear(): JsonResponse
    {
        return response()->json($this->basket->clear());
    }
}
