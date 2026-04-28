<?php

namespace Tests\Unit\Basket;

use App\Models\Product;
use App\Services\Basket\BasketPricer;
use App\Services\DeliveryService;
use App\Services\OfferService;
use App\Services\Offers\RedWidgetBogoHalfPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BasketPricerTest extends TestCase
{
    use RefreshDatabase;

    private BasketPricer $pricer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricer = new BasketPricer(
            new OfferService([new RedWidgetBogoHalfPrice()]),
            new DeliveryService(),
        );

        Product::factory()->create(['name' => 'Red Widget',   'code' => 'R01', 'price' => 3295]);
        Product::factory()->create(['name' => 'Green Widget', 'code' => 'G01', 'price' => 2495]);
        Product::factory()->create(['name' => 'Blue Widget',  'code' => 'B01', 'price' => 795]);
    }

    public function test_empty_input_returns_empty_snapshot(): void
    {
        $this->assertSame(
            [
                'items'          => [],
                'subtotal'       => 0,
                'discounts'      => [],
                'discount_total' => 0,
                'delivery'       => 0,
                'total'          => 0,
            ],
            $this->pricer->priceFor([]),
        );
    }

    public function test_single_item_includes_full_delivery(): void
    {
        $snapshot = $this->pricer->priceFor(['B01' => 1]);

        $this->assertCount(1, $snapshot['items']);
        $this->assertSame(795, $snapshot['subtotal']);
        $this->assertSame(0, $snapshot['discount_total']);
        $this->assertSame(495, $snapshot['delivery']);
        $this->assertSame(1290, $snapshot['total']);
    }

    public function test_unknown_codes_are_silently_skipped(): void
    {
        // Defensive: a code lingering in the session for a deleted product
        // should not break the response.
        $snapshot = $this->pricer->priceFor(['R01' => 1, 'GHOST' => 5]);

        $this->assertCount(1, $snapshot['items']);
        $this->assertSame('R01', $snapshot['items'][0]['code']);
    }

    public function test_offer_is_applied_before_delivery_tier(): void
    {
        // 2 × R01 = 6590 raw → discount 1648 (ceil) → discounted 4942 → <$50 tier (495).
        $snapshot = $this->pricer->priceFor(['R01' => 2]);

        $this->assertSame(6590, $snapshot['subtotal']);
        $this->assertSame(1648, $snapshot['discount_total']);
        $this->assertSame(495, $snapshot['delivery']);
        $this->assertSame(5437, $snapshot['total']);
    }

    public function test_high_value_basket_gets_free_delivery(): void
    {
        // 4 × R01 = 13180 raw → discount 3296 (2×ceil) → discounted 9884 → free delivery.
        $snapshot = $this->pricer->priceFor(['R01' => 4]);

        $this->assertSame(0, $snapshot['delivery']);
        $this->assertSame(9884, $snapshot['total']);
    }
}
