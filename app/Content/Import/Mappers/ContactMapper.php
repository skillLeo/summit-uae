<?php

namespace App\Content\Import\Mappers;

use App\Content\Docx\Block;
use App\Content\Import\Cursor;
use App\Content\Import\ImportException;
use App\Content\Import\Mapper;

/**
 * 07_Contact_Us*.docx → P07 page copy and every string of the shared consultation form.
 *
 * The Contact file is the master for the one form component used on Contact and Home. Form
 * strings are keyed strictly by the red markers that exist in all three files: the submitted
 * field name in "FIELD … | name="phone" |", the option value in "Option value="S01" |" and the
 * English description of each feedback and delivery state. Black text is never used as a key.
 */
final class ContactMapper extends Mapper
{
    /** Every ui_strings key the shared form component reads; a missing one fails the import. */
    private const FORM_KEYS = [
        'form.heading', 'form.intro', 'form.required_note',
        'form.name.label', 'form.name.help', 'form.name.error',
        'form.phone.label', 'form.phone.help', 'form.phone.error',
        'form.email.label', 'form.email.help', 'form.email.error',
        'form.preferred_language.label', 'form.preferred_language.placeholder',
        'form.preferred_language.option.ar', 'form.preferred_language.option.en', 'form.preferred_language.option.ru',
        'form.matter_type.label', 'form.matter_type.placeholder',
        'form.matter_type.option.S01', 'form.matter_type.option.S02', 'form.matter_type.option.S03',
        'form.matter_type.option.S04', 'form.matter_type.option.S05', 'form.matter_type.option.S06',
        'form.matter_type.option.S07', 'form.matter_type.option.S08', 'form.matter_type.option.S09',
        'form.matter_type.option.S10', 'form.matter_type.option.S11',
        'form.matter_type.option.other', 'form.matter_type.option.unsure',
        'form.summary.label', 'form.summary.help', 'form.summary.error',
        'form.deadline_date.label', 'form.deadline_date.help', 'form.deadline_date.error',
        'form.deadline_details.label', 'form.deadline_details.help',
        'form.attachment.label', 'form.attachment.help', 'form.attachment.remove',
        'form.contact_group.help', 'form.notice.sensitive', 'form.notice.engagement',
        'form.consent.label', 'form.consent.link', 'form.submit',
        'form.contact_group.error', 'form.consent.error',
        'form.attachment.error_type', 'form.attachment.error_size',
        'form.attachment.error_count', 'form.attachment.error_rejected',
        'form.state.summary', 'form.state.busy', 'form.state.success', 'form.state.failure',
        'form.state.uncertain', 'form.state.rate_limited', 'form.state.new_enquiry',
    ];

    /** Red sub-markers inside a FIELD block: help text, validation message, selector option. */
    private const FIELD_PART = '/^(Field help text|Field validation message|Option value="[^"]*")/';

    /** "Form Feedback and Delivery States": red state description => ui key. */
    private const MESSAGES = [
        'Required contact group error' => 'form.contact_group.error',
        'Required consent error' => 'form.consent.error',
        'Invalid file type error' => 'form.attachment.error_type',
        'File size error' => 'form.attachment.error_size',
        'File count error' => 'form.attachment.error_count',
        'Rejected or unreadable upload error' => 'form.attachment.error_rejected',
        'Validation summary' => 'form.state.summary',
        'Submit button busy label' => 'form.state.busy',
        'Confirmed server acceptance success message' => 'form.state.success',
        'Confirmed submission failure' => 'form.state.failure',
        'Uncertain delivery after timeout' => 'form.state.uncertain',
        'Rate limit message' => 'form.state.rate_limited',
        'Return to form button after confirmed success' => 'form.state.new_enquiry',
    ];

    public function sources(): array
    {
        return ['contact' => '07_Contact_Us'];
    }

    public function map(array $docs, string $locale): array
    {
        $c = $docs['contact'];
        $ui = [];
        $content = [];
        $whatsapp = []; // marker => href of every WA01 control, checked against the black draft

        // ---------------------------------------------------------------- breadcrumb
        $home = $this->link($c, 'P07-L001', 'P07.breadcrumb.0', $locale, 'P01');
        $breadcrumb = [
            ['label' => $home['label'], 'target' => $home['target']],
            ['label' => $c->take('P07.breadcrumb.1'), 'target' => null],
        ];

        // ---------------------------------------------------------------- hero
        $c->expectNote('/^Contact hero \|/');
        $h1 = $c->takeHeading(1, 'P07.h1');
        $content['lead'] = $c->take('P07.lead');
        $content['paragraphs'] = $c->takeParagraphs('P07.paragraphs', '/^P07-L002 \|/');
        $cta = $this->link($c, 'P07-L002', 'P07.hero_cta_label', $locale, 'consultation-form');
        $content['hero_cta_label'] = $cta['label'];
        $content['hero_cta_target'] = $cta['target'];
        $wa = $this->link($c, 'P07-L003', 'P07.hero_whatsapp_label', $locale, 'WA01');
        $content['hero_whatsapp_label'] = $wa['label'];
        $whatsapp['P07-L003'] = $wa['href'];

        // ---------------------------------------------------------------- direct contact details
        $c->expectNote('/^Direct contact details \|/');
        $details = ['heading' => $c->takeHeading(2, 'P07.details.heading')];
        $c->expectNote('/^Telephone card:/');
        $details['telephone_label'] = $this->takeScoped($c, 'P07.details.telephone_label');
        $tel = $this->link($c, 'P07-L004', 'P07.details.phone_display', $locale, 'TEL');
        $details['phone_display'] = $tel['label'];
        $c->expectNote('/^WhatsApp card:/');
        $details['whatsapp_label'] = $this->takeScoped($c, 'P07.details.whatsapp_label');
        $wa = $this->link($c, 'P07-L005', 'P07.details.whatsapp_cta', $locale, 'WA01');
        $details['whatsapp_cta'] = $wa['label'];
        $whatsapp['P07-L005'] = $wa['href'];
        $c->expectNote('/^Email card:/');
        $details['email_label'] = $this->takeScoped($c, 'P07.details.email_label');
        $details['email'] = $this->link($c, 'P07-L006', 'P07.details.email', $locale, 'EMAIL')['label'];
        $details['languages'] = $c->take('P07.details.languages');
        $c->expectNote('/^Office hours\b/');
        $details['hours'] = $this->takeScoped($c, 'P07.details.hours');
        $details['after_hours'] = $c->take('P07.details.after_hours');
        $content['details'] = $details;

        // ---------------------------------------------------------------- what happens after contact
        $c->expectNote('/^What follows an enquiry \|/');
        $after = ['heading' => $c->takeHeading(2, 'P07.after.heading'), 'steps' => []];
        $end = $this->boundary($c, '/^FORM section \|/');
        while ($c->findNote('/\| H3\./', $end) !== null) {
            $i = count($after['steps']);
            $after['steps'][] = [
                'title' => $c->takeHeading(3, "P07.after.steps.{$i}.title"),
                'text' => $c->take("P07.after.steps.{$i}.text"),
            ];
        }
        $content['after'] = $after;

        // ---------------------------------------------------------------- consultation form (shared component)
        $c->expectNote('/^FORM section \|/');
        $c->expectNote('/^Consultation form \|/');
        $ui['form.heading'] = $c->takeHeading(2, 'ui.form.heading');
        $content['form_section'] = ['heading' => $ui['form.heading']];
        $ui['form.intro'] = $c->take('ui.form.intro');
        $ui['form.required_note'] = $c->take('ui.form.required_note');

        // Each FIELD marker names the submitted field; its help, validation and option notes follow.
        $fieldsEnd = $this->boundary($c, '/^Visible contact-group guidance\b/');
        while (($field = $c->findNote('/^FIELD \S+ \|/', $fieldsEnd)) !== null) {
            if (preg_match('/\bname="([a-z0-9_]+)"/', $field->note, $m) !== 1) {
                throw new ImportException($c->where($field).' FIELD marker without a name="…" attribute');
            }
            $prefix = 'form.'.$m[1];
            $this->put($ui, $prefix.'.label', $this->takeScoped($c, 'ui.'.$prefix.'.label'), $c);
            $next = $c->indexOfNote('/^FIELD /');
            $end = $next === null ? $fieldsEnd : min($next, $fieldsEnd);
            while (($part = $c->findNote(self::FIELD_PART, $end)) !== null) {
                $key = $prefix.'.'.$this->fieldPart($part);
                $this->put($ui, $key, $this->takeScoped($c, 'ui.'.$key), $c);
            }
        }
        $c->expectNote('/^Attachment controls\b/');
        $this->put($ui, 'form.attachment.remove', $this->takeScoped($c, 'ui.form.attachment.remove'), $c);

        $c->expectNote('/^Visible contact-group guidance\b/');
        $ui['form.contact_group.help'] = $this->takeScoped($c, 'ui.form.contact_group.help');
        $c->expectNote('/^Visible form notices\b/');
        $ui['form.notice.sensitive'] = $this->takeScoped($c, 'ui.form.notice.sensitive');
        $ui['form.notice.engagement'] = $this->takeScoped($c, 'ui.form.notice.engagement');

        $consentNote = $c->expectNote('/name="enquiry_consent"/');
        $ui['form.consent.label'] = $this->takeScoped($c, 'ui.form.consent.label');
        $linked = $this->link($c, 'P07-L007', 'ui.form.consent.link', $locale, 'L01')['label'];
        $ui['form.consent.link'] = $this->consentLinkPhrase($ui['form.consent.label'], $linked, $consentNote, $locale);
        $c->expectNote('/^Submit button, type="submit"/');
        $ui['form.submit'] = $this->takeScoped($c, 'ui.form.submit');

        // Conditional messages, keyed by their red state description wherever it sits in the section.
        $c->expectNote('/^Form Feedback and Delivery States\b/');
        $start = $c->position();
        $end = $this->boundary($c, '/^Prepare a useful initial enquiry \|/');
        foreach (self::MESSAGES as $marker => $key) {
            $c->seek($start);
            if ($c->findNote('/^'.preg_quote($marker, '/').'\b/', $end) === null) {
                throw new ImportException($c->where()." expected the \"{$marker}\" designer note");
            }
            $this->put($ui, $key, $this->takeScoped($c, 'ui.'.$key), $c);
        }
        $c->seek($end);

        // ---------------------------------------------------------------- what helps us understand
        $c->expectNote('/^Prepare a useful initial enquiry \|/');
        $content['helps'] = [
            'heading' => $c->takeHeading(2, 'P07.helps.heading'),
            'items' => $c->takeBullets('P07.helps.items'),
            'note' => $c->take('P07.helps.note'),
        ];

        // ---------------------------------------------------------------- contact FAQ
        $c->expectNote('/^Contact-specific FAQ \|/');
        $faq = ['heading' => $c->takeHeading(2, 'P07.faq.heading'), 'items' => []];
        $end = $this->boundary($c, '/^P07-L008 \|/');
        while ($c->findNote('/^Contact FAQ \d+ \|/', $end) !== null) {
            $i = count($faq['items']);
            $faq['items'][] = [
                'question' => $c->takeHeading(3, "P07.faq.items.{$i}.question"),
                'answer' => $c->take("P07.faq.items.{$i}.answer"),
            ];
        }
        $content['faq'] = $faq;

        // ---------------------------------------------------------------- onward links
        $content['links'] = [];
        foreach (['P07-L008', 'P07-L009', 'P07-L010'] as $i => $marker) {
            $link = $this->link($c, $marker, "P07.links.{$i}", $locale);
            $content['links'][] = ['label' => $link['label'], 'target' => $link['target']];
        }

        // ---------------------------------------------------------------- final contact controls
        $c->expectNote('/^Final direct contact controls \|/');
        $final = [
            'heading' => $c->takeHeading(2, 'P07.final.heading'),
            'text' => $c->take('P07.final.text'),
        ];
        $wa = $this->link($c, 'P07-L011', 'P07.final.whatsapp_label', $locale, 'WA01');
        $final['whatsapp_label'] = $wa['label'];
        $whatsapp['P07-L011'] = $wa['href'];
        $final['call_label'] = $this->link($c, 'P07-L012', 'P07.final.call_label', $locale, 'TEL')['label'];
        $final['email_label'] = $this->link($c, 'P07-L013', 'P07.final.email_label', $locale, 'EMAIL')['label'];
        $final['scope'] = $c->take('P07.final.scope');
        $legal = $this->link($c, 'P07-L014', 'P07.final.legal_link_label', $locale);
        $final['legal_link_label'] = $legal['label'];
        $final['legal_link_target'] = $legal['target'];
        $content['final'] = $final;

        // ---------------------------------------------------------------- WhatsApp draft + metadata
        $c->expectNote('/^WhatsApp draft\./');
        $draft = $this->takeScoped($c, 'P07.whatsapp_draft');
        $recipient = preg_replace('/\D/', '', $tel['href']);
        foreach ($whatsapp as $marker => $href) {
            if ($this->whatsappDraft($href) !== $draft) {
                $this->report->add('source-conflict', "WA01 {$marker}", 'The encoded WhatsApp link does not decode to the black draft text', $locale);
            }
            if (! str_starts_with($href, 'https://wa.me/'.$recipient.'?')) {
                $this->report->add('source-conflict', "WA01 {$marker}", "The WhatsApp recipient differs from the telephone number {$tel['href']}", $locale);
            }
        }
        $c->expectNote('/^SEO title\b/');
        $seoTitle = $this->takeScoped($c, 'P07.seo_title');
        $c->expectNote('/^Meta description\b/');
        $metaDescription = $this->takeScoped($c, 'P07.meta_description');

        $missing = array_diff(self::FORM_KEYS, array_keys($ui));
        if ($missing !== []) {
            throw new ImportException($c->where().' the consultation form lacks '.implode(', ', $missing));
        }
        foreach (array_diff(array_keys($ui), self::FORM_KEYS) as $key) {
            $this->report->add('note', "ui_strings.{$key}", 'Form string in the Contact file that the shared form component does not read yet', $locale);
        }

        return [
            'ui_strings' => $ui,
            'pages' => ['P07' => [
                'h1' => $h1,
                'seo_title' => $seoTitle,
                'meta_description' => $metaDescription,
                'breadcrumb' => $breadcrumb,
                'whatsapp_draft' => $draft,
                'content' => $content,
            ]],
        ];
    }

    /**
     * Take the black hyperlink announced by a red "P07-Lnnn |" marker. Its target is the ID the
     * note names ("-> P05:", "-> WA01:", "-> #consultation-form"), never the fallback URL.
     *
     * @return array{label: string, href: string, target: string}
     */
    private function link(Cursor $c, string $marker, string $field, string $locale, ?string $expect = null): array
    {
        $note = $c->expectNote('/^'.preg_quote($marker, '/').' \|/');
        $link = $c->takeLink($field);
        if (preg_match('/->\s*([A-Z]+\d*):\s*(\S*)/', $note->note, $m) === 1) {
            $target = $m[1];
            // "-> P05: /ar/faq/", "-> TEL: tel:+971…" and "-> EMAIL: mailto:…" also name the address.
            $address = rtrim($m[2], '.');
            if (preg_match('#^(/|tel:|mailto:)#', $address) === 1 && ! str_ends_with($link['href'], $address)) {
                $this->report->add('source-conflict', "P07 {$marker}", "The black link points to {$link['href']} but the red note names {$address}", $locale);
            }
        } elseif (preg_match('/->\s*\S*#([a-z0-9-]+)/', $note->note, $m) === 1) {
            $target = $m[1];
            if (! str_ends_with($link['href'], '#'.$target)) {
                $this->report->add('source-conflict', "P07 {$marker}", "The black link points to {$link['href']} but the red note names #{$target}", $locale);
            }
        } else {
            throw new ImportException($c->where($note)." {$marker} names no link destination");
        }
        if ($expect !== null && $target !== $expect) {
            $this->report->add('source-conflict', "P07 {$marker}", "Expected a link to {$expect}; the red note names {$target}", $locale);
        }

        return $link + ['target' => $target];
    }

    /**
     * Take the next black block for the designer note just passed. A block that sits after another
     * red note belongs to that note, so a missing string fails loudly instead of shifting every
     * following string by one.
     */
    private function takeScoped(Cursor $c, string $field): string
    {
        $next = $c->peekPublic();
        $note = $c->indexOfNote('/^/');
        if ($next === null || ($note !== null && $note < $next->index)) {
            throw new ImportException($c->where()." no black line follows the designer note for {$field}");
        }

        return $c->take($field);
    }

    /**
     * The words of the consent label that carry the L01 link; the form component links the first
     * occurrence of form.consent.link inside form.consent.label. Normally that is the black
     * P07-L007 label itself. The Russian consent note instead directs linking "the grammatically
     * inflected privacy-policy phrase already present in that complete Russian sentence" and forbids
     * substituting the nominative P07-L007 label (the footer wording) into it, so that phrase is
     * located word by word and returned verbatim from the approved label.
     */
    private function consentLinkPhrase(string $label, string $linked, Block $note, string $locale): string
    {
        if (str_contains($label, $linked)) {
            return $linked;
        }
        if (preg_match('/\binflected\b/', $note->note) === 1) {
            // Each linked word, less a case ending of up to two letters, must begin one of
            // consecutive label words: "Политика конфиденциальности" finds "Политикой конфиденциальности".
            $words = preg_split('/\s+/u', $linked, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $stems = array_map(fn (string $w) => preg_quote(mb_substr($w, 0, max(3, mb_strlen($w) - 2)), '/').'\p{L}*', $words);
            if ($stems !== [] && preg_match_all('/(?<!\p{L})'.implode('\s+', $stems).'/iu', $label, $m) === 1) {
                $this->report->add('note', 'form.consent.link', sprintf(
                    'The consent label links its inflected phrase "%s" to L01, as the red consent note directs; "%s" (P07-L007) is the standalone footer wording.',
                    $m[0][0],
                    $linked,
                ), $locale);

                return $m[0][0];
            }
        }
        $this->report->add('source-conflict', 'form.consent.link', sprintf(
            'The linked phrase "%s" does not occur in the consent label "%s"; decide which words of the label link to L01.',
            $linked,
            $label,
        ), $locale);

        return $linked;
    }

    /** Index of the next note matching $pattern: the end of the section being read. */
    private function boundary(Cursor $c, string $pattern): int
    {
        return $c->indexOfNote($pattern)
            ?? throw new ImportException($c->where()." expected a designer note matching {$pattern}");
    }

    /** "Field help text…" → help, "Field validation message…" → error, Option value="x" → option.x. */
    private function fieldPart(Block $note): string
    {
        if (preg_match('/^Option value="([^"]*)"/', $note->note, $m) === 1) {
            return $m[1] === '' ? 'placeholder' : 'option.'.$m[1];
        }

        return str_starts_with($note->note, 'Field help text') ? 'help' : 'error';
    }

    /** @param array<string, string> $ui */
    private function put(array &$ui, string $key, string $value, Cursor $c): void
    {
        if (array_key_exists($key, $ui)) {
            throw new ImportException($c->where()." {$key} is announced twice");
        }
        $ui[$key] = $value;
    }
}
