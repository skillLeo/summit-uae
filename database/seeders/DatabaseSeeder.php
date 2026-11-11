<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Approved DOCX copy (database/content/*.json from `php artisan summit:import`).
        $this->call(ContentSeeder::class);
    }
}
