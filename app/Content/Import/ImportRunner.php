<?php

namespace App\Content\Import;

use App\Content\Docx\Block;
use App\Content\Docx\Document;
use App\Content\Docx\DocxReader;
use Illuminate\Support\Facades\File;

final class ImportRunner
{
    public const LOCALES = ['en', 'ar', 'ru'];

    /** @var array<string, array<string, mixed>> locale => dataset => records */
    private array $data = [];

    /** @var array<string, array<string, array{doc: Document, cursor: ?Cursor, mapper: ?string}>> locale => filename => info */
    private array $docs = [];

    private Report $report;

    /**
     * @param  list<Mapper>  $mappers
     */
    public function __construct(
        private readonly DocxReader $reader,
        private readonly array $mappers,
        private readonly string $packageDir,
    ) {
        $this->report = new Report;
    }

    public function report(): Report
    {
        return $this->report;
    }

    /**
     * Parse and map everything. Returns the assembled translatable datasets.
     *
     * @return array<string, array<string, mixed>>
     */
    public function run(): array
    {
        foreach (self::LOCALES as $locale) {
            $this->data[$locale] = [];
            foreach ($this->files($locale) as $file) {
                $this->docs[$locale][basename($file)] = [
                    'doc' => $this->reader->read($file, $locale),
                    'cursor' => null,
                    'mapper' => null,
                ];
            }
            foreach ($this->mappers as $mapper) {
                $mapper->report = $this->report;
                $cursors = [];
                foreach ($mapper->sources() as $key => $prefix) {
                    $name = $this->resolve($locale, $prefix);
                    $cursor = new Cursor($this->docs[$locale][$name]['doc']);
                    $this->docs[$locale][$name]['cursor'] = $cursor;
                    $this->docs[$locale][$name]['mapper'] = $mapper->name();
                    $cursors[$key] = $cursor;
                }
                $this->merge($locale, $mapper->map($cursors, $locale), $mapper->name());
            }
        }

        return $this->assemble();
    }

    /** @return list<string> */
    private function files(string $locale): array
    {
        $files = glob($this->packageDir.'/'.$locale.'/*.docx') ?: [];
        sort($files);

        return $files;
    }

    private function resolve(string $locale, string $prefix): string
    {
        $matches = array_values(array_filter(
            array_keys($this->docs[$locale] ?? []),
            fn (string $name) => str_starts_with($name, $prefix),
        ));
        if (count($matches) !== 1) {
            throw new ImportException("[{$locale}] expected exactly one DOCX starting with {$prefix}, found ".count($matches));
        }

        return $matches[0];
    }

    /** @param array<string, array<string, mixed>> $partial */
    private function merge(string $locale, array $partial, string $mapper): void
    {
        foreach ($partial as $dataset => $records) {
            if (! array_key_exists($dataset, ContentSchema::DATASETS)) {
                throw new ImportException("{$mapper} returned unknown dataset {$dataset}");
            }
            foreach ($records as $code => $value) {
                $existing = $this->data[$locale][$dataset][$code] ?? null;
                $this->data[$locale][$dataset][$code] = $existing === null
                    ? $value
                    : $this->mergeValue($existing, $value, "{$locale}.{$dataset}.{$code}", $mapper);
            }
        }
    }

    private function mergeValue(mixed $a, mixed $b, string $path, string $mapper): mixed
    {
        if (is_array($a) && is_array($b) && ! array_is_list($a) && ! array_is_list($b)) {
            foreach ($b as $k => $v) {
                $a[$k] = array_key_exists($k, $a) ? $this->mergeValue($a[$k], $v, "{$path}.{$k}", $mapper) : $v;
            }

            return $a;
        }
        if ($a !== $b) {
            throw new ImportException("{$mapper} conflicts with an earlier mapper at {$path}");
        }

        return $a;
    }

    /** @return array<string, array<string, mixed>> */
    private function assemble(): array
    {
        $out = [];
        foreach (ContentSchema::DATASETS as $dataset => $plain) {
            $codes = [];
            foreach (self::LOCALES as $locale) {
                $codes = array_merge($codes, array_keys($this->data[$locale][$dataset] ?? []));
            }
            $codes = array_values(array_unique($codes));
            $out[$dataset] = [];
            foreach ($codes as $code) {
                $byLocale = [];
                foreach (self::LOCALES as $locale) {
                    $byLocale[$locale] = $this->data[$locale][$dataset][$code] ?? null;
                    if ($byLocale[$locale] === null) {
                        $this->report->add('parity', "{$dataset}.{$code}", 'missing in this language', $locale);
                    }
                }
                if (in_array($dataset, ContentSchema::FLAT, true)) {
                    $out[$dataset][$code] = $byLocale;
                    $this->checkShape("{$dataset}.{$code}", $byLocale);

                    continue;
                }
                $record = [];
                $fields = [];
                foreach ($byLocale as $fieldsForLocale) {
                    $fields = array_merge($fields, array_keys($fieldsForLocale ?? []));
                }
                foreach (array_values(array_unique($fields)) as $field) {
                    $values = [];
                    foreach (self::LOCALES as $locale) {
                        $values[$locale] = $byLocale[$locale][$field] ?? null;
                    }
                    if (ContentSchema::isPlain($dataset, $field)) {
                        $record[$field] = $values['en'];
                        foreach (['ar', 'ru'] as $locale) {
                            if ($values[$locale] !== null && $values[$locale] !== $values['en']) {
                                $this->report->add('source-conflict', "{$dataset}.{$code}.{$field}",
                                    'EN value '.json_encode($values['en'], JSON_UNESCAPED_UNICODE).' differs from '.json_encode($values[$locale], JSON_UNESCAPED_UNICODE), $locale);
                            }
                        }
                    } else {
                        $record[$field] = $values;
                        $this->checkShape("{$dataset}.{$code}.{$field}", $values);
                    }
                }
                $out[$dataset][$code] = $record;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $values */
    private function checkShape(string $path, array $values): void
    {
        $en = self::shape($values['en'] ?? null);
        foreach (['ar', 'ru'] as $locale) {
            if (($values[$locale] ?? null) === null) {
                continue;
            }
            $other = self::shape($values[$locale]);
            if ($en !== $other) {
                $this->report->add('parity', $path, 'structure differs from English: EN '.$en.' vs '.strtoupper($locale).' '.$other, $locale);
            }
        }
    }

    private static function shape(mixed $v): string
    {
        if (! is_array($v)) {
            return $v === null ? 'null' : 's';
        }
        if (array_is_list($v)) {
            return '['.implode(',', array_map(self::shape(...), $v)).']';
        }
        ksort($v);
        $parts = [];
        foreach ($v as $k => $item) {
            $parts[] = $k.':'.self::shape($item);
        }

        return '{'.implode(',', $parts).'}';
    }

    // ------------------------------------------------------------------ outputs

    /** @param array<string, array<string, mixed>> $datasets */
    public function writeDatasets(array $datasets, string $dir): void
    {
        File::ensureDirectoryExists($dir);
        foreach ($datasets as $dataset => $records) {
            File::put($dir.'/'.$dataset.'.json', json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        }
    }

    public function writeDesignerNotes(string $dir): int
    {
        $count = 0;
        foreach ($this->docs as $locale => $docs) {
            File::ensureDirectoryExists($dir.'/'.$locale);
            foreach ($docs as $name => $info) {
                $lines = ['# Designer notes: '.$name, '', 'Red text from `package/docx/'.$locale.'/'.$name.'`. Internal guidance only, never published. Black headings are shown for context.', ''];
                foreach ($info['doc']->blocks as $b) {
                    if ($b->isHeading()) {
                        $lines[] = '';
                        $lines[] = str_repeat('#', min(6, $b->headingLevel + 1)).' '.$b->text;
                        $lines[] = '';
                    }
                    if ($b->note !== '') {
                        $lines[] = '- '.str_replace("\n", ' ', $b->note).' `#'.$b->index.'`';
                        $count++;
                    }
                }
                File::put($dir.'/'.$locale.'/'.pathinfo($name, PATHINFO_FILENAME).'.md', implode("\n", $lines)."\n");
            }
        }

        return $count;
    }

    public function writeLedger(string $path): void
    {
        $ledger = [];
        foreach ($this->docs as $locale => $docs) {
            foreach ($docs as $name => $info) {
                if ($info['cursor'] !== null) {
                    $ledger[$locale][$name] = $info['cursor']->ledger();
                }
            }
        }
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($ledger, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
    }

    /**
     * Coverage per document: public blocks, consumed, unmapped.
     *
     * @return list<array{locale: string, file: string, mapper: ?string, public: int, consumed: int, unmapped: list<Block>}>
     */
    public function coverage(): array
    {
        $rows = [];
        foreach ($this->docs as $locale => $docs) {
            foreach ($docs as $name => $info) {
                $public = $info['doc']->publicBlocks();
                $unmapped = $info['cursor'] === null ? $public : $info['cursor']->unconsumedPublic();
                $rows[] = [
                    'locale' => $locale,
                    'file' => $name,
                    'mapper' => $info['mapper'],
                    'public' => count($public),
                    'consumed' => count($public) - count($unmapped),
                    'unmapped' => $unmapped,
                ];
            }
        }

        return $rows;
    }
}
