<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BasketTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the three canonical widgets and return them keyed by code.
     */
    private function seedWidgets(): array
    {
        return [
            'R01' => Product::factory()->create(['name' => 'Red Widget',   'code' => 'R01', 'price' => 3295]),
            'G01' => Product::factory()->create(['name' => 'Green Widget', 'code' => 'G01', 'price' => 2495]),
            'B01' => Product::factory()->create(['name' => 'Blue Widget',  'code' => 'B01', 'price' => 795]),
        ];
    }

    /* ──────────────────────────────────────────────────────────────────── *
     *  Empty basket / show
     * ──────────────────────────────────────────────────────────────────── */

    public function test_empty_basket_returns_zero_totals(): void
    {
        $this->getJson('/api/basket')
            ->assertOk()
            ->assertExactJson([
                'items'          => [],
                'subtotal'       => 0,
                'discounts'      => [],
                'discount_total' => 0,
                'delivery'       => 0,
                'total'          => 0,
            ]);
    }

    /* ──────────────────────────────────────────────────────────────────── *
     *  Add
     * ──────────────────────────────────────────────────────────────────── */

    public function test_add_creates_a_line_with_quantity_one(): void
    {
        $this->seedWidgets();

        $this->postJson('/api/basket/items', ['code' => 'B01'])
            ->assertOk()
            ->assertJsonPath('items.0.code', 'B01')
            ->assertJsonPath('items.0.quantity', 1)
            ->assertJsonPath('items.0.line_total', 795)
            ->assertJsonPath('subtotal', 795);
    }

    public function test_add_increments_existing_line(): void
    {
        $this->seedWidgets();

        $this->postJson('/api/basket/items', ['code' => 'B01']);
        $this->postJson('/api/basket/items', ['code' => 'B01', 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('items.0.quantity', 3)
            ->assertJsonPath('items.0.line_total', 2385);
    }

    public function test_add_unknown_code_returns_404(): void
    {
        $this->seedWidgets();

        $this->postJson('/api/basket/items', ['code' => 'ZZZ'])
            ->assertNotFound();
    }

    public function test_add_validates_required_code(): void
    {
        $this->postJson('/api/basket/items', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_add_validates_quantity_minimum(): void
    {
        $this->seedWidgets();

        $this->postJson('/api/basket/items', ['code' => 'B01', 'quantity' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');
    }

    /* ──────────────────────────────────────────────────────────────────── *
     *  Update / remove / clear
     * ──────────────────────────────────────────────────────────────────── */

    public function test_update_sets_absolute_quantity(): void
    {
        $this->seedWidgets();
        $this->postJson('/api/basket/items', ['code' => 'B01', 'quantity' => 2]);

        $this->patchJson('/api/basket/items/B01', ['quantity' => 5])
            ->assertOk()
            ->assertJsonPath('items.0.quantity', 5);
    }

    public function test_update_with_zero_quantity_removes_the_line(): void
    {
        $this->seedWidgets();
        $this->postJson('/api/basket/items', ['code' => 'B01']);

        $this->patchJson('/api/basket/items/B01', ['quantity' => 0])
            ->assertOk()
            ->assertJsonPath('items', [])
            ->assertJsonPath('subtotal', 0);
    }

    public function test_remove_drops_a_line(): void
    {
        $this->seedWidgets();
        $this->postJson('/api/basket/items', ['code' => 'R01']);
        $this->postJson('/api/basket/items', ['code' => 'B01']);

        $this->deleteJson('/api/basket/items/R01')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.code', 'B01');
    }

    public function test_clear_empties_the_basket(): void
    {
        $this->seedWidgets();
        $this->postJson('/api/basket/items', ['code' => 'R01']);
        $this->postJson('/api/basket/items', ['code' => 'B01']);

        $this->deleteJson('/api/basket')
            ->assertOk()
            ->assertJsonPath('items', [])
            ->assertJsonPath('subtotal', 0)
            ->assertJsonPath('total', 0);
    }

    /* ──────────────────────────────────────────────────────────────────── *
     *  Integration: delivery + offers wired into snapshot
     * ──────────────────────────────────────────────────────────────────── */

    public function test_low_value_basket_charges_full_delivery(): void
    {
        $this->seedWidgets();

        // 1 × B01 = $7.95 → delivery $4.95 → total $12.90
        $this->postJson('/api/basket/items', ['code' => 'B01'])
            ->assertJsonPath('subtotal', 795)
            ->assertJsonPath('discount_total', 0)
            ->assertJsonPath('delivery', 495)
            ->assertJsonPath('total', 1290);
    }

    public function test_bogo_offer_applies_to_two_red_widgets(): void
    {
        $this->seedWidgets();

        $response = $this->postJson('/api/basket/items', ['code' => 'R01', 'quantity' => 2])
            ->assertOk();

        // subtotal = 6590, discount = 1648 (ceil(3295/2)), post-discount = 4942
        // → delivery 495 (under $50 tier), total = 4942 + 495 = 5437
        $response->assertJsonPath('subtotal', 6590)
                 ->assertJsonPath('discount_total', 1648)
                 ->assertJsonPath('delivery', 495)
                 ->assertJsonPath('total', 5437)
                 ->assertJsonPath('discounts.0.code', 'R01_BOGO_HALF');
    }

    public function test_delivery_tier_uses_post_discount_subtotal(): void
    {
        // This is the key acceptance: 2 × R01 = $65.90 raw, but after the
        // BOGO discount the basket is $49.43, which falls in the <$50 tier.
        $this->seedWidgets();

        $this->postJson('/api/basket/items', ['code' => 'R01', 'quantity' => 2])
            ->assertJsonPath('delivery', 495); // $4.95, not $2.95
    }

    public function test_high_value_basket_qualifies_for_free_delivery(): void
    {
        $this->seedWidgets();

        // 4 × R01 = $131.80, discount = 2 × ceil(3295/2) = $32.96,
        // post-discount = $98.84 → free delivery
        $this->postJson('/api/basket/items', ['code' => 'R01', 'quantity' => 4])
            ->assertJsonPath('subtotal', 13180)
            ->assertJsonPath('discount_total', 3296)
            ->assertJsonPath('delivery', 0)
            ->assertJsonPath('total', 9884);
    }

    public function test_basket_persists_across_requests_via_session(): void
    {
        $this->seedWidgets();

        $this->postJson('/api/basket/items', ['code' => 'R01']);
        $this->postJson('/api/basket/items', ['code' => 'B01']);

        $this->getJson('/api/basket')
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('subtotal', 4090);
    }
}
