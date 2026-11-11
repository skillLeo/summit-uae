<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Digits;
use App\Design\D2\Prefill;
use App\Support\SiteUrl;
use Database\Seeders\ContentSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Design 2 About page (P06, d2::inner.about; owner: pages-people): the approved P06 content in the DOCX
 * order, the team anchors other pages link to (#team = A02, #team-ahmed …), one profile per approved team
 * member (TEAM 1's <x-d2.team.profile>) with the full approved biography, areas and links, the
 * "Request a consultation with …" prefill links, and no added credentials, years or memberships.
 */
final class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    private const MEMBERS = ['team-ahmed', 'team-mohamed', 'team-sarah'];

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

        return $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/about/")->assertOk()->getContent();
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
    public function test_the_approved_sections_render_in_docx_order_under_one_h1(string $locale): void
    {
        $html = $this->page($locale);
        $x = $this->dom($html);
        $page = content()->page('P06');
        $c = $page['content'];

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame($this->text($page['h1']), $this->text($this->one($x, '//h1')->textContent));
        $main = $this->text($this->one($x, '//main')->textContent);
        $this->assertStringContainsString($this->text($c['hero']['lead']), $main);
        foreach ([...$c['hero']['paragraphs'], ...$c['story']['paragraphs'], $c['team']['intro'], ...$c['scope']['paragraphs'], ...$c['confidentiality']['paragraphs'], ...$c['cta']['paragraphs']] as $paragraph) {
            $this->assertStringContainsString($this->text($paragraph), $main);
        }

        $h2s = [];
        foreach ($x->query('//main//h2') as $h2) {
            $h2s[] = $this->text($h2->textContent);
        }
        $order = [];
        foreach (['story', 'approach', 'process', 'team', 'scope', 'confidentiality', 'cta'] as $key) {
            $heading = $this->text($c[$key]['heading']);
            $this->assertSame(1, $x->query('//main//h2[normalize-space()="'.$heading.'"]')->length, "{$locale} {$key}: one approved H2");
            $order[] = array_search($heading, $h2s, true);
        }
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order, 'DOCX order');

        foreach ($c['approach']['principles'] as $principle) {
            $this->assertSame(1, $x->query('//section[@id="approach"]//h3[normalize-space()="'.$this->text($principle['title']).'"]')->length);
        }
        // The approved step titles keep their own numbering, printed in full; on Arabic pages its digits follow the
        // Arabic digits setting (App\Design\D2\Digits: "١." is shown as "1." by default).
        foreach ($c['process']['steps'] as $step) {
            $title = \App\Design\D2\Digits::localise($step['title'], $locale);
            $this->assertSame(1, $x->query('//section[@id="process"]//h3[normalize-space()="'.$this->text($title).'"]')->length, $title);
        }
        foreach ($c['process']['links'] as $link) {
            $this->assertSame(1, $x->query('//section[@id="process"]//a[@href="'.SiteUrl::target($link['target']).'"]')->length);
        }
    }

    #[DataProvider('locales')]
    public function test_the_team_anchors_and_profiles_are_complete(string $locale): void
    {
        $x = $this->dom($this->page($locale));
        $this->one($x, '//section[@id="team"]');
        $this->assertSame(SiteUrl::page('P06', $locale, 'team'), SiteUrl::target('A02', $locale));

        foreach (content()->teamMembers() as $member) {
            $code = $member['code'];
            $this->assertContains($code, self::MEMBERS);
            $article = $this->one($x, '//section[@id="team"]//article[@id="'.$member['anchor'].'"]');
            $text = $this->text($article->textContent);
            $this->assertSame(1, $x->query('.//h3[normalize-space()="'.$this->text($member['name']).'"]', $article)->length, $code.' name');
            $this->assertStringContainsString($this->text($member['role']), $text);
            foreach ($member['bio'] as $paragraph) {
                $this->assertStringContainsString($this->text($paragraph), $text, $code.' biography');
            }
            $this->assertSame(1, $x->query('.//h4[normalize-space()="'.$this->text($member['areas']['heading']).'"]', $article)->length);
            foreach ($member['areas']['items'] as $item) {
                $this->assertStringContainsString($this->text($item), $text, $code.' areas');
            }
            foreach ($member['links'] as $link) {
                $this->assertSame(1, $x->query('.//a[@href="'.SiteUrl::target($link['target']).'"]', $article)->length, $code.' link '.$link['target']);
            }
            // "Request a consultation with …": a real link that pre-fills the consultant (works without JS).
            $request = $this->one($x, '//article[@id="'.$member['anchor'].'"]//a[@data-consultant="'.$code.'"]');
            $this->assertSame(Prefill::url(['consultant' => $code]), $request->getAttribute('href'));
            $this->assertStringContainsString('consultant='.$code, $request->getAttribute('href'));

            // The approved focus line (the DOCX's "role and expertise descriptor"), verbatim, right after the role; no
            // proposed chips stand in for it on P06 (brief §5.3).
            $role = $this->one($x, '//article[@id="'.$member['anchor'].'"]//p[@class="profile__role"]');
            $focus = $this->one($x, '//article[@id="'.$member['anchor'].'"]//p[@class="profile__focus"]');
            $this->assertSame($this->text($member['focus']), $this->text($focus->textContent), $code.' focus line');
            $this->assertSame($focus, $x->query('following::p[1]', $role)->item(0), $code.': the focus line follows the role');
            $this->assertSame(0, $x->query('.//*[contains(concat(" ",@class," ")," chip ")]', $article)->length, $code.': no chips on P06');
        }

        // The approved team menu (P06-L006 … L008): after "Our Team" and its intro, before the first profile (the DOCX
        // order), never in the head; its own labels ("Ms Sarah Zaki"), each to a profile, as the kit's link rows.
        $this->assertSame(0, $x->query('//header[contains(@class,"inner-head")]//nav[contains(@class,"about-people")]')->length, 'no team menu in the head');
        $this->assertSame(0, $x->query('//header[contains(@class,"inner-head")]//*[contains(@class,"inner-head__aside")]')->length, 'no head aside');
        $menu = content()->page('P06', $locale)['content']['team']['menu'];
        $links = $x->query('//section[@id="team"]/div[@class="container"]/nav[@class="about-people"][@aria-labelledby="team-title"]//a[contains(@class,"link-row")]');
        $this->assertSame(count($menu), $links->length);
        foreach ($menu as $i => $item) {
            $this->assertSame('#'.$item['target'], $links->item($i)->getAttribute('href'));
            $this->assertSame($this->text($item['label']), $this->text($links->item($i)->textContent));
        }
        $this->assertSame(1, $x->query('//section[@id="team"]//div[contains(@class,"section__head")]/following-sibling::nav[@class="about-people"]/following-sibling::div[@class="about-members"]')->length, 'head, menu, profiles');

        // Sarah Zaki (D43): the "photo pending" panel with her initials until a portrait is supplied, never a placeholder photo.
        $sarah = $this->one($x, '//article[@id="team-sarah"]');
        $this->assertSame(1, $x->query('.//*[contains(@class,"portrait--pending")][@data-photo-pending="true"]', $sarah)->length);
        $this->assertSame(1, $x->query('.//*[contains(@class,"portrait__panel")]', $sarah)->length);
        $this->assertSame(0, $x->query('.//img[contains(@class,"portrait__img")]', $sarah)->length);
        $ahmed = $this->one($x, '//article[@id="team-ahmed"]');
        $this->assertSame(1, $x->query('.//img[contains(@class,"portrait__img")][@alt]', $ahmed)->length);
        // D42: every portrait names its member; one nonce'd <style> places the photos on the shared eye line.
        $this->assertSame(1, $x->query('//style[@data-portraits]')->length);
        foreach (['team-ahmed', 'team-mohamed'] as $code) {
            $this->assertSame(1, $x->query('//*[contains(@class,"portrait--photo")][@data-member="'.$code.'"]')->length, $code.': the profile');
        }
        // D44: the approved working-languages line as plain text, exactly as written; no language chips. The DOCX order
        // (brief §5.3): after the areas, before the member's links; not on the card.
        foreach (content()->teamMembers() as $member) {
            $line = $this->one($x, '//article[@id="'.$member['anchor'].'"]//p[contains(@class,"profile__languages")]');
            $this->assertSame($this->text($member['languages']), $this->text($line->textContent));
            $this->assertStringContainsString('about-member__languages', $line->getAttribute('class'));
            $this->assertSame(0, $x->query('//article[@id="'.$member['anchor'].'"]//div[@class="about-member__card"]//p[contains(@class,"profile__languages")]')->length);
            $this->assertSame(1, $x->query('//article[@id="'.$member['anchor'].'"]//div[@class="about-member__areas"]/following::p[contains(@class,"about-member__languages")][1][preceding::div[@class="about-member__areas"][1]/ancestor::article[@id="'.$member['anchor'].'"]]')->length, 'the languages follow the areas');
            if ($member['links'] !== []) {
                $this->assertSame(1, $x->query('following-sibling::ul[contains(@class,"about-member__links")]', $line)->length, 'the links follow the languages');
            }
            $this->assertSame(0, $x->query('//article[@id="'.$member['anchor'].'"]//*[contains(concat(" ",@class," ")," chip ")]')->length);
        }
    }

    /**
     * D45: the story's text column and the biographies in one body size (no first paragraph set in the Lead size), and
     * "Our Team" at the standard section rhythm (no section of the page shortens its padding).
     */
    public function test_about_body_text_uses_one_size_and_the_standard_rhythm(): void
    {
        $css = (string) file_get_contents(resource_path('css/d2/pages/about.css'));
        foreach (['.about-split__body', '.about-member__bio'] as $block) {
            $this->assertSame(1, preg_match('/\n  '.preg_quote($block, '/').' \{(.*?)\n  \}/s', $css, $rule), $block);
            $this->assertStringContainsString('font-size: var(--fs-body);', $rule[1], $block);
            $this->assertStringNotContainsString('--fs-lead', $rule[1], $block);
        }
        $this->assertStringNotContainsString('.about-story__body p:first-child', $css);
        $this->assertDoesNotMatchRegularExpression('/\.about-team[^{]*\{[^}]*padding-block/', $css, 'Our Team keeps the section padding');
        $home = (string) file_get_contents(resource_path('css/d2/sections/about.css'));
        $this->assertStringNotContainsString('--fs-lead', $home, 'home section 05: one body size');
    }

    public function test_no_credentials_years_or_memberships_are_added(): void
    {
        $html = $this->page('en');
        // The approved focus line is shown as written, once (brief §5.3; it opens with the approved degree): nothing is
        // added to it. Its middle dot keeps to the previous word (no line starts with it).
        $mohamed = collect(content()->teamMembers())->firstWhere('code', 'team-mohamed');
        $this->assertSame(1, substr_count(str_replace("\u{00A0}", ' ', html_entity_decode($html)), $mohamed['focus']), 'the approved focus line, once');
        $this->assertStringContainsString("Law\u{00A0}· Civil", $html);
        $main = mb_strtolower(strip_tags($html));
        foreach (['years of experience', 'law firm', 'member of', 'licensed lawyer', 'award', 'free consultation'] as $phrase) {
            $this->assertStringNotContainsString($phrase, $main, $phrase);
        }
    }

    public function test_the_cta_uses_the_approved_channels(): void
    {
        $x = $this->dom($this->page('en'));
        $cta = $this->one($x, '//section[@id="about-cta"]');
        $this->assertSame(1, $x->query('.//a[@href="'.SiteUrl::consultation().'"]', $cta)->length);
        $this->assertSame(1, $x->query('.//a[@href="'.setting('whatsapp_url').'"][@target="_blank"]', $cta)->length);
        $this->assertSame(1, $x->query('.//a[@href="tel:+971524666191"]//bdi[@dir="ltr"]', $cta)->length);
        $this->assertSame(1, $x->query('.//a[@href="mailto:info@summitlegaluae.com"]//bdi[@dir="ltr"]', $cta)->length);
        // Design 2.1 (one contact-channel pattern): telephone and e-mail are the kit's channel rows (glyph + value),
        // not secondary buttons; the request and WhatsApp buttons share one height (.pp-cta__buttons).
        $this->assertSame(2, $x->query('.//ul[contains(@class,"pp-cta__channels")]/li/a[contains(@class,"channel ")]', $cta)->length);
        $this->assertSame(0, $x->query('.//a[starts-with(@href,"tel:") or starts-with(@href,"mailto:")][contains(@class,"btn")]', $cta)->length);
        $this->assertSame(2, $x->query('.//div[@class="pp-cta__buttons"]/a[contains(@class,"btn")]', $cta)->length);
        foreach (content()->page('P06')['content']['cta']['links'] as $link) {
            $this->assertSame(1, $x->query('.//a[@href="'.SiteUrl::target($link['target']).'"]', $cta)->length);
        }
    }

    #[DataProvider('locales')]
    public function test_static_blocks_are_not_cards_and_related_page_links_are_link_rows(string $locale): void
    {
        // Design 2.1 (brief §3.5): cards are for interactive containers and clickable listings. The six principles,
        // the four steps and the two terms panels are static text: plain columns under a hairline and band panels.
        $x = $this->dom($this->page($locale));
        $c = content()->page('P06')['content'];

        $this->assertSame(count($c['approach']['principles']), $x->query('//section[@id="approach"]//ul[@class="about-principles"]/li[@class="about-principle"]/h3[@class="about-principle__title"]')->length);
        $this->assertSame(0, $x->query('//section[@id="approach"]//*[contains(concat(" ", @class, " "), " card ")]')->length, 'no card in the principles');
        $this->assertSame(count($c['process']['steps']), $x->query('//section[@id="process"]//ol[contains(@class,"pp-steps--row")]/li[@class="pp-step"]')->length);

        // One treatment per role: the related-page links of #process and the terms panels' Legal Notice link are link
        // rows, as on every inner page; no secondary button and no underlined text link stands in for them.
        foreach ($c['process']['links'] as $link) {
            $this->assertSame(1, $x->query('//section[@id="process"]//ul[contains(@class,"about-process__links")]//a[@class="link-row link-row--page"][@href="'.SiteUrl::target($link['target']).'"]')->length, $link['target']);
        }
        $this->assertSame(0, $x->query('//section[@id="process"]//a[contains(@class,"btn")]')->length);
        $terms = $x->query('//div[contains(@class,"about-terms")]//section[contains(@class,"pp-panel")]');
        $this->assertSame(2, $terms->length);
        $this->assertGreaterThanOrEqual(1, $x->query('//div[contains(@class,"about-terms")]//ul[contains(@class,"pp-panel__links")]//a[@class="link-row link-row--page"]')->length);
        $this->assertSame(0, $x->query('//div[contains(@class,"about-terms")]//a[contains(@class,"t-link")]')->length);

        $css = file_get_contents(resource_path('css/d2/pages/about.css'));
        $this->assertMatchesRegularExpression('/\.pp-panel \{[^}]*background: var\(--band\);/', $css, 'the terms panels are band panels');
        $this->assertMatchesRegularExpression('/\.about-principle \{[^}]*border-block-start: var\(--line-w\) solid var\(--line-strong\);/', $css, 'a hairline, no card edge');
        $this->assertDoesNotMatchRegularExpression('/\.about-principle \{[^}]*border-radius/', $css);
    }

    #[DataProvider('locales')]
    public function test_step_titles_keep_their_approved_number_in_the_heading_role_and_names_take_the_minor_role(string $locale): void
    {
        // Findings (brief §3.2 roles, §3.6 rule 12): the approved step number ("1.") sat on its own line inside the ink
        // H3 in ink-3 at 15 / 600; the head's portrait names were 15 / 600. The number is part of the approved title,
        // printed as written in the heading's own role and colour; names take the Minor heading role (16 / 600).
        $x = $this->dom($this->page($locale));
        $steps = content()->page('P06')['content']['process']['steps'];
        $titles = $x->query('//section[@id="process"]//h3[@class="pp-step__title"]');
        $this->assertSame(count($steps), $titles->length);
        foreach ($steps as $i => $step) {
            // (Arabic digits follow the site setting at render time, App\Design\D2\Digits.)
            $this->assertSame($this->text(Digits::localise((string) $step['title'], $locale)), $this->text($titles->item($i)->textContent), "step {$i}: the approved title, number included");
        }
        $css = (string) file_get_contents(resource_path('css/d2/pages/about.css'));
        preg_match('/\.pp-step__num \{[^}]*\}/', $css, $num);
        $this->assertNotEmpty($num);
        $this->assertDoesNotMatchRegularExpression('/color|font-size|font-weight|display: block/', $num[0], 'the number inherits the heading');
        // The team menu (Phase 2: under "Our Team", no longer the head's portrait names) is the kit's link rows.
        $this->assertSame(3, $x->query('//nav[@class="about-people"]//a[@class="link-row link-row--page about-person"]')->length);
        // P07's "What Happens After You Contact Us" is its own section now (the Contact DOCX order, Phase 2): its steps
        // are H3 under the section's H2, in the shared row of steps (.pp-steps--row), so they keep the H3 role.
        $contact = (string) file_get_contents(resource_path('css/d2/pages/contact.css'));
        $this->assertDoesNotMatchRegularExpression('/\.contact-after \.pp-step__title \{/', $contact, 'P07 steps: the shared H3 role');
        $this->assertMatchesRegularExpression('/\.contact-after \.pp-steps--row \{\s*grid-template-columns: repeat\(3, minmax\(0, 1fr\)\);/', $contact, 'P07: three steps on one row from 1280 px');
    }
}
