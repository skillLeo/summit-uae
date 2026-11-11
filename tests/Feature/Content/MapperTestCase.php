<?php

namespace Tests\Feature\Content;

use App\Content\Docx\DocxReader;
use App\Content\Import\ImportRunner;
use App\Content\Import\Mapper;
use App\Content\Import\Report;
use Tests\TestCase;

/**
 * Runs one or more mappers against the real client DOCX files and exposes the results.
 */
abstract class MapperTestCase extends TestCase
{
    /** Fragments that only ever occur in red designer notes; none may reach public output. */
    protected const NOTE_FRAGMENTS = [
        'Same tab', 'Open in a new tab', 'Launch the matching handler', '[[RED', 'See the separate sitemap',
        'H-L0', 'rel="noopener', 'designer', 'Publish BLACK', 'source fallback',
    ];

    /** @var array<string, array{datasets: array<string, mixed>, coverage: list<array<string, mixed>>, report: Report}> */
    private static array $cache = [];

    /**
     * @param  list<Mapper>  $mappers
     * @return array{datasets: array<string, mixed>, coverage: list<array<string, mixed>>, report: Report}
     */
    protected function import(array $mappers): array
    {
        $key = implode(',', array_map(fn (Mapper $m) => $m::class, $mappers));
        if (! isset(self::$cache[$key])) {
            $runner = new ImportRunner(new DocxReader, $mappers, base_path('package/docx'));
            $datasets = $runner->run();
            self::$cache[$key] = [
                'datasets' => $datasets,
                'coverage' => array_values(array_filter($runner->coverage(), fn ($r) => $r['mapper'] !== null)),
                'report' => $runner->report(),
            ];
        }

        return self::$cache[$key];
    }

    /** Every public block of every file the mappers claimed is consumed, in all three languages. */
    protected function assertFullCoverage(array $result): void
    {
        $this->assertNotEmpty($result['coverage'], 'The mapper claimed no files.');
        foreach ($result['coverage'] as $row) {
            $sample = array_map(fn ($b) => '#'.$b->index.' '.mb_strimwidth($b->text, 0, 80, '…'), array_slice($row['unmapped'], 0, 5));
            $this->assertSame([], $sample, "Unmapped public copy in {$row['locale']}/{$row['file']}");
        }
    }

    /** No string anywhere in the output may contain a red-note fragment. */
    protected function assertNoDesignerNotes(array $result): void
    {
        array_walk_recursive($result['datasets'], function ($value, $key) {
            if (! is_string($value) || $key === 'url' || $key === 'href') {
                return;
            }
            foreach (self::NOTE_FRAGMENTS as $fragment) {
                $this->assertStringNotContainsString($fragment, $value, "Designer-note text leaked into public output: {$value}");
            }
        });
    }

    /** Fails on parity findings (EN/AR/RU structure differences) for the given subject prefix. */
    protected function assertNoParityIssues(array $result, string $prefix = ''): void
    {
        $issues = array_filter($result['report']->entries('parity'), fn ($e) => str_starts_with($e['subject'], $prefix));
        $this->assertSame([], array_values(array_map(fn ($e) => "{$e['locale']} {$e['subject']}: {$e['detail']}", $issues)));
    }
}
