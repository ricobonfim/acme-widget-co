<?php

namespace Tests\Unit;

use App\Services\DeliveryService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DeliveryServiceTest extends TestCase
{
    private DeliveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DeliveryService();
    }

    public static function tierProvider(): array
    {
        return [
            'empty basket'                => [0,     0],
            'just over 0'                 => [1,     495],
            'just under $50'              => [4999,  495],
            'exactly $50 lower boundary'  => [5000,  295],
            'between $50 and $90'         => [7500,  295],
            'just under $90'              => [8999,  295],
            'exactly $90 free threshold'  => [9000,  0],
            'well above $90'              => [25000, 0],
        ];
    }

    #[DataProvider('tierProvider')]
    public function test_returns_correct_cost_for_each_tier(int $subtotal, int $expectedCost): void
    {
        $this->assertSame($expectedCost, $this->service->costFor($subtotal));
    }

    public function test_negative_subtotal_costs_nothing(): void
    {
        // Defensive: should never happen, but make sure we don't charge anyone.
        $this->assertSame(0, $this->service->costFor(-100));
    }
}
