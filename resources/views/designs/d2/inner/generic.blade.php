{{--
    Design 2 generic inner template (upgrade spec §12 Phase D): any page whose own d2::inner.<template> does
    not exist yet still looks finished. PagePreviewController data: $h1, $intro, $crumbs; plus $pageCode or
    $serviceCode from d2::pages.preview.

    head (breadcrumbs, H1, approved intro) · the page's approved DOCX sections in the 720 px column
    (<x-d2.inner.prose>: headings, paragraphs, lists, steps, Q&A, link rows; scope notes as callouts) · the
    sticky side CTA card with the page's own approved CTA block when it has one.

    What is NOT repeated here: the intro paragraphs the head already shows, the CTA block (it feeds the card),
    and heading-only entries that belong to an interactive component (search, directory, form): their
    pages' own templates render them. Anchors stored with a section (P06 #team) are kept as section ids.
--}}
@php
    $record = $serviceCode ? (content()->service($serviceCode) ?? []) : ($pageCode ? content()->page($pageCode) : []);
    $content = $serviceCode ? ($record['sections'] ?? []) : ($record['content'] ?? []);
    $content = is_array($content) ? $content : [];
    $printed = array_map('trim', (array) $intro);
    $fresh = fn ($text) => is_string($text) && trim($text) !== '' && ! in_array(trim($text), $printed, true);

    $sections = [];
    $loose = [];
    $cta = null;
    foreach ($content as $key => $value) {
        // Top-level paragraphs (P02 intro, P03 lead + intro, P07 lead + paragraphs): only what the head did not show.
        if (is_string($value)) {
            if (in_array($key, ['lead', 'intro', 'text', 'body'], true) && $fresh($value)) {
                $loose[] = $value;
            }
            continue;
        }
        if (! is_array($value) || $value === []) {
            continue;
        }
        if (array_is_list($value)) {
            if (in_array($key, ['intro', 'paragraphs'], true)) {
                array_push($loose, ...array_values(array_filter($value, $fresh)));
            } elseif ($key === 'links') {
                $sections[] = ['blocks' => [['type' => 'links', 'items' => $value]]];
            }
            continue;
        }
        // The page's CTA block feeds the CTA card (P02/P04/P06 cta, P03/P05 consultation, P07 final, services cta).
        if (in_array($key, ['cta', 'consultation', 'final'], true) && $cta === null) {
            $cta = $value;
            continue;
        }
        // Contact details and the home form variant belong to the contact card and the wizard.
        if (in_array($key, ['details', 'form_home_variant'], true)) {
            continue;
        }
        $section = $value;
        // A service's specialist resource (intro + its domain), in its own place in the DOCX order.
        if ($key === 'specialist' && ! isset($section['heading'])) {
            if (! empty($section['domain'])) {
                $sections[] = ['blocks' => [
                    ['type' => 'p', 'text' => (string) ($section['intro'] ?? '')],
                    ['type' => 'links', 'items' => [['label' => (string) ($section['label'] ?? $section['domain']), 'url' => 'https://'.$section['domain'].'/']]],
                ]];
            }
            continue;
        }
        // Head-like entries without a heading (P05/P06 hero, P04 intro): the paragraphs the head did not show.
        if (! isset($section['heading'])) {
            foreach (['lead', 'intro', 'text', 'body'] as $k) {
                if ($fresh($section[$k] ?? null)) {
                    $loose[] = $section[$k];
                }
            }
            array_push($loose, ...array_values(array_filter((array) ($section['paragraphs'] ?? []), $fresh)));
            if (! empty($section['links']) && array_is_list($section['links'])) {
                $sections[] = ['blocks' => [['type' => 'links', 'items' => $section['links']]]];
            }
            continue;
        }
        // Service sections whose list lives on the record itself.
        if ($serviceCode && $key === 'documents') {
            $section['items'] = $record['documents'] ?? [];
            $section['note'] = $record['documents_note'] ?? null;
        }
        if ($serviceCode && $key === 'next_step') {
            $section['blocks'] = [
                ['type' => 'heading', 'text' => (string) ($section['first_steps_heading'] ?? '')],
                ['type' => 'ul', 'items' => $record['first_steps'] ?? []],
                ['type' => 'heading', 'text' => (string) ($section['mistakes_heading'] ?? '')],
                ['type' => 'ul', 'items' => $record['mistakes'] ?? []],
            ];
        }
        if (isset($section['menu']) && is_array($section['menu'])) {
            $section['links'] = $section['menu'];
        }
        if (isset($section['link_label']) && is_string($section['link_label'])) {
            $section['link'] = ['label' => $section['link_label'], 'target' => 'P08'];
        }
        if (isset($section['anchor']) && is_string($section['anchor'])) {
            $section['id'] = $section['anchor'];
        }
        if ($key === 'scope') {
            $section['variant'] = 'note';
        }
        unset($section['menu'], $section['link_label'], $section['anchor'], $section['first_steps_heading'], $section['mistakes_heading']);
        // Heading-only placeholders of interactive components (search, directory, form, category menu).
        $body = array_diff_key($section, array_flip(['heading', 'id', 'variant', 'tone']));
        if (array_filter($body, fn ($v) => $v !== null && $v !== '' && $v !== []) === []) {
            continue;
        }
        $sections[] = $section;
    }
    if ($loose !== []) {
        array_unshift($sections, ['blocks' => $loose]);
    }

    // The CTA block's own approved labels, whichever shape its mapper used.
    $pick = function (...$candidates): ?string {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return null;
    };
    $ctaText = $cta['text'] ?? $cta['body'] ?? $cta['paragraphs'] ?? null;
    $requestLabel = $pick($cta['request_label'] ?? null, $cta['consultation']['label'] ?? null, $cta['cta']['label'] ?? null);
    $whatsappLabel = $pick($cta['whatsapp_label'] ?? null, $cta['whatsapp']['label'] ?? null);
@endphp
<x-d2.inner.head :title="$h1" :crumbs="$crumbs" :intro="$intro" class="generic-head" />

<x-d2.inner.layout class="generic">
    @if ($sections !== [])
        <x-d2.inner.prose :sections="$sections" id-prefix="section" />
    @endif

    <x-slot:aside>
        <x-d2.inner.cta-card
            :heading="$cta['heading'] ?? null"
            :text="$ctaText"
            :note="$cta['note'] ?? $cta['notice'] ?? null"
            :service="$serviceCode"
            :request-label="$requestLabel"
            :whatsapp-label="$whatsappLabel"
            :email="! empty($cta['email'])"
            :whatsapp-draft="$serviceCode ? ($record['whatsapp_draft'] ?? null) : null" />
    </x-slot:aside>
</x-d2.inner.layout>
