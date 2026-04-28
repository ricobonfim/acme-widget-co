<?php

namespace Tests\Unit;

use App\Services\OfferService;
use PHPUnit\Framework\TestCase;

class OfferServiceTest extends TestCase
{
    private OfferService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OfferService();
    }

    /**
     * Helper to keep test cases compact and readable.
     */
    private function line(string $code, int $unitPrice, int $qty): array
    {
        return [
            'code'       => $code,
            'name'       => $code,
            'unit_price' => $unitPrice,
            'quantity'   => $qty,
            'line_total' => $unitPrice * $qty,
        ];
    }

    public function test_no_red_widget_means_no_discount(): void
    {
        $result = $this->service->apply([
            $this->line('B01', 795, 3),
            $this->line('G01', 2495, 1),
        ]);

        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['discounts']);
    }

    public function test_single_red_widget_does_not_trigger_offer(): void
    {
        $result = $this->service->apply([$this->line('R01', 3295, 1)]);

        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['discounts']);
    }

    public function test_two_red_widgets_discount_one_at_half_price(): void
    {
        $result = $this->service->apply([$this->line('R01', 3295, 2)]);

        // 3295 / 2 = 1647 (integer division, rounds in customer's favor)
        $this->assertSame(1647, $result['total']);
        $this->assertCount(1, $result['discounts']);
        $this->assertSame('R01_BOGO_HALF', $result['discounts'][0]['code']);
        $this->assertSame(1647, $result['discounts'][0]['amount']);
    }

    public function test_three_red_widgets_form_only_one_pair(): void
    {
        // 3 → 1 pair → 1 × 1647
        $result = $this->service->apply([$this->line('R01', 3295, 3)]);

        $this->assertSame(1647, $result['total']);
    }

    public function test_four_red_widgets_form_two_pairs(): void
    {
        // 4 → 2 pairs → 2 × 1647 = 3294
        $result = $this->service->apply([$this->line('R01', 3295, 4)]);

        $this->assertSame(3294, $result['total']);
    }

    public function test_other_products_alongside_red_widget_are_unaffected(): void
    {
        $result = $this->service->apply([
            $this->line('R01', 3295, 2),
            $this->line('B01', 795, 5),
            $this->line('G01', 2495, 1),
        ]);

        $this->assertSame(1647, $result['total']);
        $this->assertCount(1, $result['discounts']);
    }

    public function test_odd_unit_price_rounds_in_customers_favor(): void
    {
        // Unit price 99 → half is 49.5 → intdiv keeps 49 (cheaper for the customer)
        $result = $this->service->apply([$this->line('R01', 99, 2)]);

        $this->assertSame(49, $result['total']);
    }
}
