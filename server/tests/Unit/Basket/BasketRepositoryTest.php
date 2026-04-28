<?php

namespace Tests\Unit\Basket;

use App\Services\Basket\BasketRepository;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\TestCase;

class BasketRepositoryTest extends TestCase
{
    private BasketRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a real Session store backed by an in-memory array so the test
        // exercises the same contract Laravel injects in production.
        $session = new Store('test', new ArraySessionHandler(60));
        $this->repo = new BasketRepository($session);
    }

    public function test_starts_empty(): void
    {
        $this->assertSame([], $this->repo->all());
    }

    public function test_increment_creates_and_grows_a_line(): void
    {
        $this->repo->increment('R01');
        $this->repo->increment('R01', 2);

        $this->assertSame(['R01' => 3], $this->repo->all());
    }

    public function test_increment_clamps_non_positive_quantities_to_one(): void
    {
        $this->repo->increment('R01', 0);
        $this->repo->increment('R01', -5);

        $this->assertSame(['R01' => 2], $this->repo->all());
    }

    public function test_set_replaces_the_quantity(): void
    {
        $this->repo->increment('R01', 3);
        $this->repo->set('R01', 7);

        $this->assertSame(['R01' => 7], $this->repo->all());
    }

    public function test_set_with_zero_or_less_removes_the_line(): void
    {
        $this->repo->increment('R01', 3);
        $this->repo->set('R01', 0);

        $this->assertSame([], $this->repo->all());
    }

    public function test_remove_drops_a_specific_line(): void
    {
        $this->repo->increment('R01');
        $this->repo->increment('B01');

        $this->repo->remove('R01');

        $this->assertSame(['B01' => 1], $this->repo->all());
    }

    public function test_clear_empties_everything(): void
    {
        $this->repo->increment('R01');
        $this->repo->increment('B01', 2);

        $this->repo->clear();

        $this->assertSame([], $this->repo->all());
    }
}
