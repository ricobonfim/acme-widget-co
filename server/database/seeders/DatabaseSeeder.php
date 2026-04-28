<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Acme Widget Co only needs the catalog. There are no user accounts
     * or auth flow — the basket lives in an anonymous session.
     */
    public function run(): void
    {
        $this->call([
            ProductSeeder::class,
        ]);
    }
}
