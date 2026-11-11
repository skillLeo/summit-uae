<?php

namespace App\Console\Commands;

use App\Content\Cms\ContentImporter;
use App\Content\Import\ImportException;
use Illuminate\Console\Command;

/**
 * Fills the CMS tables from today's sources (docs/briefs/design-2.1-and-admin-cms.md §8.8 step 2; App\Content\Cms\Sources):
 * pages and blocks, every collection, situations, document samples, navigation with its link keys, interface text
 * with copy_source, settings (contacts, office hours, WA01 drafts), SEO titles and descriptions, calculator rule sets.
 *
 *   php artisan summit:content-import          idempotent: creates what is missing, updates what nobody edited
 *   php artisan summit:content-import --fresh  rebuilds every CMS content table and the settings from the sources
 *
 * Run `php artisan summit:import` first when the DOCX files changed (it writes database/content/*.json).
 */
final class ContentImportCommand extends Command
{
    protected $signature = 'summit:content-import
        {--fresh : Rebuild every CMS content table and the settings from the sources (users, enquiries and revisions are kept)}';

    protected $description = 'Import today\'s content sources (DOCX import, lang files, config) into the CMS tables';

    public function handle(ContentImporter $importer): int
    {
        try {
            $result = $importer->run((bool) $this->option('fresh'));
        } catch (ImportException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        foreach ($result['counts'] as $table => $c) {
            $rows[] = [$table, $c['created'], $c['updated'], $c['unchanged'], $c['kept'], $c['removed']];
        }
        $this->table(['Table', 'Created', 'Updated', 'Unchanged', 'Kept', 'Removed'], $rows);
        foreach ($result['notes'] as $note) {
            $this->warn($note);
        }
        $this->info($this->option('fresh') ? 'Rebuilt from the sources.' : 'Imported. Kept = edited in the admin (or no longer in the sources), left as it is.');

        return self::SUCCESS;
    }
}
