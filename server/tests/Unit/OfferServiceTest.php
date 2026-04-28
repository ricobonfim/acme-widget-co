<?php

namespace Tests\Unit;

use App\Services\OfferService;
use App\Services\Offers\Offer;
use PHPUnit\Framework\TestCase;

class OfferServiceTest extends TestCase
{
    public function test_returns_empty_result_when_no_offers_are_registered(): void
    {
        $service = new OfferService([]);

        $result = $service->apply([
            ['code' => 'X1', 'name' => 'X', 'unit_price' => 100, 'quantity' => 1, 'line_total' => 100],
        ]);

        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['discounts']);
    }

    public function test_collects_discounts_from_every_applicable_offer(): void
    {
        $offerA = $this->fakeOffer(['code' => 'A', 'label' => 'A', 'amount' => 100]);
        $offerB = $this->fakeOffer(['code' => 'B', 'label' => 'B', 'amount' => 250]);

        $service = new OfferService([$offerA, $offerB]);

        $result = $service->apply([]);

        $this->assertSame(350, $result['total']);
        $this->assertCount(2, $result['discounts']);
        $this->assertSame(['A', 'B'], array_column($result['discounts'], 'code'));
    }

    public function test_skips_offers_that_return_null(): void
    {
        $applies      = $this->fakeOffer(['code' => 'A', 'label' => 'A', 'amount' => 100]);
        $doesNotApply = $this->fakeOffer(null);

        $service = new OfferService([$applies, $doesNotApply]);

        $result = $service->apply([]);

        $this->assertSame(100, $result['total']);
        $this->assertCount(1, $result['discounts']);
        $this->assertSame('A', $result['discounts'][0]['code']);
    }

    public function test_passes_lines_through_to_each_offer(): void
    {
        $lines = [
            ['code' => 'X1', 'name' => 'X', 'unit_price' => 100, 'quantity' => 1, 'line_total' => 100],
        ];

        $captured = null;
        $spy = new class($captured) implements Offer {
            public function __construct(private &$captured) {}
            public function applyTo(array $lines): ?array
            {
                $this->captured = $lines;
                return null;
            }
        };

        (new OfferService([$spy]))->apply($lines);

        $this->assertSame($lines, $captured);
    }

    /**
     * Build an anonymous Offer that always returns the given discount
     * descriptor (or null when not applicable).
     */
    private function fakeOffer(?array $discount): Offer
    {
        return new class($discount) implements Offer {
            public function __construct(private ?array $discount) {}
            public function applyTo(array $lines): ?array
            {
                return $this->discount;
            }
        };
    }
}
