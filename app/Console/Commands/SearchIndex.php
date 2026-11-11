<?php

namespace App\Console\Commands;

use App\Content\ContentRepository;
use App\Support\SiteUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Writes the Design 2 command-palette index public/search/{locale}.json (upgrade spec §12 C12, §5.5):
 * every service, guide, FAQ and case study as {id, type, title, summary, url, text}, from the imported
 * DOCX content exactly as stored (nothing is shortened or reworded; `text` is extra approved copy that
 * is searched but never shown). URLs are site-relative, so the files are the same on every host.
 *
 * Deterministic: fixed order (services, guides, FAQs, case studies, each by position) and fixed
 * formatting (one item per line), so the committed files only change when the content changes.
 * Run it after `summit:import` + `db:seed`; `--check` exits 1 when a file is missing or stale (CI and
 * tests/Feature/Design/D2/SearchIndexTest.php).
 */
final class SearchIndex extends Command
{
    protected $signature = 'summit:search-index
        {--check : Exit 1 when a file is missing or out of date, without writing}
        {--dir= : Output directory (default: public/search)}';

    protected $description = 'Generate public/search/{locale}.json for the Design 2 command palette (services, guides, FAQs, case studies)';

    public const VERSION = 1;

    public function handle(ContentRepository $content): int
    {
        $dir = $this->option('dir') ? (string) $this->option('dir') : public_path('search');
        $check = (bool) $this->option('check');
        $stale = [];

        foreach (array_keys((array) config('summit.locales')) as $locale) {
            $json = self::encode(self::documents($content, $locale), $locale);
            $path = rtrim($dir, '/')."/{$locale}.json";

            if ($check) {
                if (! is_file($path) || file_get_contents($path) !== $json) {
                    $stale[] = $path;
                }

                continue;
            }

            File::ensureDirectoryExists(dirname($path));
            File::put($path, $json);
            $this->line(sprintf('%s  %d items, %s KB, %s KB gzip', $path, substr_count($json, "\n{"), number_format(strlen($json) / 1024, 1), number_format(strlen((string) gzencode($json, 9)) / 1024, 1)));
        }

        if ($check) {
            foreach ($stale as $path) {
                $this->error("Stale or missing: {$path}. Run php artisan summit:search-index");
            }

            return $stale === [] ? self::SUCCESS : self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * The searchable records of one language, in index order.
     *
     * @return list<array{id: string, type: string, title: string, summary: string, url: string, text: string}>
     */
    public static function documents(ContentRepository $content, string $locale): array
    {
        $docs = [];

        foreach ($content->services($locale) as $service) {
            $docs[] = self::doc(
                $service['code'],
                'service',
                $service['title'] ?? '',
                $service['hub_summary'] ?? $service['home_summary'] ?? '',
                SiteUrl::servicePath($service['code'], $locale),
                [...(array) ($service['situations'] ?? [])],
            );
        }

        foreach ($content->guides($locale) as $guide) {
            $body = (array) ($guide['body'] ?? []);
            $docs[] = self::doc(
                $guide['code'],
                'guide',
                $guide['card_title'] ?? $guide['title'] ?? '',
                $guide['card_summary'] ?? $guide['home_summary'] ?? '',
                SiteUrl::pagePath('P03', $locale).'#'.config("summit.guides.{$guide['code']}", $guide['anchor'] ?? ''),
                [
                    $body['title'] ?? '',
                    ...self::texts($body['intro'] ?? []),
                    ...array_map(fn ($section) => (string) ($section['heading'] ?? ''), (array) ($body['sections'] ?? [])),
                ],
            );
        }

        foreach ($content->faqs($locale) as $faq) {
            $paragraphs = self::texts($faq['answer'] ?? []);
            $docs[] = self::doc(
                $faq['anchor'] ?? $faq['code'],
                'faq',
                $faq['question'] ?? '',
                $paragraphs[0] ?? '',
                SiteUrl::pagePath('P05', $locale).'#'.($faq['anchor'] ?? $faq['code']),
                array_slice($paragraphs, 1),
            );
        }

        foreach ($content->all('case_studies', $locale) as $case) {
            $docs[] = self::doc(
                $case['anchor'] ?? $case['code'],
                'case',
                $case['card_title'] ?? $case['title'] ?? '',
                $case['card_summary'] ?? $case['home_summary'] ?? '',
                SiteUrl::pagePath('P04', $locale).'#'.($case['anchor'] ?? $case['code']),
                [$case['matter'] ?? ''],
            );
        }

        return $docs;
    }

    /** One record per line inside a small envelope: stable bytes, readable diffs. */
    public static function encode(array $docs, string $locale): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
        $lines = array_map(fn (array $doc) => json_encode($doc, $flags), $docs);

        return '{"version":'.self::VERSION.',"locale":'.json_encode($locale, $flags).',"items":['."\n"
            .implode(",\n", $lines)."\n"
            ."]}\n";
    }

    /** @param list<string> $extra */
    private static function doc(string $id, string $type, mixed $title, mixed $summary, string $url, array $extra): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'title' => self::clean($title),
            'summary' => self::clean($summary),
            'url' => $url,
            'text' => implode("\n", array_values(array_filter(array_map(fn ($t) => self::clean($t), $extra), fn ($t) => $t !== ''))),
        ];
    }

    /** Paragraph texts of a block list ([{type: p, text}, …]). @return list<string> */
    private static function texts(mixed $blocks): array
    {
        $out = [];
        foreach ((array) $blocks as $block) {
            if (is_array($block) && ($block['type'] ?? 'p') === 'p' && is_string($block['text'] ?? null) && trim($block['text']) !== '') {
                $out[] = $block['text'];
            }
        }

        return $out;
    }

    /** The stored text, trimmed; non-strings (missing translations) become ''. */
    private static function clean(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
