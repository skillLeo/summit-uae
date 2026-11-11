<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Docs;
use Database\Seeders\ContentSeeder;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Design 2 sample documents (<x-d2.doc.*>) and the Anatomy of a Document section (#anatomy).
 * Upgrade spec §4.2, §5.4, §12 B2 / C3; owner: docs. Server-rendered contract: the document roots and
 * their data-callouts, the key fields, the trilingual watermark, generic content only, the anatomy's
 * single-select groups, its no-JS fallback and proposed copy in all three languages.
 */
final class DocsTest extends TestCase
{
    use RefreshDatabase;

    private const TYPES = ['cheque', 'memo', 'contract', 'tenancy', 'transfer', 'messages', 'notice'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function dom(string $html): HTMLDocument
    {
        return HTMLDocument::createFromString($html, LIBXML_NOERROR);
    }

    private function render(string $type, string $locale, string $variant = 'stage', ?string $hot = null): HTMLDocument
    {
        app()->setLocale($locale);
        $hotAttr = $hot === null ? '' : ' hot="'.$hot.'"';

        return $this->dom('<!DOCTYPE html><html lang="'.$locale.'"><body>'.Blade::render('<x-d2.doc.'.$type.' variant="'.$variant.'"'.$hotAttr.' />').'</body></html>');
    }

    private function home(string $locale): string
    {
        return $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/")->assertOk()->getContent();
    }

    /** @return list<Element> */
    private function all(HTMLDocument|Element $root, string $selector): array
    {
        return iterator_to_array($root->querySelectorAll($selector));
    }

    public function test_every_document_root_follows_the_shared_contract(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            foreach (self::TYPES as $type) {
                foreach (Docs::VARIANTS as $variant) {
                    $doc = $this->render($type, $locale, $variant);
                    $roots = $this->all($doc, '.doc');
                    $this->assertCount(1, $roots, "{$locale} {$type} {$variant}: one document root (the cheque's memo is not a second root)");
                    $root = $roots[0];

                    $this->assertSame($type, $root->getAttribute('data-doc'));
                    $this->assertSame($variant, $root->getAttribute('data-variant'));
                    foreach (['doc', 'doc--'.$type, 'doc--'.$variant] as $class) {
                        $this->assertContains($class, explode(' ', $root->getAttribute('class')), "{$type}: class {$class}");
                    }
                    $this->assertNull($root->getAttribute('aria-hidden'), 'the root itself stays exposable (a container may give it role="img")');
                    $this->assertTrue($root->hasAttribute('data-illustration'), "{$type} {$variant}: the root is marked as an illustration (Design 2.1 brief §7.1)");
                    foreach (['doc__shadow--rest', 'doc__shadow--air', 'doc__body'] as $part) {
                        $el = null;
                        foreach ($root->childNodes as $child) {
                            if ($child instanceof Element && in_array($part, explode(' ', (string) $child->getAttribute('class')), true)) {
                                $el = $child;
                            }
                        }
                        $this->assertNotNull($el, "{$type}: .{$part} is a child of the root");
                        $this->assertSame('true', $el->getAttribute('aria-hidden'), "{$type}: {$part} is aria-hidden");
                    }

                    $callouts = json_decode((string) $root->getAttribute('data-callouts'), true, flags: JSON_THROW_ON_ERROR);
                    $this->assertCount(3, $callouts, "{$type}: three callouts");
                    foreach ($callouts as $c) {
                        $this->assertSame(['id', 'x', 'y', 'label', 'side'], array_keys($c));
                        $this->assertGreaterThanOrEqual(0, $c['x']);
                        $this->assertLessThanOrEqual(100, $c['x']);
                        $this->assertGreaterThanOrEqual(0, $c['y']);
                        $this->assertLessThanOrEqual(100, $c['y']);
                        $this->assertContains($c['side'], ['start', 'end']);
                        $this->assertNotSame('', trim($c['label']));
                        $this->assertStringNotContainsString('docs.callouts', $c['label'], "{$locale} {$type}.{$c['id']}: translated label");
                        $this->assertNotNull($root->querySelector('[data-field="'.$c['id'].'"]'), "{$type}: callout {$c['id']} has its field");
                    }
                }
            }
        }
    }

    public function test_same_side_callout_labels_never_stack_on_each_other(): void
    {
        foreach (Docs::CALLOUTS as $type => $callouts) {
            $height = Docs::SIZE[$type][1];
            foreach (['start', 'end'] as $side) {
                $ys = array_values(array_map(fn ($c) => $c[1], array_filter($callouts, fn ($c) => $c[2] === $side)));
                sort($ys);
                for ($i = 1; $i < count($ys); $i++) {
                    $gap = ($ys[$i] - $ys[$i - 1]) / 100 * $height;
                    $this->assertGreaterThanOrEqual(40, $gap, "{$type}: {$side} labels {$gap}px apart (a 14/20 label with 10 px padding is 40 px tall)");
                }
            }
        }
    }

    public function test_watermark_is_on_every_document_in_all_three_languages(): void
    {
        // Design 2.1 (brief §3.6 rule 2, D02): one phrase per language, never a middle-dot string.
        $texts = ['en' => 'ILLUSTRATIVE SAMPLE', 'ar' => 'نموذج توضيحي', 'ru' => 'УСЛОВНЫЙ ОБРАЗЕЦ'];
        foreach (['en', 'ar', 'ru'] as $locale) {
            foreach (self::TYPES as $type) {
                $lines = $this->all($this->render($type, $locale), '.doc__wm .doc__wm-line');
                $this->assertCount(3, $lines, "{$locale} {$type}: three watermark lines");
                $this->assertSame($locale, $lines[0]->getAttribute('lang'), "{$locale} {$type}: the page language first");
                $byLang = [];
                foreach ($lines as $line) {
                    $byLang[$line->getAttribute('lang')] = trim((string) $line->getAttribute('data-wm'));
                    $this->assertSame($line->getAttribute('lang') === 'ar' ? 'rtl' : 'ltr', $line->getAttribute('dir'));
                }
                ksort($byLang);
                $this->assertSame(['ar' => $texts['ar'], 'en' => $texts['en'], 'ru' => $texts['ru']], $byLang);
            }
        }
    }

    /**
     * Design 2.1 brief §3.6 rule 2 / §7.1 rule 3 (D02): no middle-dot or bullet separators on a sample document either
     * (illustrations are exempt from rules 1, 2, 6 and 7 only): not in the watermark (drawn from data-wm), not in the
     * receipt's date and time.
     */
    public function test_documents_use_no_separator_dots(): void
    {
        $dot = '/[·•∙⋅‧●◦・]/u';
        foreach (['en', 'ar', 'ru'] as $locale) {
            foreach (\Illuminate\Support\Arr::dot(require lang_path("{$locale}/proposed-d2/docs.php")) as $key => $value) {
                $this->assertDoesNotMatchRegularExpression($dot, (string) $value, "{$locale} docs.{$key}");
            }
            foreach (self::TYPES as $type) {
                // the text and every attribute (data-wm, data-callouts) of the rendered document
                $html = html_entity_decode($this->render($type, $locale)->saveHtml());
                $this->assertDoesNotMatchRegularExpression($dot, $html, "{$locale} {$type}: no separator dot");
            }
        }
        $this->assertSame('00/00/2026 00:00', d2p('docs.transfer.date_value', [], 'en'));
        $this->assertSame('00.00.2026 00:00', d2p('docs.transfer.date_value', [], 'ru'));
    }

    public function test_documents_are_generic_illustrations(): void
    {
        $banned = '/\b(law firm|lawyer|advocate|ejari|dld|rera|land department|ministry|court|tribunal|police|iban|swift|qr)\b|محكمة|وزارة|إيجاري|دائرة الأراضي|محام|суд\b|судеб|министерств|адвокат|юрист/iu';
        foreach (['en', 'ar', 'ru'] as $locale) {
            foreach (self::TYPES as $type) {
                $html = Blade::render('<x-d2.doc.'.$type.' />');
                $text = html_entity_decode(strip_tags($html));
                $this->assertDoesNotMatchRegularExpression($banned, $text, "{$locale} {$type}: no real institution, template or credential");
                $this->assertStringNotContainsString(' style="', $html, "{$type}: no style attribute (CSP)");
                $this->assertSame('', $this->numbersOnDocument($text), "{$locale} {$type}: zeroed numbers only (dates 00/00/2026)");
            }
        }
        // the generic names in every language
        $names = ['en' => ['Sample Bank', 'A. Example', 'B. Sample LLC'], 'ar' => ['البنك النموذجي', 'أ. مثال', 'ب. نموذج ذ.م.م'], 'ru' => ['Банк «Образец»', 'А. Пример', 'ООО «Б. Образец»']];
        foreach ($names as $locale => [$bank, $payee, $company]) {
            app()->setLocale($locale);
            $cheque = html_entity_decode(strip_tags(Blade::render('<x-d2.doc.cheque />')));
            $this->assertStringContainsString($bank, $cheque);
            $this->assertStringContainsString($payee, $cheque);
            $this->assertStringContainsString($company, $cheque);
            $this->assertMatchesRegularExpression('/00[\/.]00[\/.]2026/', $cheque, "{$locale}: the 00/00/2026 date");
        }
    }

    /** Digits on a document other than 0, the clause and list numbers, the page folio and fixed codes. */
    private function numbersOnDocument(string $text): string
    {
        $text = preg_replace('/ERC-20|UTC\+4|\b20(26|27)\b/u', '', $text);           // fixed codes, the sample years
        $text = preg_replace('/(Page|Страница|صفحة)\s*1\s*\S{1,3}\s*2/u', '', $text);   // folio "Page 1 of 2"
        $text = preg_replace('/(^|\s)[1-5]\.\s/u', ' ', $text);                         // clause numbers "1. "

        return (string) preg_replace('/[^1-9]/u', '', $text);
    }

    public function test_arabic_documents_run_right_to_left_with_numbers_isolated(): void
    {
        foreach (self::TYPES as $type) {
            app()->setLocale('ar');
            $html = Blade::render('<x-d2.doc.'.$type.' />');
            $this->assertMatchesRegularExpression('/<bdi dir="ltr">[^<]*0[^<]*<\/bdi>/u', $html, "{$type}: numbers in <bdi dir=\"ltr\">");
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', html_entity_decode(strip_tags($html)), "{$type}: Arabic content");
        }
    }

    public function test_selected_field_is_marked_on_the_server(): void
    {
        foreach (Docs::HOTSPOTS as $type => $ids) {
            foreach ($ids as $id) {
                $doc = $this->render($type, 'en', 'anatomy', $id);
                $hot = $this->all($doc, '[data-field][data-hot]');
                $this->assertCount(1, $hot, "{$type}: exactly one selected field");
                $this->assertSame($id, $hot[0]->getAttribute('data-field'));
            }
        }
    }

    public function test_anatomy_section_renders_its_groups_and_the_no_js_fallback(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            app()->setLocale($locale);
            $html = $this->home($locale);
            $dom = $this->dom($html);
            $section = $dom->getElementById('anatomy');
            $this->assertNotNull($section, "{$locale}: #anatomy");
            $this->assertSame('anatomy-title', $section->getAttribute('aria-labelledby'));
            $h2 = $dom->getElementById('anatomy-title');
            $this->assertSame('H2', $h2->tagName);
            $this->assertSame(d2p('anatomy.title'), trim($h2->textContent));

            // document chips: one toolbar of toggle buttons, the first pressed
            $chips = $this->all($section, '[data-anatomy-docs] [data-anatomy-doc]');
            $this->assertSame(Docs::ANATOMY, array_map(fn ($c) => $c->getAttribute('data-anatomy-doc'), $chips));
            $this->assertSame('toolbar', $section->querySelector('[data-anatomy-docs]')->getAttribute('role'));
            $this->assertSame(['true', 'false', 'false', 'false', 'false'], array_map(fn ($c) => $c->getAttribute('aria-pressed'), $chips));
            foreach ($chips as $chip) {
                $this->assertSame('BUTTON', $chip->tagName);
                $this->assertSame(Docs::name($chip->getAttribute('data-anatomy-doc')), trim($chip->textContent));
            }

            // panels: the first visible, the others hidden; each with its document, hotspots and notes
            foreach (Docs::ANATOMY as $i => $type) {
                $panel = $dom->getElementById('anatomy-panel-'.$type);
                $this->assertNotNull($panel);
                $this->assertSame($i > 0, $panel->hasAttribute('hidden'), "{$type}: hidden unless first");
                $figure = $panel->querySelector('.anatomy__figure');
                $this->assertSame('img', $figure->getAttribute('role'));
                $this->assertSame(Docs::alt($type), $figure->getAttribute('aria-label'));
                $this->assertSame('anatomy', $figure->querySelector('.doc')->getAttribute('data-variant'));

                $parts = $this->all($panel, '[data-anatomy-parts] [data-anatomy-part]');
                $this->assertSame(Docs::HOTSPOTS[$type], array_map(fn ($c) => $c->getAttribute('data-anatomy-part'), $parts));
                foreach ($parts as $j => $part) {
                    $id = $part->getAttribute('data-anatomy-part');
                    $this->assertSame($j === 0 ? 'true' : 'false', $part->getAttribute('aria-pressed'));
                    $this->assertNotNull($panel->querySelector('.doc [data-field="'.$id.'"]'), "{$type}.{$id}: field on the document");
                    $note = $dom->getElementById($part->getAttribute('aria-controls'));
                    $this->assertNotNull($note, "{$type}.{$id}: aria-controls points at its note");
                    $this->assertSame($id, $note->getAttribute('data-anatomy-note'));
                    $this->assertSame($j === 0, str_contains($note->getAttribute('class'), 'is-current'));
                    $this->assertCount(2, $this->all($note, 'dt'));
                    $this->assertStringContainsString(d2p('anatomy.fields.'.$type.'.'.$id.'.show'), html_entity_decode($note->getAttribute('data-announce')));
                }
                $hot = $this->all($panel, '.doc [data-field][data-hot]');
                $this->assertCount(1, $hot);
                $this->assertSame(Docs::HOTSPOTS[$type][0], $hot[0]->getAttribute('data-field'));

                // the consultation link works without JavaScript
                $cta = $panel->querySelector('[data-anatomy-cta]');
                $this->assertSame(ui('cta.request_consultation'), trim($cta->textContent));
                $this->assertStringEndsWith('#consultation-form', $cta->getAttribute('href'));
                $service = Docs::SERVICE[$type];
                $service ? $this->assertStringContainsString('service='.$service, $cta->getAttribute('href')) : $this->assertStringNotContainsString('service=', $cta->getAttribute('href'));
            }

            $anatomy = $this->between($html, '<section class="section anatomy"', '</section>');
            $this->assertStringNotContainsString(' style="', $anatomy, 'CSP: no style attributes');
            $this->assertStringContainsString('x-data="d2Anatomy"', $anatomy);
            $this->assertSame(0, substr_count($anatomy, '<h2') - 1, 'one H2');
        }
    }

    /**
     * Design 2.1 D28 and §6 "Anatomy": the documents are the one chip, the parts are a vertical list beside the
     * document and the document carries one hotspot button per part. Both groups name the same parts in the same
     * order, control the same notes, start with the first part pressed and are one Tab stop each (roving tabindex).
     */
    public function test_anatomy_parts_list_and_hotspots_name_the_same_parts(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            app()->setLocale($locale);
            $html = $this->home($locale);
            $dom = $this->dom($html);
            $section = $dom->getElementById('anatomy');

            foreach ($this->all($section, '[data-anatomy-doc]') as $chip) {
                $this->assertSame('chip chip--filter', $chip->getAttribute('class'), "{$locale}: the one chip, no 2.0 variant");
            }
            $this->assertCount(0, $this->all($section, '.chip--hotspot, .chip--choice'), "{$locale}: no second chip style");

            foreach (Docs::ANATOMY as $type) {
                $panel = $dom->getElementById('anatomy-panel-'.$type);
                $name = Docs::name($type);

                $list = $panel->querySelector('[data-anatomy-parts]');
                $this->assertSame('toolbar', $list->getAttribute('role'));
                $this->assertSame('vertical', $list->getAttribute('aria-orientation'));
                $this->assertSame(d2p('anatomy.parts_label', ['document' => $name]), $list->getAttribute('aria-label'));

                $layer = $panel->querySelector('[data-anatomy-hotspots]');
                $this->assertNotNull($layer, "{$locale} {$type}: hotspots over the document");
                $this->assertSame('toolbar', $layer->getAttribute('role'));
                $this->assertSame(d2p('anatomy.hotspots_label', ['document' => $name]), $layer->getAttribute('aria-label'));
                $this->assertNull($layer->closest('[role="img"]'), 'the buttons are not inside the role="img" figure');
                $this->assertNull($layer->closest('[data-illustration]'), 'the buttons are interface, not illustration');
                $this->assertTrue($layer->parentElement->isSameNode($panel->querySelector('[data-anatomy-plate]')), 'the layer sits on the plate over the figure');

                $parts = $this->all($list, '[data-anatomy-part]');
                $spots = $this->all($layer, '[data-anatomy-hotspot]');
                $this->assertSame(Docs::HOTSPOTS[$type], array_map(fn ($b) => $b->getAttribute('data-anatomy-hotspot'), $spots));
                foreach (Docs::HOTSPOTS[$type] as $j => $id) {
                    $label = d2p('anatomy.fields.'.$type.'.'.$id.'.name');
                    foreach ([$parts[$j], $spots[$j]] as $button) {
                        $this->assertSame('BUTTON', $button->tagName);
                        $this->assertSame('button', $button->getAttribute('type'));
                        $this->assertSame('anatomy-'.$type.'-'.$id, $button->getAttribute('aria-controls'));
                        $this->assertSame($j === 0 ? 'true' : 'false', $button->getAttribute('aria-pressed'));
                        $this->assertSame($j === 0 ? '0' : '-1', $button->getAttribute('tabindex'), 'one Tab stop per group');
                    }
                    $this->assertSame($label, trim($parts[$j]->textContent), "{$locale} {$type}.{$id}: the row shows the part name");
                    $this->assertSame($label, $spots[$j]->getAttribute('aria-label'), "{$locale} {$type}.{$id}: the hotspot is named like its row");
                    $this->assertSame('', trim($spots[$j]->textContent), 'a hotspot draws nothing but its focus ring');
                }
            }
        }

        $css = (string) file_get_contents(resource_path('css/d2/sections/anatomy.css'));
        $this->assertMatchesRegularExpression('/\.anatomy__hotspot:focus-visible \{\s*outline: var\(--focus-w\) solid var\(--focus-color\);/', $css, 'the focus ring is drawn on the document');
        $this->assertMatchesRegularExpression('/\[data-field\]\[data-peek\]:not\(\[data-hot\]\)::after \{\s*opacity: 0\.45;/', $css, 'a hovered or focused part lights its field');
        $this->assertMatchesRegularExpression('/\.no-js \.anatomy__hotspots \{\s*display: none;/', $css);
    }

    public function test_proposed_copy_exists_in_all_three_languages(): void
    {
        foreach (['docs', 'anatomy'] as $area) {
            $en = require lang_path("en/proposed-d2/{$area}.php");
            $ar = require lang_path("ar/proposed-d2/{$area}.php");
            $ru = require lang_path("ru/proposed-d2/{$area}.php");
            $keys = fn (array $a) => array_keys(\Illuminate\Support\Arr::dot($a));
            $this->assertSame($keys($en), $keys($ar), "{$area}: Arabic keys");
            $this->assertSame($keys($en), $keys($ru), "{$area}: Russian keys");
            foreach (\Illuminate\Support\Arr::dot($en) as $key => $value) {
                $arValue = \Illuminate\Support\Arr::get($ar, $key);
                $ruValue = \Illuminate\Support\Arr::get($ru, $key);
                $this->assertNotSame('', trim((string) $arValue), "{$area}.{$key} (ar)");
                $this->assertNotSame('', trim((string) $ruValue), "{$area}.{$key} (ru)");
                // Prose (lower-case Latin letters) must be translated; codes and amounts (AED, USDT, 0x…) may stay.
                if (preg_match('/\p{Ll}{3,}/u', (string) $value) && ! ($value === $arValue && $value === $ruValue)) {
                    $this->assertMatchesRegularExpression('/\p{Arabic}/u', (string) $arValue, "{$area}.{$key}: Arabic draft, not English");
                    $this->assertMatchesRegularExpression('/\p{Cyrillic}/u', (string) $ruValue, "{$area}.{$key}: Russian draft, not English");
                }
            }
        }
        // every hotspot has its three texts
        foreach (Docs::HOTSPOTS as $type => $ids) {
            foreach ($ids as $id) {
                foreach (['name', 'show', 'check'] as $part) {
                    foreach (['en', 'ar', 'ru'] as $locale) {
                        $key = "proposed.d2.anatomy.fields.{$type}.{$id}.{$part}";
                        $this->assertNotSame($key, __($key, [], $locale), "{$locale}: {$key}");
                    }
                }
            }
        }
    }

    public function test_anatomy_copy_makes_no_promises(): void
    {
        $banned = '/\b(guarantee|guaranteed|will win|success rate|law firm|lawyers?|advocates?|free consultation|24\/7|always|never fails)\b|نضمن|مضمون|محام|مكتب محاماة|гарантир|адвокат|юридическая фирма/iu';
        foreach (['en', 'ar', 'ru'] as $locale) {
            $flat = \Illuminate\Support\Arr::dot(require lang_path("{$locale}/proposed-d2/anatomy.php"));
            foreach ($flat as $key => $value) {
                $this->assertDoesNotMatchRegularExpression($banned, (string) $value, "{$locale} anatomy.{$key}");
            }
        }
    }

    public function test_paper_texture_is_a_small_avif_tile(): void
    {
        $path = public_path('images/d2/paper-512.avif');
        $this->assertFileExists($path);
        $this->assertLessThanOrEqual(12 * 1024, filesize($path), 'paper texture ≤ 12 KB');
        $this->assertSame('ftyp', substr((string) file_get_contents($path, false, null, 4, 4), 0, 4));
        $this->assertStringContainsString('avif', (string) file_get_contents($path, false, null, 8, 8));
    }

    private function between(string $haystack, string $start, string $end): string
    {
        $from = strpos($haystack, $start);
        $this->assertNotFalse($from, "missing {$start}");
        $to = strpos($haystack, $end, $from);

        return substr($haystack, $from, ($to === false ? strlen($haystack) : $to + strlen($end)) - $from);
    }
}
