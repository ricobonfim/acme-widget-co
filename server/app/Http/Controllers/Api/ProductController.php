<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * GET /api/products?q=...
     * Search products by name or code (case-insensitive partial match).
     * Returns up to 20 results. Empty `q` returns the first 20 products.
     */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $query = Product::query();

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%");
            });
        }

        $products = $query->orderBy('name')->limit(20)->get([
            'id', 'name', 'code', 'price',
        ]);

        return response()->json(['data' => $products]);
    }
}
