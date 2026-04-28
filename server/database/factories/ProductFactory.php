<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name'  => $this->faker->unique()->words(2, true),
            'code'  => strtoupper($this->faker->unique()->bothify('??##')),
            'price' => $this->faker->numberBetween(100, 10000),
        ];
    }
}
