<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Prefill;
use App\Support\SiteUrl;
use Database\Seeders\ContentSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Design 2 Contact page (P07, d2::inner.contact; owner: form-contact-faq): the approved P07 content in the Contact
 * DOCX order (Design 2.1, Phase 2), the full
 * consultation wizard (TEAM 1's <x-d2.wizard.form>, the same backend, field names, idempotency key and
 * states as the home page) under #consultation-form, the contact card (approved channels, hours sentence
 * with the live status, languages; no address, no map), prefill from a team card, and the release rule
 * (noindex and the home form for every other page until contact_page_released).
 */
final class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    /** The endpoint's own field names (StoreEnquiryRequest + technical inputs). */
    private const POSTED = ['_token', 'idempotency_key', 'website', 'name', 'phone', 'email', 'preferred_language', 'matter_type', 'summary', 'deadline_date', 'deadline_details', 'enquiry_consent'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
        config(['summit.features.attachments_enabled' => false]);
    }

    /** @return array<string, array{string}> */
    public static function locales(): array
    {
        return ['en' => ['en'], 'ar' => ['ar'], 'ru' => ['ru']];
    }

    private function page(string $locale, string $query = ''): string
    {
        app()->setLocale($locale);

        return $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/contact/{$query}")->assertOk()->getContent();
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
    public function test_the_page_holds_the_full_wizard_on_the_enquiry_endpoint(string $locale): void
    {
        $x = $this->dom($this->page($locale));

        $section = $this->one($x, '//section[@id="consultation-form"]');
        $form = $this->one($x, '//section[@id="consultation-form"]//form[@data-wizard-form]');
        $this->assertSame(route('enquiries.store'), $form->getAttribute('action'));
        $this->assertSame('post', strtolower($form->getAttribute('method')));
        // The Contact file's form section: its H2 "Consultation Request Details", the intro and the required note.
        $this->assertSame('consultation-title', $section->getAttribute('aria-labelledby'));
        $this->one($x, '//section[@id="consultation-form"]//h2[@id="consultation-title"][normalize-space()="'.$this->text(ui('form.heading')).'"]');
        $this->assertSame('consultation-title', $form->getAttribute('aria-labelledby'));
        foreach (['form.intro', 'form.required_note'] as $key) {
            $this->assertSame(1, $x->query('//section[@id="consultation-form"]//div[contains(@class,"section__head")]/p[normalize-space()="'.$this->text(ui($key)).'"]')->length, $key);
        }

        $names = [];
        foreach ($x->query('.//input[@name] | .//select[@name] | .//textarea[@name]', $form) as $control) {
            /** @var DOMElement $control */
            if (! $control->hasAttribute('form')) {
                $names[] = $control->getAttribute('name');
            }
        }
        $this->assertEqualsCanonicalizing(self::POSTED, array_values(array_unique($names)), 'the endpoint\'s field names only');
        $key = $this->one($x, '//form[@data-wizard-form]//input[@name="idempotency_key"]')->getAttribute('value');
        $this->assertTrue(Str::isUuid($key));
        $this->assertSame('-1', $this->one($x, '//input[@name="website"]')->getAttribute('tabindex'));
        $this->one($x, '//*[@data-wizard][@x-data="d2Wizard"]');
        $this->one($x, '//script[@data-wizard-data]');
        $this->assertSame(1, $x->query('//*[@id="consultation-form"]')->length, 'one form anchor');
    }

    public function test_every_render_has_a_fresh_idempotency_key(): void
    {
        $key = fn () => $this->one($this->dom($this->page('en')), '//input[@name="idempotency_key"]')->getAttribute('value');
        $this->assertNotSame($key(), $key());
    }

    #[DataProvider('locales')]
    public function test_the_contact_card_shows_the_approved_channels_hours_and_languages_and_no_address(string $locale): void
    {
        $html = $this->page($locale);
        $x = $this->dom($html);
        $details = content()->page('P07')['content']['details'];

        // "Contact Details" (the Contact file's H2) right after the hero, in the head's aside.
        $aside = $this->one($x, '//header[contains(@class,"inner-head")]//div[contains(concat(" ", @class, " "), " contact-card ")]');
        $this->assertSame('contact-details-title', $aside->getAttribute('aria-labelledby'));
        $this->one($x, '//h2[@id="contact-details-title"][normalize-space()="'.$this->text($details['heading']).'"]');
        $this->assertSame(1, $x->query('.//a[@href="tel:+971524666191"]', $aside)->length);
        $this->assertSame(1, $x->query('.//a[@href="mailto:info@summitlegaluae.com"]', $aside)->length);
        $this->assertSame(1, $x->query('.//a[@href="'.setting('whatsapp_url').'"]', $aside)->length);
        $this->assertStringContainsString('https://wa.me/971524666191?text=', setting('whatsapp_url'));
        $this->assertSame('+971 52 466 6191', $this->text($x->query('.//bdi[@dir="ltr"]', $aside)->item(0)->textContent));

        $text = $this->text($aside->textContent);
        foreach (['hours', 'languages', 'after_hours'] as $key) {
            // The Contact file's own hours line (App\Design\D2\Hours::sentence('contact')), its digits per the
            // Arabic digits setting (the Arabic line is written with ٩ and ٦; shown as 9 and 6 by default).
            $this->assertStringContainsString($this->text(\App\Design\D2\Digits::localise($details[$key], $locale)), $text, $key);
        }
        // The approved hours sentence is plain text; the live status sits next to it.
        $this->assertSame(1, $x->query('.//*[@data-hours-status]', $aside)->length);
        // The Contact file's order: Telephone, WhatsApp, Email, the languages line, the office hours, the after-hours
        // paragraph (in the text column, no glyph of its own: brief §3.6 rule 5).
        $labels = [];
        foreach ($x->query('.//*[contains(@class,"contact-card__label")]', $aside) as $label) {
            $labels[] = $this->text($label->textContent);
        }
        $this->assertSame([$details['telephone_label'], $details['whatsapp_label'], $details['email_label']], $labels);
        $facts = [];
        foreach ($x->query('.//p[contains(@class,"contact-card__fact")]', $aside) as $fact) {
            $facts[] = $this->text($fact->textContent);
        }
        $this->assertCount(3, $facts);
        $this->assertStringStartsWith($this->text(str_replace(' · ', "\u{00A0}· ", $details['languages'])), $facts[0]);
        $this->assertStringStartsWith($this->text(\App\Design\D2\Digits::localise($details['hours'], $locale)), str_replace("\u{00A0}", ' ', $facts[1]));
        $this->assertSame($this->text(\App\Design\D2\Digits::localise($details['after_hours'], $locale)), $facts[2]);
        $note = $this->one($x, '//*[contains(@class,"contact-card__fact--note")]');
        $this->assertSame(0, $x->query('.//*[@data-icon]', $note)->length, 'no glyph on the after-hours note');

        $main = mb_strtolower($this->text($this->one($x, '//main')->textContent));
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertSame(0, preg_match('/google\.[a-z.]+\/maps|maps\.apple|openstreetmap/i', $html));
        foreach (['24/7', 'free consultation', 'law firm', 'within 24 hours'] as $phrase) {
            $this->assertStringNotContainsString($phrase, $main, $phrase);
        }
    }

    #[DataProvider('locales')]
    public function test_the_contact_card_uses_the_approved_p07_whatsapp_action_and_the_home_card_keeps_the_cta(string $locale): void
    {
        $details = content()->page('P07', $locale)['content']['details'];
        $this->assertNotSame('', trim((string) ($details['whatsapp_cta'] ?? '')));

        $x = $this->dom($this->page($locale));
        $row = $this->one($x, '//header[contains(@class,"inner-head")]//a[contains(@class,"contact-card__channel--whatsapp")]');
        $this->assertSame($this->text($details['whatsapp_cta']), $this->text($this->one($x, '//header[contains(@class,"inner-head")]//a[contains(@class,"contact-card__channel--whatsapp")]//*[contains(@class,"contact-card__value")]')->textContent));
        $this->assertSame(setting('whatsapp_url'), $row->getAttribute('href'));

        $home = $this->dom($this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/")->assertOk()->getContent());
        $this->assertSame($this->text(ui('cta.whatsapp')), $this->text($this->one($home, '//section[@id="consultation-form"]//a[contains(@class,"contact-card__channel--whatsapp")]//*[contains(@class,"contact-card__value")]')->textContent));
    }

    #[DataProvider('locales')]
    public function test_the_approved_p07_content_renders_with_one_h1_and_a_correct_heading_order(string $locale): void
    {
        $html = $this->page($locale);
        $x = $this->dom($html);
        $page = content()->page('P07');
        $c = $page['content'];

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame($this->text($page['h1']), $this->text($this->one($x, '//h1')->textContent));
        $main = $this->text($this->one($x, '//main')->textContent);
        foreach ([$c['lead'], ...$c['paragraphs'], ...$c['helps']['items'], $c['helps']['note'], $c['final']['text'], $c['final']['scope']] as $text) {
            $this->assertStringContainsString($this->text($text), $main);
        }
        // The approved numbering is printed in full; on Arabic pages its digits follow the Arabic digits setting
        // (App\Design\D2\Digits: "١." is shown as "1." by default).
        foreach ($c['after']['steps'] as $step) {
            $title = \App\Design\D2\Digits::localise($step['title'], $locale);
            $this->assertSame(1, $x->query('//section[@id="contact-after"]//h3[normalize-space()="'.$this->text($title).'"]')->length, $title);
        }

        // The Contact DOCX order of the H2 sections (brief §5.3): Contact Details, What Happens After You Contact Us,
        // Consultation Request Details, What Helps Us Understand Your Matter, Questions Before Contacting Us, Contact
        // Us in the Way That Suits You.
        $h2 = [];
        foreach ($x->query('//main//h2') as $heading) {
            $h2[] = $this->text($heading->textContent);
        }
        $this->assertSame(array_map(fn ($t) => $this->text(\App\Design\D2\Digits::localise($t, $locale)), [
            $c['details']['heading'], $c['after']['heading'], ui('form.heading'), $c['helps']['heading'], $c['faq']['heading'], $c['final']['heading'],
        ]), $h2);
        foreach ($c['faq']['items'] as $i => $item) {
            $details = $this->one($x, '//details[@id="contact-faq-'.($i + 1).'"]');
            $this->assertStringContainsString($this->text($item['question']), $this->text($details->textContent));
            $this->assertStringContainsString($this->text($item['answer']), $this->text($details->textContent));
        }
        foreach ($c['links'] as $link) {
            $this->assertSame(1, $x->query('//section[@id="contact-questions"]//a[@href="'.SiteUrl::target($link['target']).'"]')->length, $link['target']);
        }

        // Headings never skip a level inside the page content.
        $levels = [];
        foreach ($x->query('//main//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]') as $heading) {
            $levels[] = (int) substr($heading->nodeName, 1);
        }
        for ($i = 1, $n = count($levels); $i < $n; $i++) {
            $this->assertLessThanOrEqual(1, $levels[$i] - $levels[$i - 1], "h{$levels[$i - 1]} → h{$levels[$i]}");
        }

        // The hero CTA stays on this page; WhatsApp opens the approved draft.
        $this->one($x, '//header[contains(@class,"inner-head")]//a[@href="#consultation-form"]');
        $this->assertSame(1, $x->query('//header[contains(@class,"inner-head")]//a[contains(concat(" ", @class, " "), " btn ")][@href="'.setting('whatsapp_url').'"]')->length);
        // (the head's aside holds the Contact Details card, whose WhatsApp row opens the same draft)
        $this->assertSame(2, $x->query('//header[contains(@class,"inner-head")]//a[@href="'.setting('whatsapp_url').'"]')->length);
    }

    public function test_a_team_card_prefill_reaches_the_wizard_on_this_page(): void
    {
        $x = $this->dom($this->page('en', '?consultant=team-sarah&service=S04'));
        $data = json_decode($this->one($x, '//script[@data-wizard-data]')->textContent, true);
        $this->assertSame(['service' => 'S04', 'consultant' => 'team-sarah'], $data['prefill']);
        $this->assertSame('S04', $this->one($x, '//select[@name="matter_type"]//option[@selected]')->getAttribute('value'));
        $sarah = collect(content()->teamMembers())->firstWhere('code', 'team-sarah');
        $this->assertStringContainsString($sarah['name'], $this->one($x, '//textarea[@name="summary"]')->textContent, 'without JavaScript the context is visible, editable text');

        // Unknown or array-shaped values are ignored.
        $y = $this->dom($this->page('en', '?consultant[]=x&service=S99'));
        $this->assertSame([], json_decode($this->one($y, '//script[@data-wizard-data]')->textContent, true)['prefill']);
    }

    public function test_until_released_the_page_is_noindex_and_other_pages_keep_the_home_form(): void
    {
        config(['summit.contact_page_released' => false]);
        $html = $this->page('en');
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
        $this->assertSame(SiteUrl::home('en', 'consultation-form'), SiteUrl::consultation('en'));
        $this->assertStringStartsWith(SiteUrl::home('en'), Prefill::url(['consultant' => 'team-ahmed'], 'en'));

        $about = $this->withUnencryptedCookies(['summit_design' => '2'])->get('/en/about/')->assertOk()->getContent();
        $this->assertStringNotContainsString('href="'.SiteUrl::page('P07', 'en').'#consultation-form"', $about);
    }

    public function test_once_released_the_page_still_renders_and_prefill_links_target_it(): void
    {
        config(['summit.contact_page_released' => true]);
        $x = $this->dom($this->page('en'));
        $this->one($x, '//section[@id="consultation-form"]//form[@data-wizard-form]');
        $this->assertSame(SiteUrl::page('P07', 'en', 'consultation-form'), SiteUrl::consultation('en'));
        $this->assertStringStartsWith(SiteUrl::page('P07', 'en'), Prefill::url(['consultant' => 'team-ahmed'], 'en'));
    }

    #[DataProvider('locales')]
    public function test_useful_links_and_the_closing_channels_follow_the_kit(string $locale): void
    {
        $x = $this->dom($this->page($locale));
        $c = content()->page('P07')['content'];

        // "Useful Links" beside questions: the FAQ browser's look (P05, home), one treatment for the role: the Minor
        // heading and underlined text links in a band panel, not bordered link rows.
        $aside = $this->one($x, '//section[@id="contact-questions"]//aside[contains(@class,"contact-questions__aside")]');
        $this->assertSame(1, $x->query('./h3[contains(@class,"faq-aside__title")]', $aside)->length);
        $this->assertSame(count($c['links']), $x->query('./ul[@class="faq-links"]/li/a[@class="faq-link"]/span[@class="faq-link__label"]', $aside)->length);
        $this->assertSame(0, $x->query('.//a[contains(@class,"link-row")]', $aside)->length);
        // the H2 stands above both columns, so the questions and the panel start on one line
        $this->assertSame(1, $x->query('//section[@id="contact-questions"]/div[@class="container"]/h2[@id="contact-questions-title"]')->length);

        // "Call Us" and "Email Us" (this page's approved labels) are the kit's channel rows: glyph, then the label.
        $final = $this->one($x, '//section[@id="contact-final"]');
        $phone = $this->one($x, './/ul[contains(@class,"pp-cta__channels")]/li/a[@class="channel channel--phone"][@href="tel:+971524666191"]', $final);
        $email = $this->one($x, './/ul[contains(@class,"pp-cta__channels")]/li/a[@class="channel channel--email"][@href="mailto:info@summitlegaluae.com"]', $final);
        $this->assertSame($this->text($c['final']['call_label']), $this->text($phone->textContent));
        $this->assertSame($this->text($c['final']['email_label']), $this->text($email->textContent));
        $this->assertSame(0, $x->query('.//a[contains(@class,"btn--secondary")]', $final)->length);
    }
}
