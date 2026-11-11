<?php

namespace App\Content\Import;

/**
 * Cross-file checks that no single mapper can make. They run on the assembled datasets and add
 * findings to the import report (docs/content-diff.md):
 *  - label-mismatch: the homepage file's older form copy vs the Contact master; navigation labels
 *    vs page breadcrumbs; service and guide titles that differ between approved files;
 *  - source-conflict: a page's WhatsApp draft that differs from WA01;
 *  - reuse: approved copy deliberately placed on a page other than its source file.
 */
final class CrossChecks
{
    private const LOCALES = ['en', 'ar', 'ru'];

    /** homepage form field (form_home_variant) => Contact master ui key */
    private const FORM_PAIRS = [
        'labels.name' => 'form.name.label',
        'labels.phone' => 'form.phone.label',
        'labels.email' => 'form.email.label',
        'labels.preferred_language' => 'form.preferred_language.label',
        'labels.matter_type' => 'form.matter_type.label',
        'labels.summary' => 'form.summary.label',
        'labels.deadline_date' => 'form.deadline_date.label',
        'labels.attachment' => 'form.attachment.label',
        'language_placeholder' => 'form.preferred_language.placeholder',
        'language_options.ar' => 'form.preferred_language.option.ar',
        'language_options.en' => 'form.preferred_language.option.en',
        'language_options.ru' => 'form.preferred_language.option.ru',
        'matter_placeholder' => 'form.matter_type.placeholder',
        'matter_other' => 'form.matter_type.option.other',
        'deadline_hint' => 'form.deadline_date.help',
        'attachment_hint' => 'form.attachment.help',
        'sensitive_notice' => 'form.notice.sensitive',
        'consent' => 'form.consent.label',
        'submit' => 'form.submit',
        'engagement_notice' => 'form.notice.engagement',
        'states.submitting' => 'form.state.busy',
        'states.success' => 'form.state.success',
        'states.missing_name' => 'form.name.error',
        'states.invalid_contact' => 'form.contact_group.error',
        'states.missing_summary' => 'form.summary.error',
        'states.missing_consent' => 'form.consent.error',
        'states.invalid_attachment' => 'form.attachment.error_type',
        'states.failure' => 'form.state.failure',
    ];

    /** page => nav ui key, for the breadcrumb's current-page label */
    private const NAV_PAGES = [
        'P02' => 'nav.practice_areas', 'P03' => 'nav.resources', 'P04' => 'nav.case_studies',
        'P05' => 'nav.faq', 'P06' => 'nav.about', 'P07' => 'nav.contact',
    ];

    public static function run(array $d, Report $report): void
    {
        self::form($d, $report);
        self::navigation($d, $report);
        self::services($d, $report);
        self::guides($d, $report);
        self::whatsapp($d, $report);
        self::reuse($report);
    }

    private static function form(array $d, Report $report): void
    {
        $home = $d['pages']['P01']['content'] ?? [];
        $ui = $d['ui_strings'] ?? [];
        foreach (self::LOCALES as $l) {
            $variant = $home[$l]['form_home_variant'] ?? [];
            foreach (self::FORM_PAIRS as $path => $key) {
                $homeText = data_get($variant, $path);
                $master = $ui[$key][$l] ?? null;
                if ($homeText !== null && $master !== null && $homeText !== $master) {
                    $report->add('label-mismatch', "form: {$path} → {$key}",
                        'Homepage file: "'.$homeText.'" | Contact file (master, used): "'.$master.'"', $l);
                }
            }
            // The homepage note says the matter options reuse the eleven service titles verbatim;
            // the Contact master supplies short option labels instead.
            foreach (array_keys(config('summit.services')) as $code) {
                $title = $d['services'][$code]['title'][$l] ?? null;
                $option = $ui["form.matter_type.option.{$code}"][$l] ?? null;
                if ($title !== null && $option !== null && $title !== $option) {
                    $report->add('label-mismatch', "form: matter option {$code}",
                        'Homepage note (reuse the service title): "'.$title.'" | Contact file (master, used): "'.$option.'"', $l);
                }
            }
        }
    }

    private static function navigation(array $d, Report $report): void
    {
        foreach (self::NAV_PAGES as $page => $key) {
            foreach (self::LOCALES as $l) {
                $crumb = $d['pages'][$page]['breadcrumb'][$l] ?? null;
                $current = is_array($crumb) ? end($crumb) : null;
                $current = is_array($current) ? ($current['label'] ?? null) : $current;
                $nav = $d['ui_strings'][$key][$l] ?? null;
                if ($current !== null && $nav !== null && $current !== $nav) {
                    $report->add('label-mismatch', "{$page} breadcrumb vs {$key}",
                        'Navigation (homepage file): "'.$nav.'" | breadcrumb ('.$page.' file): "'.$current.'". Both are approved; confirm whether they should match.', $l);
                }
            }
        }
    }

    private static function services(array $d, Report $report): void
    {
        foreach ($d['services'] ?? [] as $code => $s) {
            foreach (self::LOCALES as $l) {
                $title = $s['title'][$l] ?? null;
                foreach (['hub_title' => 'P02 card title', 'breadcrumb' => 'service-page breadcrumb'] as $field => $what) {
                    $other = $s[$field][$l] ?? null;
                    $other = is_array($other) ? end($other) : $other;
                    $other = is_array($other) ? ($other['label'] ?? null) : $other;
                    if ($title !== null && $other !== null && $title !== $other) {
                        $report->add('label-mismatch', "{$code} title vs {$what}", 'Homepage: "'.$title.'" | '.$what.': "'.$other.'"', $l);
                    }
                }
            }
        }
    }

    private static function guides(array $d, Report $report): void
    {
        foreach ($d['guides'] ?? [] as $code => $g) {
            foreach (self::LOCALES as $l) {
                $home = $g['home_title'][$l] ?? null;
                $card = $g['card_title'][$l] ?? null;
                if ($home !== null && $card !== null && $home !== $card) {
                    $report->add('label-mismatch', "{$code} title",
                        'Homepage card (used on Home): "'.$home.'" | Knowledge Centre card: "'.$card.'". Both approved; each is used on its own page.', $l);
                }
            }
        }
    }

    private static function whatsapp(array $d, Report $report): void
    {
        $wa = $d['settings']['whatsapp_draft'] ?? [];
        foreach (['pages', 'services'] as $dataset) {
            foreach ($d[$dataset] ?? [] as $code => $record) {
                foreach (self::LOCALES as $l) {
                    $draft = $record['whatsapp_draft'][$l] ?? null;
                    if ($draft !== null && isset($wa[$l]) && $draft !== $wa[$l]) {
                        $report->add('source-conflict', "WA01 draft in {$code}", 'Differs from the homepage WA01 draft: "'.$draft.'"', $l);
                    }
                }
            }
        }
    }

    private static function reuse(Report $report): void
    {
        $rows = [
            'Consultation form (Home)' => 'All labels, help texts, options, notices and delivery states come from 07_Contact_Us (the form master), not from the older homepage form copy (see the label mismatches above).',
            'Hero quiet line (Home)' => 'Office hours "… UAE time" from 07_Contact_Us (the homepage line has no time zone).',
            'Contact channel labels (Home §10)' => 'Telephone / WhatsApp / Email labels from 07_Contact_Us.',
            'Mobile contact sheet' => '"Call Us" / "Email Us" and office hours from 07_Contact_Us.',
            'Case previews (Home §7)' => 'The "Matter: …" line of case-01 and case-04 from 04_Case_Studies.',
            'Guides (Home §8)' => 'Grouped under the three category names of 03_Knowledge_Centre.',
            'Matter Navigator (Home §5)' => 'Situations and "This service may be relevant if:" from 02_Services_Overview; documents, note, first steps, mistakes, section headings and related-guide labels from each 02_S01–S11 page.',
            'Search sheet' => 'Guide titles and summaries from 03_Knowledge_Centre; search label, placeholder and no-results text from the same file.',
            '404 (U01)' => 'Header and footer from 01_Homepage; page copy from L03_404_Page.',
        ];
        foreach ($rows as $subject => $detail) {
            $report->add('reuse', $subject, $detail);
        }
    }
}
