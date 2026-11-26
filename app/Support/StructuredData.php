<?php

namespace App\Support;

use App\Content\Cms\HomeBlocks;
use App\Design\D2\Pending;

/**
 * schema.org structured data (JSON-LD) of a page (client PDF item 26), printed by the Design 2 head
 * (d2::partials.seo) on every indexable page. Everything in it is on the page: the approved texts of the page
 * language, read from the same content records the templates show, so a removed or unpublished record (Legal
 * Translation, owner decision 6) drops out by itself.
 *
 *   #organization  LegalService: the brand, the home page, the logo, the telephone (E.164, from the tel: link), the
 *                  e-mail, the country served (AE), the site languages and the published practice areas (on the home
 *                  page and the Practice Areas page, where they are listed). No address, opening hours, ratings, reviews,
 *                  prices or founding year: the content rules forbid them. LegalService is a schema.org subtype of
 *                  LocalBusiness; Google's local-business rich result needs a street address, so this node is valid
 *                  schema.org but not eligible for that result until the client approves publishing an address.
 *   #website       WebSite: the brand, the languages, the publisher.
 *   #webpage       the page (WebPage, or AboutPage, ContactPage, CollectionPage, FAQPage …), its language, its
 *                  breadcrumb, its main entity.
 *   #breadcrumb    BreadcrumbList: exactly the visible breadcrumb trail ($crumbs) on the production host.
 *   #service       Service on a practice-area page: its approved title, introduction, provider and country.
 *   FAQPage        only where the page shows those questions and answers in full (the FAQ page, a practice area's own
 *                  questions, the home page's questions while that block is shown, the Contact page's questions). A
 *                  question is marked up on one page only (the FAQ centre's questions that a practice area repeats
 *                  under "related questions" are left to the FAQ page).
 *   Article        the nine complete guides on the Resources page (headline, section, language, author and
 *                  publisher = the organisation; no dates or images: none are shown).
 */
final class StructuredData
{
    /**
     * @param  array<string, mixed>  $seo  App\Support\Seo::make()
     * @param  list<array{label?: string, href?: ?string}>  $crumbs  the visible breadcrumb trail
     * @return array<string, mixed>|null null for a page that is not indexed
     */
    public static function graph(array $seo, array $crumbs = []): ?array
    {
        $canonical = $seo['canonical'] ?? null;
        if (! is_string($canonical) || $canonical === '') {
            return null;
        }
        $entry = SitePages::current();
        $locale = app()->getLocale();
        $origin = SiteUrl::canonicalOrigin();
        $org = $origin.'/#organization';

        $page = [
            '@type' => self::pageType($entry),
            '@id' => $canonical.'#webpage',
            'url' => $canonical,
            'name' => (string) $seo['title'],
            'inLanguage' => $locale,
            'isPartOf' => ['@id' => $origin.'/#website'],
        ];
        if (filled($seo['description'] ?? null)) {
            $page['description'] = (string) $seo['description'];
        }
        if (in_array($entry['code'] ?? null, ['P01', 'P06', 'P07'], true)) {
            $page['about'] = ['@id' => $org];
        }

        $graph = [self::organization($entry), self::website()];
        $breadcrumb = self::breadcrumb($crumbs, $canonical);
        if ($breadcrumb !== null) {
            $page['breadcrumb'] = ['@id' => $breadcrumb['@id']];
        }

        $faqs = self::faqs($entry);
        if ($faqs !== []) {
            $types = array_values(array_unique([...(array) $page['@type'], 'FAQPage']));
            $page['@type'] = count($types) === 1 ? $types[0] : $types;
            $page['mainEntity'] = $faqs;
        }

        $extra = [];
        if (($entry['type'] ?? null) === 'service') {
            $service = self::service($entry['code'], $canonical);
            if ($service !== null) {
                $extra[] = $service;
                $page['about'] = ['@id' => $service['@id']];
                $page['mainEntity'] ??= ['@id' => $service['@id']];
            }
        }
        if (($entry['code'] ?? null) === 'P03') {
            $articles = self::articles($canonical);
            if ($articles !== []) {
                $page['hasPart'] = array_map(fn (array $a) => ['@id' => $a['@id']], $articles);
                array_push($extra, ...$articles);
            }
        }

        $graph[] = $page;
        if ($breadcrumb !== null) {
            $graph[] = $breadcrumb;
        }

        return ['@context' => 'https://schema.org', '@graph' => [...$graph, ...$extra]];
    }

    /** The JSON for a <script type="application/ld+json">: "<", ">" and "&" escaped, so no text can close the script. */
    public static function json(array $graph): string
    {
        return (string) json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** @return string|list<string> */
    private static function pageType(?array $entry): string|array
    {
        return match ($entry['code'] ?? null) {
            'P02', 'P03', 'P04' => 'CollectionPage',
            'P05' => 'FAQPage',
            'P06' => 'AboutPage',
            'P07' => 'ContactPage',
            default => 'WebPage',
        };
    }

    /** @return array<string, mixed> */
    private static function organization(?array $entry): array
    {
        $origin = SiteUrl::canonicalOrigin();
        $name = ui('brand.name', 'en');
        $alternate = array_values(array_unique(array_filter(
            array_map(fn (string $l) => ui('brand.name', $l), array_keys((array) config('summit.locales'))),
            fn (string $n) => $n !== $name && ! str_starts_with($n, '⟦'),
        )));
        $languages = array_values(array_keys((array) config('summit.locales')));
        $telephone = preg_replace('/^tel:/i', '', (string) setting('phone_href'));
        $email = (string) setting('email');

        $node = [
            '@type' => 'LegalService',
            '@id' => $origin.'/#organization',
            'name' => $name,
        ];
        if ($alternate !== []) {
            $node['alternateName'] = count($alternate) === 1 ? $alternate[0] : $alternate;
        }
        $node += [
            'url' => SiteUrl::canonical(SiteUrl::pagePath('P01')),
            'logo' => [
                '@type' => 'ImageObject',
                '@id' => $origin.'/#logo',
                'url' => $origin.'/icons/icon-512.png',
                'contentUrl' => $origin.'/icons/icon-512.png',
                'width' => 512,
                'height' => 512,
                'caption' => ui('brand.logo_alt'),
            ],
            'image' => ['@id' => $origin.'/#logo'],
        ];
        if (preg_match('/^\+\d{6,15}$/', (string) $telephone) === 1) {
            $node['telephone'] = $telephone;
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $node['email'] = $email;
        }
        $node['areaServed'] = self::country();
        $node['knowsLanguage'] = $languages;
        $contact = ['@type' => 'ContactPoint', 'contactType' => 'customer service', 'areaServed' => 'AE', 'availableLanguage' => $languages];
        if (isset($node['telephone'])) {
            $contact['telephone'] = $node['telephone'];
        }
        if (isset($node['email'])) {
            $contact['email'] = $node['email'];
        }
        $node['contactPoint'] = [$contact];

        // The practice areas, where the page lists them (home: section 02; the Practice Areas page).
        if (in_array($entry['code'] ?? null, ['P01', 'P02'], true)) {
            $offers = [];
            foreach (SitePages::services() as $service) {
                $record = content()->service($service['code']);
                $title = trim((string) ($record['title'] ?? ''));
                if ($title !== '' && in_array(app()->getLocale(), $service['locales'], true)) {
                    $offers[] = ['@type' => 'Offer', 'itemOffered' => [
                        '@type' => 'Service',
                        'name' => $title,
                        'url' => SiteUrl::canonical('/'.app()->getLocale().'/'.$service['path']),
                    ]];
                }
            }
            if ($offers !== []) {
                $node['hasOfferCatalog'] = [
                    '@type' => 'OfferCatalog',
                    'name' => ui('nav.practice_areas'),
                    'url' => SiteUrl::canonical(SiteUrl::pagePath('P02')),
                    'itemListElement' => $offers,
                ];
            }
        }

        return $node;
    }

    /** @return array<string, mixed> */
    private static function website(): array
    {
        $origin = SiteUrl::canonicalOrigin();

        return [
            '@type' => 'WebSite',
            '@id' => $origin.'/#website',
            // The default language's home page ("/" itself is a 301 to it: PDF item 29), never a redirecting address.
            'url' => SiteUrl::canonical(SiteUrl::pagePath('P01', (string) config('summit.default_locale'))),
            'name' => ui('brand.name', 'en'),
            'inLanguage' => array_values(array_keys((array) config('summit.locales'))),
            'publisher' => ['@id' => $origin.'/#organization'],
        ];
    }

    /** @return array<string, string> */
    private static function country(): array
    {
        return ['@type' => 'Country', 'name' => 'United Arab Emirates', 'identifier' => 'AE'];
    }

    /**
     * The visible breadcrumb trail on the production host: each link's path, the last item the page itself.
     *
     * @param  list<array{label?: string, href?: ?string}>  $crumbs
     * @return array<string, mixed>|null
     */
    private static function breadcrumb(array $crumbs, string $canonical): ?array
    {
        $crumbs = array_values(array_filter($crumbs, fn ($c) => is_array($c) && trim((string) ($c['label'] ?? '')) !== ''));
        if (count($crumbs) < 2) {
            return null;
        }
        $items = [];
        foreach ($crumbs as $i => $crumb) {
            $last = $i === count($crumbs) - 1;
            $href = $crumb['href'] ?? null;
            $item = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => trim((string) $crumb['label'])];
            if ($last) {
                $item['item'] = $canonical;
            } elseif (is_string($href) && $href !== '') {
                $path = parse_url($href, PHP_URL_PATH);
                if (! is_string($path) || $path === '') {
                    return null;
                }
                $item['item'] = SiteUrl::canonical($path);
            } else {
                return null; // a trail with an unlinked middle step is not a valid BreadcrumbList
            }
            $items[] = $item;
        }

        return ['@type' => 'BreadcrumbList', '@id' => $canonical.'#breadcrumb', 'itemListElement' => $items];
    }

    /** @return array<string, mixed>|null */
    private static function service(string $code, string $canonical): ?array
    {
        $record = content()->service($code);
        $name = trim((string) ($record['title'] ?? ''));
        if ($record === null || $name === '') {
            return null;
        }
        $intro = array_values(array_filter((array) ($record['intro'] ?? []), fn ($p) => is_string($p) && trim($p) !== ''));
        $node = [
            '@type' => 'Service',
            '@id' => $canonical.'#service',
            'name' => $name,
            'serviceType' => $name,
            'url' => $canonical,
            'provider' => ['@id' => SiteUrl::canonicalOrigin().'/#organization'],
            'areaServed' => self::country(),
        ];
        if (is_string($record['intro'] ?? null) && trim($record['intro']) !== '') {
            $node['description'] = trim($record['intro']);
        } elseif ($intro !== []) {
            $node['description'] = trim($intro[0]);
        }

        return $node;
    }

    /**
     * The questions and answers the page shows in full, each marked up once on the site.
     *
     * @return list<array<string, mixed>>
     */
    private static function faqs(?array $entry): array
    {
        $items = match (true) {
            ($entry['code'] ?? null) === 'P05' => array_map(fn ($f) => [$f['question'] ?? '', $f['answer'] ?? ''], content()->faqs()),
            ($entry['type'] ?? null) === 'service' => array_map(
                fn ($f) => [$f['question'] ?? '', $f['answer'] ?? ''],
                array_values(array_filter((array) (content()->service($entry['code'])['sections']['faq']['items'] ?? []), 'is_array')),
            ),
            ($entry['code'] ?? null) === 'P07' => array_map(
                fn ($f) => [$f['question'] ?? '', $f['answer'] ?? ''],
                array_values(array_filter((array) (content()->page('P07')['content']['faq']['items'] ?? []), 'is_array')),
            ),
            ($entry['code'] ?? null) === 'P01' && self::homeShows('faq') => array_map(
                fn ($f) => [$f['question'] ?? '', $f['answer'] ?? ''],
                array_values(array_filter((array) (content()->page('P01')['content']['faq']['items'] ?? []), 'is_array')),
            ),
            default => [],
        };

        $out = [];
        foreach ($items as [$question, $answer]) {
            $question = trim((string) $question);
            $answer = self::plain($answer);
            if ($question !== '' && $answer !== '') {
                $out[] = ['@type' => 'Question', 'name' => $question, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer]];
            }
        }

        return $out;
    }

    /** Whether the home page shows a block of this type (on, and its copy shown: App\Design\D2\Pending). */
    private static function homeShows(string $type): bool
    {
        foreach (content()->blocks(HomeBlocks::PAGE) as $block) {
            if ($block['type'] === $type && ($block['enabled'] ?? false) && Pending::shows($block)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array<string, mixed>> */
    private static function articles(string $canonical): array
    {
        $categories = [];
        foreach (content()->guideCategories() as $category) {
            $categories[(string) ($category['code'] ?? '')] = (string) ($category['name'] ?? '');
        }
        $out = [];
        foreach (content()->guides() as $guide) {
            $anchor = (string) (($guide['anchor'] ?? null) ?: config('summit.guides.'.($guide['code'] ?? '')));
            // The guide's visible H2 (components/d2/resources/article.blade.php): the body title, else the record's.
            $headline = trim((string) (($guide['body']['title'] ?? null) ?: ($guide['title'] ?? '')));
            if ($anchor === '' || $headline === '') {
                continue;
            }
            $article = [
                '@type' => 'Article',
                '@id' => $canonical.'#'.$anchor,
                'url' => $canonical.'#'.$anchor,
                'headline' => $headline,
                'inLanguage' => app()->getLocale(),
                'isPartOf' => ['@id' => $canonical.'#webpage'],
                'mainEntityOfPage' => ['@id' => $canonical.'#webpage'],
                'author' => ['@id' => SiteUrl::canonicalOrigin().'/#organization'],
                'publisher' => ['@id' => SiteUrl::canonicalOrigin().'/#organization'],
            ];
            $section = $categories[(string) ($guide['category'] ?? '')] ?? '';
            if (trim($section) !== '') {
                $article['articleSection'] = trim($section);
            }
            $summary = trim((string) ($guide['card_summary'] ?? ''));
            if ($summary !== '') {
                $article['description'] = $summary;
            }
            $out[] = $article;
        }

        return $out;
    }

    /** An answer (a string, or a list of paragraphs / {type: p, text} blocks) as plain text, paragraphs on their own lines. */
    private static function plain(mixed $answer): string
    {
        $parts = [];
        foreach (is_array($answer) ? $answer : [$answer] as $block) {
            $text = is_string($block) ? $block : (is_array($block) ? (string) ($block['text'] ?? '') : '');
            if (is_array($block) && isset($block['items']) && is_array($block['items'])) {
                $text = implode("\n", array_filter(array_map(fn ($i) => is_string($i) ? $i : (string) ($i['text'] ?? ''), $block['items'])));
            }
            $text = trim(strip_tags($text));
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode("\n\n", $parts);
    }
}
