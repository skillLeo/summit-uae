<?php

namespace App\Content\Import\Mappers;

use App\Content\Import\Cursor;
use App\Content\Import\ImportException;
use App\Content\Import\Mapper;

/**
 * 01_Homepage*.docx → P01 page copy, shared header/footer strings, service and guide card copy,
 * case previews, specialist sites (X01–X06) and the WA01 draft.
 *
 * Reference mapper: anchors on the English red markers that exist in all three files
 * ("HEADER |", "SECTION 0n |", "Card S0n |", "Guide card G0n |", "H-L0nn |").
 */
final class HomepageMapper extends Mapper
{
    private const SERVICES = ['S01', 'S02', 'S03', 'S04', 'S05', 'S06', 'S07', 'S08', 'S09', 'S10', 'S11'];

    private const GUIDES = ['G01', 'G02', 'G03', 'G04', 'G05', 'G06', 'G07', 'G08', 'G09'];

    public function sources(): array
    {
        return ['home' => '01_Homepage'];
    }

    public function map(array $docs, string $locale): array
    {
        $c = $docs['home'];
        $ui = [];
        $settings = [];
        $page = [];
        $services = [];
        $guides = [];
        $cases = [];
        $sites = [];

        // ---------------------------------------------------------------- header
        $c->expectNote('/^HEADER \|/');
        $ui['brand.name'] = $c->takeHeading(2, 'ui.brand.name');
        $nav = ['nav.home', 'nav.practice_areas', 'nav.resources', 'nav.case_studies', 'nav.faq', 'nav.about', 'nav.contact'];
        foreach ($nav as $i => $key) {
            $c->expectNote('/^H-L00'.($i + 1).' \|/');
            $ui[$key] = $c->takeLink('ui.'.$key)['label'];
        }
        foreach (['lang.ar' => 'H-L008', 'lang.en' => 'H-L009', 'lang.ru' => 'H-L010'] as $key => $marker) {
            $c->expectNote('/^'.$marker.' \|/');
            $ui[$key] = $c->takeLink('ui.'.$key)['label'];
        }
        $c->expectNote('/^Mobile controls\./');
        foreach (['a11y.open_menu', 'a11y.close_menu', 'a11y.choose_language', 'a11y.skip_to_content', 'brand.logo_alt'] as $key) {
            $ui[$key] = $c->take('ui.'.$key);
        }

        // ---------------------------------------------------------------- 01 hero
        $c->expectNote('/^SECTION 01 \|/');
        $page['h1'] = $c->takeHeading(1, 'P01.h1');
        $page['content']['hero'] = [
            'lead' => $c->take('P01.hero.lead'),
            'body' => $c->take('P01.hero.body'),
        ];
        $c->expectNote('/^H-L011 \|/');
        $ui['cta.request_consultation'] = $c->takeLink('ui.cta.request_consultation')['label'];
        $c->expectNote('/^H-L012 \|/');
        $wa = $c->takeLink('ui.cta.whatsapp');
        $ui['cta.whatsapp'] = $wa['label'];
        $settings['whatsapp_url'] = $wa['href'];

        // ---------------------------------------------------------------- 02 services
        $c->expectNote('/^SECTION 02 \|/');
        $page['content']['services'] = [
            'heading' => $c->takeHeading(2, 'P01.services.heading'),
            'intro' => $c->take('P01.services.intro'),
        ];
        $explore = null;
        foreach (self::SERVICES as $i => $code) {
            $c->expectNote('/^Card '.$code.' \|/');
            $services[$code]['title'] = $c->takeHeading(3, "services.{$code}.title");
            $services[$code]['home_summary'] = $c->take("services.{$code}.home_summary");
            $c->expectNote('/^H-L0'.(13 + $i).' \|/');
            $link = $c->takeLink("services.{$code}.home_link");
            $explore ??= $link['label'];
            if ($link['label'] !== $explore) {
                $this->report->add('note', "P01 card {$code}", 'CTA label differs from the first card: '.$link['label'], $locale);
            }
        }
        $ui['cta.explore_service'] = $explore;
        $page['content']['services']['closing'] = $c->take('P01.services.closing');
        $c->expectNote('/^H-L024 \|/');
        $ui['cta.view_all_practice_areas'] = $c->takeLink('ui.cta.view_all_practice_areas')['label'];

        // ---------------------------------------------------------------- 03 who we help
        $c->expectNote('/^SECTION 03 \|/');
        $who = [
            'heading' => $c->takeHeading(2, 'P01.who.heading'),
            'intro' => $c->take('P01.who.intro'),
            'items' => [],
        ];
        foreach (['individuals', 'businesses', 'investors', 'residents', 'entrepreneurs'] as $i => $key) {
            $who['items'][] = [
                'key' => $key,
                'title' => $c->takeHeading(3, "P01.who.items.{$i}.title"),
                'text' => $c->take("P01.who.items.{$i}.text"),
            ];
        }
        $page['content']['who_we_help'] = $who;

        // ---------------------------------------------------------------- 04 review + method
        $c->expectNote('/^SECTION 04 \|/');
        $review = [
            'heading' => $c->takeHeading(2, 'P01.review.heading'),
            'paragraphs' => $c->takeParagraphs('P01.review.paragraphs', expect: 2),
            'questions_heading' => $c->takeHeading(3, 'P01.review.questions_heading'),
            'questions' => $c->takeBullets('P01.review.questions', expect: 5),
            'method_heading' => $c->takeHeading(3, 'P01.review.method_heading'),
            'steps' => [],
        ];
        for ($i = 0; $i < 4; $i++) {
            $review['steps'][] = [
                'title' => $c->takeHeading(3, "P01.review.steps.{$i}.title"),
                'text' => $c->take("P01.review.steps.{$i}.text"),
            ];
        }
        $page['content']['review'] = $review;

        // ---------------------------------------------------------------- 05 about
        $c->expectNote('/^SECTION 05 \|/');
        $page['content']['about'] = [
            'heading' => $c->takeHeading(2, 'P01.about.heading'),
            'paragraphs' => $c->takeParagraphs('P01.about.paragraphs', expect: 3),
        ];
        $c->expectNote('/^H-L025 \|/');
        $ui['cta.meet_team'] = $c->takeLink('ui.cta.meet_team')['label'];

        // ---------------------------------------------------------------- 06 case studies
        $c->expectNote('/^SECTION 06 \|/');
        $page['content']['cases'] = [
            'heading' => $c->takeHeading(2, 'P01.cases.heading'),
            'intro' => $c->take('P01.cases.intro'),
        ];
        foreach (['case-01' => 'H-L026', 'case-04' => 'H-L027'] as $code => $marker) {
            $cases[$code]['home_title'] = $c->takeHeading(3, "case_studies.{$code}.home_title");
            $cases[$code]['home_summary'] = $c->take("case_studies.{$code}.home_summary");
            $c->expectNote('/^'.$marker.' \|/');
            $cases[$code]['home_link_label'] = $c->takeLink("case_studies.{$code}.home_link_label")['label'];
        }
        $c->expectNote('/^H-L028 \|/');
        $ui['cta.view_all_case_studies'] = $c->takeLink('ui.cta.view_all_case_studies')['label'];

        // ---------------------------------------------------------------- 07 guides
        $c->expectNote('/^SECTION 07 \|/');
        $page['content']['guides'] = [
            'heading' => $c->takeHeading(2, 'P01.guides.heading'),
            'intro' => $c->take('P01.guides.intro'),
        ];
        $readGuide = null;
        foreach (self::GUIDES as $i => $code) {
            $c->expectNote('/^Guide card '.$code.' \|/');
            $guides[$code]['home_title'] = $c->takeHeading(3, "guides.{$code}.home_title");
            $guides[$code]['home_summary'] = $c->take("guides.{$code}.home_summary");
            $c->expectNote('/^H-L0'.(29 + $i).' \|/');
            $label = $c->takeLink("guides.{$code}.home_link")['label'];
            $readGuide ??= $label;
        }
        $ui['cta.read_guide'] = $readGuide;
        $c->expectNote('/^H-L038 \|/');
        $ui['cta.visit_resources'] = $c->takeLink('ui.cta.visit_resources')['label'];

        // ---------------------------------------------------------------- 08 FAQ
        $c->expectNote('/^SECTION 08 \|/');
        $faq = ['heading' => $c->takeHeading(2, 'P01.faq.heading'), 'items' => []];
        for ($i = 0; $i < 8; $i++) {
            $faq['items'][] = [
                'question' => $c->takeHeading(3, "P01.faq.items.{$i}.question"),
                'answer' => $c->take("P01.faq.items.{$i}.answer"),
            ];
        }
        $page['content']['faq'] = $faq;
        $c->expectNote('/^H-L039 \|/');
        $ui['cta.view_all_faqs'] = $c->takeLink('ui.cta.view_all_faqs')['label'];

        // ---------------------------------------------------------------- 09 contact + home form copy
        $c->expectNote('/^SECTION 09 \|/');
        $contact = [
            'heading' => $c->takeHeading(2, 'P01.contact.heading'),
            'intro' => $c->take('P01.contact.intro'),
        ];
        $c->expectNote('/^H-L040 \|/');
        $phone = $c->takeLink('settings.phone');
        $settings['phone_display'] = $phone['label'];
        $settings['phone_href'] = $phone['href'];
        $c->expectNote('/^H-L041 \|/');
        $wa2 = $c->takeLink('ui.cta.whatsapp#contact');
        if ($wa2['href'] !== $settings['whatsapp_url'] || $wa2['label'] !== $ui['cta.whatsapp']) {
            $this->report->add('source-conflict', 'WA01', 'Section 09 WhatsApp link differs from the hero link', $locale);
        }
        $c->expectNote('/^H-L042 \|/');
        $email = $c->takeLink('settings.email');
        $settings['email'] = $email['label'];
        $settings['email_href'] = $email['href'];
        $contact['languages'] = $c->take('P01.contact.languages');
        $contact['hours'] = $c->take('P01.contact.hours');
        $page['content']['contact'] = $contact;

        // The homepage file carries its own (older) form labels. The Contact file is the master
        // for the shared form component; these are kept only for the content-diff report.
        $variant = [];
        $c->expectNote('/^Form fields in order\./');
        foreach (['name', 'phone', 'email', 'preferred_language', 'matter_type', 'summary', 'deadline_date', 'attachment'] as $field) {
            $variant['labels'][$field] = $c->take("P01.form_home_variant.labels.{$field}");
        }
        $c->expectNote('/^Preferred language options/');
        $variant['language_placeholder'] = $c->take('P01.form_home_variant.language_placeholder');
        $variant['language_options'] = [
            'ar' => $c->take('P01.form_home_variant.language_options.ar'),
            'en' => $c->take('P01.form_home_variant.language_options.en'),
            'ru' => $c->take('P01.form_home_variant.language_options.ru'),
        ];
        $c->expectNote('/^Matter type options\./');
        $variant['matter_placeholder'] = $c->take('P01.form_home_variant.matter_placeholder');
        $variant['matter_other'] = $c->take('P01.form_home_variant.matter_other');
        $c->expectNote('/^Deadline hint and attachment hint\./');
        $variant['deadline_hint'] = $c->take('P01.form_home_variant.deadline_hint');
        $variant['attachment_hint'] = $c->take('P01.form_home_variant.attachment_hint');
        $variant['sensitive_notice'] = $c->take('P01.form_home_variant.sensitive_notice');
        $c->expectNote('/^Required unchecked consent\./');
        $variant['consent'] = $c->take('P01.form_home_variant.consent');
        $variant['submit'] = $c->take('P01.form_home_variant.submit');
        $variant['engagement_notice'] = $c->take('P01.form_home_variant.engagement_notice');
        $c->expectNote('/^Form states in this exact order/');
        foreach (['submitting', 'success', 'missing_name', 'invalid_contact', 'missing_summary', 'missing_consent', 'invalid_attachment', 'failure'] as $state) {
            $variant['states'][$state] = $c->take("P01.form_home_variant.states.{$state}");
        }
        $page['content']['form_home_variant'] = $variant;

        // ---------------------------------------------------------------- footer
        $c->expectNote('/^FOOTER \|/');
        $ui['footer.brand'] = $c->takeHeading(2, 'ui.footer.brand');
        $ui['footer.description'] = $c->take('ui.footer.description');
        $ui['footer.practice_areas'] = $c->takeHeading(3, 'ui.footer.practice_areas');
        $ui['footer.resources'] = $c->takeHeading(3, 'ui.footer.resources');
        foreach (['footer.resources_centre' => 'H-L043', 'footer.faq' => 'H-L044', 'footer.case_studies' => 'H-L045'] as $key => $marker) {
            $c->expectNote('/^'.$marker.' \|/');
            $ui[$key] = $c->takeLink('ui.'.$key)['label'];
        }
        $ui['footer.useful_links'] = $c->takeHeading(3, 'ui.footer.useful_links');
        foreach (['footer.about' => 'H-L046', 'footer.contact' => 'H-L047', 'footer.legal_notice' => 'H-L048', 'footer.privacy' => 'H-L049', 'footer.terms' => 'H-L050'] as $key => $marker) {
            $c->expectNote('/^'.$marker.' \|/');
            $ui[$key] = $c->takeLink('ui.'.$key)['label'];
        }
        $ui['footer.disclaimer'] = $c->take('ui.footer.disclaimer');
        $ui['footer.copyright'] = $c->take('ui.footer.copyright');
        $ui['footer.whatsapp'] = $c->take('ui.footer.whatsapp');
        $ui['footer.specialist_heading'] = $c->take('ui.footer.specialist_heading');
        foreach (['X01', 'X02', 'X03', 'X04', 'X05', 'X06'] as $i => $code) {
            $c->expectNote('/^H-L05'.($i + 1).' \|/');
            $link = $c->takeLink("specialist_sites.{$code}");
            $split = $this->splitDomainLabel($link['label']);
            $sites[$code] = [
                'label' => $split['label'],
                'domain' => $split['domain'],
                'url' => $link['href'],
                'position' => $i + 1,
            ];
        }

        // ---------------------------------------------------------------- WhatsApp draft + metadata
        $c->expectNote('/^WhatsApp draft\./');
        $settings['whatsapp_draft'] = $c->take('settings.whatsapp_draft');
        $decoded = $this->whatsappDraft($settings['whatsapp_url']);
        if ($decoded !== $settings['whatsapp_draft']) {
            $this->report->add('source-conflict', 'WA01', 'The encoded hero WhatsApp link does not decode to the black draft text', $locale);
        }
        $c->expectNote('/^SEO title value/');
        $page['seo_title'] = $c->take('P01.seo_title');
        $c->expectNote('/^Meta description value/');
        $page['meta_description'] = $c->take('P01.meta_description');

        if (count($ui) < 40) {
            throw new ImportException($c->where().' homepage produced too few interface strings');
        }

        return [
            'ui_strings' => $ui,
            'settings' => $settings,
            'pages' => ['P01' => $page],
            'services' => $services,
            'guides' => $guides,
            'case_studies' => $cases,
            'specialist_sites' => $sites,
        ];
    }
}
