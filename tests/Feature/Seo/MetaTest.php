<?php

namespace Tests\Feature\Seo;

use App\Support\SitePages;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client PDF item 25: every page has its own approved SEO title and meta description (the content records' seo_title
 * and meta_description, from the client's files), in the page language, never one text repeated across pages, and the
 * brand is "Summit Legal Consultancy" (item 2). The same check runs on a live site with scripts/qa/seo.mjs.
 */
final class MetaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    public function test_every_page_has_its_own_approved_title_and_description_in_its_language(): void
    {
        $titles = [];
        $descriptions = [];
        foreach (SitePages::all() as $page) {
            foreach ($page['locales'] as $locale) {
                $path = "/{$locale}/{$page['path']}";
                $record = $page['type'] === 'page' ? content()->page($page['code'], $locale) : content()->service($page['code'], $locale);
                $title = (string) ($record['seo_title'] ?? '');
                $description = (string) ($record['meta_description'] ?? '');
                $this->assertNotSame('', trim($title), "{$path}: approved SEO title");
                $this->assertNotSame('', trim($description), "{$path}: approved meta description");

                $html = $this->get($path)->assertOk()->getContent();
                $this->assertSame(1, substr_count($html, '<title>'), $path);
                $this->assertStringContainsString('<title>'.e($title).'</title>', $html, $path);
                $this->assertSame(1, substr_count($html, '<meta name="description"'), $path);
                $this->assertStringContainsString('<meta name="description" content="'.e($description).'">', $html, $path);

                foreach (['title' => $title, 'description' => $description] as $kind => $text) {
                    $this->assertDoesNotMatchRegularExpression('/Summit Legal Consultants\b/i', $text, "{$path} {$kind}");
                    $plain = str_replace('Summit Legal Consultancy', '', $text);
                    $arabic = preg_match('/\p{Arabic}/u', $plain) === 1;
                    $cyrillic = preg_match('/\p{Cyrillic}/u', $plain) === 1;
                    match ($locale) {
                        'ar' => $this->assertTrue($arabic && ! $cyrillic, "{$path} {$kind} is Arabic"),
                        'ru' => $this->assertTrue($cyrillic && ! $arabic, "{$path} {$kind} is Russian"),
                        default => $this->assertTrue(! $arabic && ! $cyrillic, "{$path} {$kind} is English"),
                    };
                }
                $titles[$title][] = $path;
                $descriptions[$description][] = $path;
            }
        }
        foreach ([$titles, $descriptions] as $texts) {
            foreach ($texts as $text => $paths) {
                $this->assertCount(1, $paths, 'repeated on '.implode(', ', $paths).": {$text}");
            }
        }
        $this->assertCount(60, $titles); // (10 since Legal Translation left the site: client PDF item 9)
        $this->assertCount(60, $descriptions);
    }

    public function test_the_default_description_only_fills_a_page_without_its_own(): void
    {
        // The SEO screen's default description (none today) never replaces an approved one.
        $html = $this->get('/en/about/')->getContent();
        $this->assertStringContainsString('<meta name="description" content="'.e(content()->page('P06', 'en')['meta_description']).'">', $html);
    }
}
