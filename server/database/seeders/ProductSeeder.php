<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $products = [
            ['name' => 'Red Widget',   'code' => 'R01', 'price' => 3295],
            ['name' => 'Green Widget', 'code' => 'G01', 'price' => 2495],
            ['name' => 'Blue Widget',  'code' => 'B01', 'price' => 795],
        ];

        foreach ($products as $product) {
            DB::table('products')->updateOrInsert(
                ['code' => $product['code']],
                array_merge($product, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }
}
