<?php

namespace Tests\Feature\Design\D2;

use Database\Seeders\ContentSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * <x-d2.upload.dropzone> (owner: pages-people): the backend's one optional attachment (docs/enquiries.md §6).
 * Rendered only while summit.features.attachments_enabled is true (switched on here by config override,
 * never through .env); the approved label, help, "Remove Document" and error messages; the field contract
 * the consultation wizard uses for every field ({prefix}-attachment, {prefix}-attachment-error).
 */
final class UploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    /** @return array<string, array{string}> */
    public static function locales(): array
    {
        return ['en' => ['en'], 'ar' => ['ar'], 'ru' => ['ru']];
    }

    private function render(string $blade, array $data = [], string $locale = 'en'): string
    {
        app()->setLocale($locale);

        return trim((string) $this->blade($blade, $data));
    }

    private function dom(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div>'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    private function one(DOMXPath $x, string $query): DOMElement
    {
        $nodes = $x->query($query);
        $this->assertNotFalse($nodes);
        $this->assertSame(1, $nodes->length, "exactly one {$query}");
        $node = $nodes->item(0);
        $this->assertInstanceOf(DOMElement::class, $node);

        return $node;
    }

    public function test_it_renders_nothing_while_attachments_are_disabled(): void
    {
        config(['summit.features.attachments_enabled' => false]);

        $this->assertSame('', $this->render('<x-d2.upload.dropzone />'));
        $this->assertSame('', $this->render('<x-d2.upload.dropzone prefix="home" :error="$e" />', ['e' => 'x']));
    }

    #[DataProvider('locales')]
    public function test_with_the_flag_on_it_renders_the_one_named_file_input_with_the_approved_copy(string $locale): void
    {
        config(['summit.features.attachments_enabled' => true, 'summit.enquiries.attachment_max_bytes' => 10_000_000]);
        $html = $this->render('<x-d2.upload.dropzone />', [], $locale);
        $x = $this->dom($html);

        $input = $this->one($x, '//input[@type="file"]');
        $this->assertSame('attachment', $input->getAttribute('name'));
        $this->assertSame('wz-attachment', $input->getAttribute('id'));
        $this->assertFalse($input->hasAttribute('multiple'), 'one file only');
        $this->assertFalse($input->hasAttribute('required'), 'the attachment is optional');
        foreach (['.pdf', '.jpg', '.jpeg', '.png', 'application/pdf', 'image/jpeg', 'image/png'] as $type) {
            $this->assertContains($type, explode(',', $input->getAttribute('accept')));
        }
        $this->assertSame('wz-attachment-hint', $input->getAttribute('aria-describedby'), 'the error id only while it shows');
        $this->assertFalse($input->hasAttribute('aria-invalid'));

        // Approved label (the field's <label for>), help and remove label; proposed "Optional" and button text.
        $labels = $x->query('//label[@for="wz-attachment"]');
        $this->assertSame(2, $labels->length, 'the field label and the "Choose a file" label');
        $this->assertSame(ui('form.attachment.label'), trim($labels->item(0)->textContent));
        $this->assertSame(d2p('upload.choose'), trim($labels->item(1)->textContent));
        $this->assertSame(ui('form.attachment.help'), trim($this->one($x, '//*[@id="wz-attachment-hint"]')->textContent));
        $this->assertStringContainsString(e(ui('form.attachment.remove')), $html);
        $this->assertStringContainsString('data-copy-status="proposed"', $html);

        // The field contract shared with the wizard: hidden error element with .field__error-text.
        $error = $this->one($x, '//*[@id="wz-attachment-error"]');
        $this->assertTrue($error->hasAttribute('hidden'));
        $this->assertSame(1, $x->query('.//*[contains(@class, "field__error-text")]', $error)->length);
        $this->one($x, '//*[@data-field="wz-attachment"]');

        // Enhancement data: Alpine component, limit and the approved messages for the browser checks.
        $root = $this->one($x, '//*[@data-upload]');
        $this->assertSame('d2Upload', $root->getAttribute('x-data'));
        $this->assertSame('10000000', $root->getAttribute('data-max-bytes'));
        $copy = json_decode($this->one($x, '//script[@data-upload-copy]')->textContent, true);
        $this->assertSame($locale, $copy['locale']);
        foreach (['error_type', 'error_size', 'error_count', 'error_rejected'] as $key) {
            $this->assertSame(ui('form.attachment.'.$key), $copy['errors'][$key], $key);
            $this->assertNotSame('', $copy['errors'][$key]);
        }
        $this->assertSame(d2p('upload.attached'), $copy['attached']);
        $this->assertSame(['kb' => d2p('upload.size_kb'), 'mb' => d2p('upload.size_mb')], $copy['sizes']);

        // Design 2.1, Phase 1 (brief §3.6; D02, D04): only the functional upload and close glyphs remain (no
        // file glyphs), and no dot sits between the file's format and size.
        preg_match_all('/data-icon="([^"]+)"/', $html, $icons);
        $this->assertSame([], array_values(array_diff(array_unique($icons[1]), ['upload', 'close', 'error', 'check'])), "{$locale}: functional icons only");
        $this->assertStringNotContainsString('upload__file-dot', $html);
        $this->assertStringNotContainsString('upload__file-icon', $html);
        $this->assertStringNotContainsString('upload__button-icon', $html);
    }

    public function test_a_server_error_is_shown_and_wired_to_the_input(): void
    {
        config(['summit.features.attachments_enabled' => true]);
        $message = ui('form.attachment.error_size');
        $x = $this->dom($this->render('<x-d2.upload.dropzone prefix="home" :error="$e" />', ['e' => $message]));

        $input = $this->one($x, '//input[@type="file"]');
        $this->assertSame('home-attachment', $input->getAttribute('id'));
        $this->assertSame('true', $input->getAttribute('aria-invalid'));
        $this->assertSame(['home-attachment-hint', 'home-attachment-error'], explode(' ', $input->getAttribute('aria-describedby')));
        $error = $this->one($x, '//*[@id="home-attachment-error"]');
        $this->assertFalse($error->hasAttribute('hidden'));
        $this->assertSame($message, trim($error->textContent));
        $this->assertStringContainsString('is-invalid', $this->one($x, '//*[@data-field="home-attachment"]')->getAttribute('class'));
    }

    public function test_the_limit_follows_the_config(): void
    {
        config(['summit.features.attachments_enabled' => true, 'summit.enquiries.attachment_max_bytes' => 5_000_000]);
        $x = $this->dom($this->render('<x-d2.upload.dropzone />'));

        $this->assertSame('5000000', $this->one($x, '//*[@data-upload]')->getAttribute('data-max-bytes'));
    }

    public function test_styleguide_specimens_render_with_the_flag_off_and_never_post(): void
    {
        config(['summit.features.attachments_enabled' => false]);

        foreach (['live', 'idle', 'hover', 'focus', 'over', 'file', 'error', 'disabled', 'plain'] as $state) {
            $x = $this->dom($this->render('<x-d2.upload.dropzone prefix="sg" :specimen="$s" :specimen-file="$f" specimen-error="error_count" />', [
                's' => $state,
                'f' => ['name' => 'sample.pdf', 'size' => 2_440_000, 'type' => 'pdf'],
            ]));
            $input = $this->one($x, '//input[@type="file"]');
            $this->assertFalse($input->hasAttribute('name'), "{$state}: a specimen is never submitted");
            $enhanced = $x->query('//*[@data-upload][@x-data]')->length;
            $this->assertSame($state === 'live' ? 1 : 0, $enhanced, "{$state}: only the live specimen runs the script");
        }

        $file = $this->dom($this->render('<x-d2.upload.dropzone prefix="sg" specimen="file" :specimen-file="$f" />', ['f' => ['name' => 'sample.pdf', 'size' => 2_440_000, 'type' => 'pdf']]));
        $this->assertFalse($this->one($file, '//*[@data-upload-file]')->hasAttribute('hidden'));
        $this->assertSame('sample.pdf', trim($this->one($file, '//*[@data-upload-name]')->textContent));
        $this->assertSame(d2p('upload.size_mb', ['size' => '2.4']), trim($this->one($file, '//*[@data-upload-size]')->textContent));

        $error = $this->dom($this->render('<x-d2.upload.dropzone prefix="sg" specimen="error" specimen-error="error_count" />'));
        $this->assertSame(ui('form.attachment.error_count'), trim($this->one($error, '//*[@id="sg-attachment-error"]')->textContent));

        $this->assertTrue($this->one($this->dom($this->render('<x-d2.upload.dropzone prefix="sg" specimen="disabled" />')), '//input[@type="file"]')->hasAttribute('disabled'));
    }

    public function test_the_styleguide_shows_the_upload_area(): void
    {
        $html = $this->withUnencryptedCookies(['summit_design' => '2'])->get('/en/styleguide/')->assertOk()->getContent();

        $this->assertStringContainsString('id="sg-upload"', $html);
        $this->assertStringContainsString('href="#sg-upload"', $html);
        $this->assertSame(0, preg_match('/<input[^>]+type="file"[^>]+name="attachment"/', $html), 'no specimen is a real, named control');
    }

    /**
     * Design 2.1 §5.2: the wizard shows the control only when the backend also scans uploads
     * (App\Design\D2\Attachment; it does not today, so design.d2.attachment_scanned is off and the field is gone).
     */
    public function test_with_the_flag_on_the_wizard_holds_the_control_only_with_a_scanning_backend(): void
    {
        config(['summit.features.attachments_enabled' => true]);
        foreach (['/en/', '/ar/contact/'] as $path) {
            $html = $this->withUnencryptedCookies(['summit_design' => '2'])->get($path)->assertOk()->getContent();
            $this->assertSame(0, preg_match_all('/<input[^>]+type="file"[^>]+name="attachment"/', $html), "{$path}: no scan, no field");
            $this->assertStringNotContainsString('enctype="multipart/form-data"', $html, $path);
        }
    }

    public function test_with_the_flag_on_the_wizard_holds_one_named_control_and_the_styleguide_none(): void
    {
        config(['summit.features.attachments_enabled' => true, 'design.d2.attachment_scanned' => true]);
        foreach (['/en/', '/ar/contact/'] as $path) {
            $html = $this->withUnencryptedCookies(['summit_design' => '2'])->get($path)->assertOk()->getContent();
            $this->assertSame(1, preg_match_all('/<input[^>]+type="file"[^>]+name="attachment"/', $html), $path);
            $this->assertStringContainsString('enctype="multipart/form-data"', $html, $path);
        }

        // Every wizard specimen in the styleguide carries an unnamed (never posted) dropzone specimen.
        $guide = $this->withUnencryptedCookies(['summit_design' => '2'])->get('/en/styleguide/')->assertOk()->getContent();
        $this->assertSame(0, preg_match('/<input[^>]+type="file"[^>]+name="attachment"/', $guide));
        $this->assertGreaterThan(1, substr_count($guide, 'data-upload-input'));
    }

    public function test_the_consultation_wizard_still_renders_without_the_control_while_the_flag_is_off(): void
    {
        config(['summit.features.attachments_enabled' => false]);
        foreach (['/en/', '/en/contact/'] as $path) {
            $html = $this->withUnencryptedCookies(['summit_design' => '2'])->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('data-wizard-form', $html, $path);
            $this->assertStringNotContainsString('type="file"', $html, $path);
        }
    }

    public function test_every_proposed_upload_and_faq_page_key_exists_in_all_three_languages(): void
    {
        $flatten = function (array $values, string $prefix = '') use (&$flatten): array {
            $out = [];
            foreach ($values as $key => $value) {
                $out += is_array($value) ? $flatten($value, $prefix.$key.'.') : [$prefix.$key => $value];
            }

            return $out;
        };
        foreach (['upload', 'faq_page'] as $area) {
            $en = $flatten(require lang_path("en/proposed-d2/{$area}.php"));
            foreach (['ar', 'ru'] as $locale) {
                $other = $flatten(require lang_path("{$locale}/proposed-d2/{$area}.php"));
                $this->assertSame(array_keys($en), array_keys($other), "{$area} {$locale}");
                foreach ($other as $key => $value) {
                    $this->assertNotSame('', trim((string) $value), "{$area} {$locale}: {$key}");
                    if (! str_starts_with($key, 'type.')) {
                        $this->assertNotSame($en[$key], $value, "{$area} {$locale}: {$key} is still English");
                    }
                }
            }
        }
        // Plural forms: Arabic 6, Russian 3, English 2.
        $this->assertCount(6, explode('|', (string) (require lang_path('ar/proposed-d2/faq_page.php'))['topic_count']));
        $this->assertCount(3, explode('|', (string) (require lang_path('ru/proposed-d2/faq_page.php'))['topic_count']));
        $this->assertCount(2, explode('|', (string) (require lang_path('en/proposed-d2/faq_page.php'))['topic_count']));
    }
}
