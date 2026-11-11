<?php

namespace Database\Seeders;

use App\Content\Cms\ContentImporter;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Fills the CMS tables from today's sources: database/content/*.json (written by `php artisan summit:import`), the lang
 * files and config. The same as `php artisan summit:content-import` (App\Content\Cms\ContentImporter, idempotent).
 */
final class ContentSeeder extends Seeder
{
    public function run(): void
    {
        if (! is_file(database_path('content/pages.json'))) {
            throw new RuntimeException('database/content is empty. Run `php artisan summit:import` first.');
        }

        app(ContentImporter::class)->run();
    }
}
