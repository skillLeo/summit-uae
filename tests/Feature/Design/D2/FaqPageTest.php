<?php

namespace Tests\Feature\Design\D2;

use App\Support\SiteUrl;
use Database\Seeders\ContentSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Design 2 FAQ page (P05, d2::inner.faq; owner: pages-people): the approved head, the approved "Choose a
 * topic" menu, the FAQ browser with all 74 approved questions under their Design 1 anchors (faq-01 … faq-74,
 * cat-01 … cat-12), the consultation and scope blocks, in every language.
 */
final class FaqPageTest extends TestCase
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

    private function page(string $locale): string
    {
        app()->setLocale($locale);

        return $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/faq/")->assertOk()->getContent();
    }

    private function dom(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
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

    private function text(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    #[DataProvider('locales')]
    public function test_the_head_carries_the_approved_title_lead_paragraphs_and_cta(string $locale): void
    {
        $html = $this->page($locale);
        $x = $this->dom($html);
        $page = content()->page('P05');
        $c = $page['content'];

        $this->assertStringContainsString('data-design="2"', $html);
        $this->assertSame($this->text($page['h1']), $this->text($this->one($x, '//h1')->textContent));
        $main = $this->text($this->one($x, '//main')->textContent);
        $this->assertStringContainsString($this->text($c['hero']['lead']), $main);
        foreach ($c['hero']['paragraphs'] as $paragraph) {
            $this->assertStringContainsString($this->text($paragraph), $main, 'both approved hero paragraphs');
        }
        foreach ([$c['search']['heading'], $c['category_menu']['heading'], $c['consultation']['heading'], $c['scope']['heading']] as $heading) {
            $this->assertSame(1, $x->query('//main//h2[normalize-space()="'.$this->text($heading).'"]')->length, $heading);
        }
        // P07 is not released: the hero CTA goes to the home form.
        $cta = $this->one($x, '//header[contains(@class,"inner-head")]//a[contains(@class,"btn")][normalize-space(.//*[@class="btn__label"])="'.$this->text($c['hero']['cta']['label']).'"]');
        $this->assertSame(SiteUrl::consultation(), $cta->getAttribute('href'));
        $this->assertStringContainsString('#consultation-form', $cta->getAttribute('href'));
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
    }

    #[DataProvider('locales')]
    public function test_every_question_and_category_keeps_its_design_one_anchor(string $locale): void
    {
        $x = $this->dom($this->page($locale));
        $faqs = content()->faqs();
        $this->assertCount(74, $faqs);

        foreach ($faqs as $faq) {
            $item = $this->one($x, '//details[@id="'.$faq['anchor'].'"]');
            $this->assertStringContainsString($this->text($faq['question']), $this->text($item->textContent), $faq['anchor']);
            $this->assertStringContainsString($this->text($faq['answer'][0]['text']), $this->text($item->textContent), $faq['anchor'].' answer');
        }
        for ($i = 1; $i <= 74; $i++) {
            $this->one($x, sprintf('//*[@id="faq-%02d"]', $i));
        }
        foreach (content()->faqCategories() as $category) {
            $panel = $this->one($x, '//*[@id="'.$category['anchor'].'"]');
            $this->assertSame($category['anchor'], $panel->getAttribute('id'));
        }
        for ($i = 1; $i <= 12; $i++) {
            $this->one($x, sprintf('//*[@id="cat-%02d"]', $i));
        }
        $this->one($x, '//*[@id="faq-browser"][@data-faq-browser]');
        $this->one($x, '//input[@id="faq-browser-search"][@data-faq-search]');
    }

    #[DataProvider('locales')]
    public function test_the_topic_index_is_the_approved_category_menu_with_counts(string $locale): void
    {
        $x = $this->dom($this->page($locale));
        $menu = content()->page('P05')['content']['category_menu'];
        $counts = collect(content()->faqs())->countBy('category');

        $nav = $this->one($x, '//nav[@id="faq-topics"]');
        $this->assertSame('faq-topics-title', $nav->getAttribute('aria-labelledby'));
        $links = $x->query('.//a[@data-faq-topic]', $nav);
        $this->assertSame(12, $links->length);
        foreach ($menu['items'] as $i => $item) {
            /** @var DOMElement $link */
            $link = $links->item($i);
            $this->assertSame('#'.$item['target'], $link->getAttribute('href'));
            $label = $x->query('.//*[contains(@class,"faq-topic__label")]', $link)->item(0);
            $this->assertSame($this->text($item['label']), $this->text($label->textContent));
            $count = $x->query('.//*[contains(@class,"faq-topic__count")]', $link)->item(0);
            $this->assertInstanceOf(DOMElement::class, $count);
            $this->assertSame('proposed', $count->getAttribute('data-copy-status'));
            $this->assertSame($this->text(trans_choice('proposed.d2.faq_page.topic_count', $counts[$item['target']], ['count' => $counts[$item['target']]], $locale)), $this->text($count->textContent));
        }
        $this->one($x, '//a[@href="#faq-topics"][normalize-space(.//*[@class="btn__label"])="'.$this->text(ui('faq.back_to_categories')).'"]');
    }

    public function test_the_counts_use_each_language_plural_forms(): void
    {
        $this->assertSame('6 questions', trans_choice('proposed.d2.faq_page.topic_count', 6, ['count' => 6], 'en'));
        $this->assertSame('4 вопроса', trans_choice('proposed.d2.faq_page.topic_count', 4, ['count' => 4], 'ru'));
        $this->assertSame('5 вопросов', trans_choice('proposed.d2.faq_page.topic_count', 5, ['count' => 5], 'ru'));
        $this->assertSame('6 أسئلة', trans_choice('proposed.d2.faq_page.topic_count', 6, ['count' => 6], 'ar'));
        $this->assertSame('11 سؤالًا', trans_choice('proposed.d2.faq_page.topic_count', 11, ['count' => 11], 'ar'));
    }

    #[DataProvider('locales')]
    public function test_the_consultation_and_scope_blocks_use_the_approved_channels_and_links(string $locale): void
    {
        $x = $this->dom($this->page($locale));
        $c = content()->page('P05')['content'];

        $ask = $this->one($x, '//section[@id="faq-ask"]');
        $this->assertSame(1, $x->query('.//a[@href="'.SiteUrl::consultation().'"]', $ask)->length);
        $whatsapp = $this->one($x, '//section[@id="faq-ask"]//a[starts-with(@href,"https://wa.me/971524666191?text=")]');
        $this->assertSame(setting('whatsapp_url'), $whatsapp->getAttribute('href'));
        $this->assertSame('_blank', $whatsapp->getAttribute('target'));
        $this->assertSame('+971 52 466 6191', $this->text($this->one($x, '//section[@id="faq-ask"]//a[@href="tel:+971524666191"]//bdi[@dir="ltr"]')->textContent));
        $this->assertSame('info@summitlegaluae.com', $this->text($this->one($x, '//section[@id="faq-ask"]//a[@href="mailto:info@summitlegaluae.com"]//bdi[@dir="ltr"]')->textContent));
        // Design 2.1 (one contact-channel pattern): the kit's channel rows, never buttons labelled with the number
        $this->assertSame(2, $x->query('//section[@id="faq-ask"]//ul[contains(@class,"pp-cta__channels")]/li/a[contains(@class,"channel ")]')->length);
        $this->assertSame(0, $x->query('//section[@id="faq-ask"]//a[starts-with(@href,"tel:") or starts-with(@href,"mailto:")][contains(@class,"btn")]')->length);
        foreach ($c['consultation']['paragraphs'] as $paragraph) {
            $this->assertStringContainsString($this->text($paragraph), $this->text($ask->textContent));
        }

        $scope = $this->one($x, '//section[@id="faq-scope"]');
        foreach ($c['scope']['links'] as $link) {
            $this->assertSame(1, $x->query('.//a[@href="'.SiteUrl::target($link['target']).'"]', $scope)->length, $link['target']);
        }
    }

    public function test_the_page_has_one_h1_and_no_banned_claims(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            $html = $this->page($locale);
            $this->assertSame(1, substr_count($html, '<h1'), $locale);
        }
        $main = mb_strtolower(strip_tags($this->page('en')));
        foreach (['law firm', 'years of experience', 'free consultation', '24/7', 'success rate', 'award'] as $phrase) {
            $this->assertStringNotContainsString($phrase, $main, $phrase);
        }
    }
}
