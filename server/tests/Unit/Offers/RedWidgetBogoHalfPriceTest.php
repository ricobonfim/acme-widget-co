<?php

namespace Tests\Unit\Offers;

use App\Services\Offers\RedWidgetBogoHalfPrice;
use PHPUnit\Framework\TestCase;

class RedWidgetBogoHalfPriceTest extends TestCase
{
    private RedWidgetBogoHalfPrice $offer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->offer = new RedWidgetBogoHalfPrice();
    }

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

    public function test_returns_null_when_red_widget_is_absent(): void
    {
        $this->assertNull($this->offer->applyTo([
            $this->line('B01', 795, 3),
            $this->line('G01', 2495, 1),
        ]));
    }

    public function test_returns_null_for_a_single_red_widget(): void
    {
        $this->assertNull($this->offer->applyTo([$this->line('R01', 3295, 1)]));
    }

    public function test_two_red_widgets_discount_the_second_at_half_price(): void
    {
        $discount = $this->offer->applyTo([$this->line('R01', 3295, 2)]);

        $this->assertNotNull($discount);
        $this->assertSame('R01_BOGO_HALF', $discount['code']);
        $this->assertSame(1647, $discount['amount']); // intdiv(3295, 2)
    }

    public function test_three_red_widgets_form_only_one_pair(): void
    {
        $discount = $this->offer->applyTo([$this->line('R01', 3295, 3)]);

        $this->assertSame(1647, $discount['amount']);
    }

    public function test_four_red_widgets_form_two_pairs(): void
    {
        $discount = $this->offer->applyTo([$this->line('R01', 3295, 4)]);

        $this->assertSame(3294, $discount['amount']);
    }

    public function test_other_products_alongside_red_widget_are_ignored(): void
    {
        $discount = $this->offer->applyTo([
            $this->line('R01', 3295, 2),
            $this->line('B01', 795, 5),
            $this->line('G01', 2495, 1),
        ]);

        $this->assertSame(1647, $discount['amount']);
    }

    public function test_odd_unit_price_rounds_in_customers_favor(): void
    {
        // Unit price 99 → half is 49.5 → intdiv keeps 49 (cheaper for the customer).
        $discount = $this->offer->applyTo([$this->line('R01', 99, 2)]);

        $this->assertSame(49, $discount['amount']);
    }
}
