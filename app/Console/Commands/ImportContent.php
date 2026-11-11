<?php

namespace App\Console\Commands;

use App\Content\Docx\DocxReader;
use App\Content\Import\ImportException;
use App\Content\Import\ImportRunner;
use App\Content\Import\Mapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class ImportContent extends Command
{
    protected $signature = 'summit:import
        {--only= : Comma-separated mapper class names; implies --dry-run}
        {--dry-run : Parse and report coverage without writing any files}
        {--out= : With --only, write the partial datasets as JSON into this directory for inspection}';

    protected $description = 'Import the approved DOCX copy (EN/AR/RU) into database/content/*.json and write the import reports';

    public function handle(DocxReader $reader): int
    {
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        $dryRun = $this->option('dry-run') || $only !== [];
        $mappers = self::discoverMappers($only);
        if ($mappers === []) {
            $this->error('No mappers found.');

            return self::FAILURE;
        }

        $runner = new ImportRunner($reader, $mappers, base_path('package/docx'));
        try {
            $datasets = $runner->run();
        } catch (ImportException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($only === []) {
            \App\Content\Import\CrossChecks::run($datasets, $runner->report());
        }
        $coverage = $runner->coverage();
        $this->table(['Locale', 'File', 'Mapper', 'Public blocks', 'Unmapped'], array_map(fn ($r) => [
            $r['locale'], $r['file'], $r['mapper'] ?? '—', $r['public'], count($r['unmapped']),
        ], array_values(array_filter($coverage, fn ($r) => $only === [] || $r['mapper'] !== null))));

        $report = $runner->report();
        foreach ($report->entries() as $e) {
            if ($e['type'] !== 'reuse') {
                $this->line(sprintf('  <comment>%s</comment> %s %s — %s', $e['type'], $e['locale'] ?? '*', $e['subject'], $e['detail']));
            }
        }

        if ($dryRun) {
            if ($this->option('out')) {
                $runner->writeDatasets(array_filter($datasets), (string) $this->option('out'));
                $this->info('Preview datasets written to '.$this->option('out'));
            }
            foreach ($coverage as $row) {
                if ($row['mapper'] !== null && $row['unmapped'] !== []) {
                    $this->warn("Unmapped public copy in {$row['locale']}/{$row['file']}:");
                    foreach (array_slice($row['unmapped'], 0, 40) as $b) {
                        $this->line(sprintf('  #%d %s', $b->index, mb_strimwidth($b->text, 0, 110, '…')));
                    }
                }
            }
            $this->info('Dry run: nothing written.');

            return self::SUCCESS;
        }

        $runner->writeDatasets($datasets, database_path('content'));
        $notes = $runner->writeDesignerNotes(base_path('docs/designer-notes'));
        $runner->writeLedger(storage_path('app/import/ledger.json'));
        File::put(base_path('docs/content-diff.md'), (new ContentDiffRenderer)->render($coverage, $report));

        $this->info(sprintf('Wrote %d datasets to database/content, %d designer notes, docs/content-diff.md.', count($datasets), $notes));

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $only
     * @return list<Mapper>
     */
    public static function discoverMappers(array $only = []): array
    {
        $mappers = [];
        foreach (glob(app_path('Content/Import/Mappers/*Mapper.php')) ?: [] as $file) {
            $class = 'App\\Content\\Import\\Mappers\\'.basename($file, '.php');
            if ($only !== [] && ! in_array(class_basename($class), $only, true)) {
                continue;
            }
            if (class_exists($class) && is_subclass_of($class, Mapper::class)) {
                $mappers[] = new $class;
            }
        }
        usort($mappers, fn (Mapper $a, Mapper $b) => strcmp($a->name(), $b->name()));

        return $mappers;
    }
}
