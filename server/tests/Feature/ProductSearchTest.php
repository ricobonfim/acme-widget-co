<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_all_products_when_query_is_empty(): void
    {
        Product::factory()->count(3)->create();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_filters_by_partial_name_match(): void
    {
        Product::factory()->create(['name' => 'Red Widget',   'code' => 'R01', 'price' => 3295]);
        Product::factory()->create(['name' => 'Green Widget', 'code' => 'G01', 'price' => 2495]);
        Product::factory()->create(['name' => 'Blue Sticker', 'code' => 'B02', 'price' => 100]);

        $response = $this->getJson('/api/products?q=widget')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertEqualsCanonicalizing(['R01', 'G01'], $codes);
    }

    public function test_filters_by_partial_code_match(): void
    {
        Product::factory()->create(['name' => 'Red Widget',   'code' => 'R01']);
        Product::factory()->create(['name' => 'Green Widget', 'code' => 'G01']);

        $this->getJson('/api/products?q=R0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'R01');
    }

    public function test_search_is_case_insensitive(): void
    {
        Product::factory()->create(['name' => 'Red Widget', 'code' => 'R01']);

        $this->getJson('/api/products?q=RED')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_returns_empty_collection_when_nothing_matches(): void
    {
        Product::factory()->create(['name' => 'Red Widget', 'code' => 'R01']);

        $this->getJson('/api/products?q=zzznope')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_caps_results_at_twenty(): void
    {
        Product::factory()->count(25)->create();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(20, 'data');
    }
}
