<?php

namespace App\Design\D2;

use Illuminate\Support\HtmlString;

/**
 * Design 2 sample documents (<x-d2.doc.*>, upgrade spec §4.2, §5.4 and §12 B2 / C3).
 *
 * Every document is a generic, fictional ILLUSTRATION: "Sample Bank", "A. Example", "B. Sample LLC",
 * zeroed numbers, dates 00/00/2026 and the watermark «ILLUSTRATIVE SAMPLE» in all three languages.
 * Copy: lang/{en,ar,ru}/proposed-d2/docs.php (proposed, needs client approval).
 *
 * Root contract (shared with the hero, method and anatomy sections):
 *   <div class="doc doc--{type} doc--{variant}" data-doc="{type}" data-variant="{variant}"
 *        data-callouts='[{"id":"figures","x":83.7,"y":62,"label":"…","side":"end"}]'>
 *     .doc__shadow--rest / .doc__shadow--air   two shadow layers (animate their opacity, never box-shadow)
 *     .doc__body (aria-hidden)                  the paper, drawn at the natural size, scaled by --doc-scale
 *   x: percent of the document box from its inline-start edge (left in LTR, right in RTL documents);
 *   y: percent from its top edge; side: the label sits on the start or the end side of the document.
 *   Key fields carry data-field="{id}" with the same ids (callouts and anatomy hotspots).
 */
final class Docs
{
    /** The six hero documents, bottom of the pile first (spec §12 B3 deal order); the memo rides on the cheque. */
    public const TYPES = ['notice', 'messages', 'transfer', 'tenancy', 'contract', 'cheque'];

    /** The documents of the Anatomy of a Document section, in chip order (spec §5.4). */
    public const ANATOMY = ['cheque', 'contract', 'tenancy', 'transfer', 'notice'];

    public const VARIANTS = ['stage', 'mobile', 'still', 'anatomy'];

    /**
     * Natural size of the document box at variant "stage" (CSS px; the same numbers live in
     * resources/css/d2/components/doc.css as --doc-w / --doc-h). The cheque box holds the 520×230
     * cheque and the 300×150 return memo stapled behind its top edge (84 px of the memo show above it).
     *
     * @var array<string, array{0: int, 1: int}>
     */
    public const SIZE = [
        'cheque' => [520, 314],
        'memo' => [300, 150],
        'contract' => [360, 470],
        'tenancy' => [380, 480],
        'transfer' => [320, 420],
        'messages' => [260, 460],
        'notice' => [360, 470],
    ];

    /**
     * Scale presets (doc.css): stage 1; mobile fits the cheque, contract and tenancy into the 342×300
     * mobile hero stage; still is the method section's still-life; anatomy follows the anatomy column
     * (a width ladder in doc.css, at most 1.2).
     *
     * @var array<string, float|null>
     */
    public const SCALE = ['stage' => 1.0, 'mobile' => 0.56, 'still' => 0.5, 'anatomy' => null];

    /**
     * Callout anchors (three per document): field id => [x %, y %, side]. The side is where the hero puts the
     * label (start or end of the document); the hero lays its callouts out from the measured fields
     * (App\Design\D2\HeroStage, D16), x / y are the rough anchors other specimens use (and the hero's fallback).
     * Two callouts on the same side stay well apart (the notice's hearing and the messages' promise are on
     * the end side, away from the case number and the contact).
     *
     * @var array<string, array<string, array{0: float, 1: float, 2: string}>>
     */
    public const CALLOUTS = [
        'cheque' => [
            'memo' => [28.0, 21.1, 'start'],
            'date' => [85.9, 44.1, 'end'],
            'figures' => [83.7, 62.0, 'end'],
        ],
        'memo' => [
            'stamp' => [86.1, 14.3, 'end'],
            'reason' => [37.4, 39.1, 'start'],
            'signatory' => [17.0, 70.4, 'start'],
        ],
        'contract' => [
            'parties' => [27.8, 23.6, 'start'],
            'wage' => [70.5, 39.0, 'end'],
            'notice' => [19.8, 61.7, 'start'],
        ],
        'tenancy' => [
            'period' => [20.4, 39.3, 'start'],
            'rent' => [83.9, 43.3, 'end'],
            'renewal' => [19.4, 69.5, 'start'],
        ],
        'transfer' => [
            'amount' => [22.6, 29.9, 'start'],
            'wallet' => [10.5, 57.6, 'start'],
            'txid' => [80.0, 66.4, 'end'],
        ],
        'messages' => [
            'contact' => [34.2, 10.6, 'start'],
            'date' => [50.0, 20.2, 'end'],
            'promise' => [19.7, 49.4, 'end'],
        ],
        'notice' => [
            'case' => [27.8, 20.6, 'start'],
            'hearing' => [28.0, 30.3, 'end'],
            'instructions' => [19.8, 59.6, 'start'],
        ],
    ];

    /**
     * Anatomy hotspots per document (field ids in reading order). Each has a name, "What it can show"
     * and "What we check" in lang/{locale}/proposed-d2/anatomy.php.
     *
     * @var array<string, list<string>>
     */
    public const HOTSPOTS = [
        'cheque' => ['date', 'payee', 'words', 'figures', 'signature', 'memo'],
        'contract' => ['parties', 'start', 'wage', 'allowances', 'notice', 'signatures'],
        'tenancy' => ['parties', 'period', 'rent', 'renewal', 'registration', 'signatures'],
        'transfer' => ['amount', 'date', 'wallet', 'txid', 'network', 'status'],
        'notice' => ['case', 'parties', 'hearing', 'department', 'instructions', 'issued'],
    ];

    /**
     * The service each anatomy document leads to (prefill for "Request a Legal Consultation"); the
     * hearing notice can belong to any matter, so it starts the wizard without a service.
     *
     * @var array<string, string|null>
     */
    public const SERVICE = [
        'cheque' => 'S01',
        'contract' => 'S06',
        'tenancy' => 'S05',
        'transfer' => 'S03',
        'notice' => null,
    ];

    public static function variant(?string $variant): string
    {
        return in_array($variant, self::VARIANTS, true) ? $variant : 'stage';
    }

    /** One proposed document string: docs.{type}.{key} in the page language (or $locale). */
    public static function t(string $key, ?string $locale = null): string
    {
        return d2p('docs.'.$key, [], $locale);
    }

    /**
     * Numbers, codes and Latin runs on a document: <bdi dir="ltr"> on Arabic pages (they keep their own
     * direction inside right-to-left text), plain escaped text elsewhere.
     */
    public static function ltr(?string $text, ?string $locale = null): HtmlString
    {
        $text ??= '';
        if ($text === '') {
            return new HtmlString('');
        }

        return ($locale ?? app()->getLocale()) === 'ar'
            ? new HtmlString('<bdi dir="ltr">'.e($text).'</bdi>')
            : new HtmlString(e($text));
    }

    /**
     * Attributes of a key field: data-field="{id}" (+ data-hot when it is the selected field, drawn with
     * the bronze outline and glow; resources/js/d2/components/anatomy.js toggles data-hot later).
     */
    public static function field(string $id, ?string $hot = null): HtmlString
    {
        return new HtmlString('data-field="'.e($id).'"'.($hot === $id ? ' data-hot' : ''));
    }

    /**
     * The callouts of one document with their translated labels (data-callouts on the root).
     *
     * @return list<array{id: string, x: float, y: float, label: string, side: string}>
     */
    public static function callouts(string $type, ?string $locale = null): array
    {
        $out = [];
        foreach (self::CALLOUTS[$type] ?? [] as $id => [$x, $y, $side]) {
            $out[] = ['id' => $id, 'x' => $x, 'y' => $y, 'label' => self::t('callouts.'.$type.'.'.$id, $locale), 'side' => $side];
        }

        return $out;
    }

    public static function calloutsJson(string $type, ?string $locale = null): string
    {
        return (string) json_encode(self::callouts($type, $locale), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    /**
     * The watermark in all three languages (spec §4.3: "only the watermark stays in all three
     * languages"), the page language first.
     *
     * @return list<array{lang: string, dir: string, text: string}>
     */
    public static function watermark(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $order = array_values(array_unique([$locale, 'en', 'ar', 'ru']));

        return array_map(fn (string $l) => [
            'lang' => $l,
            'dir' => $l === 'ar' ? 'rtl' : 'ltr',
            'text' => self::t('watermark', $l),
        ], array_values(array_intersect($order, ['en', 'ar', 'ru'])));
    }

    /** Accessible description of one document for the container's role="img" (proposed). */
    public static function alt(string $type, ?string $locale = null): string
    {
        return self::t('alt.'.$type, $locale);
    }

    /** Short document name (chips, pager): docs.name.{type}. */
    public static function name(string $type, ?string $locale = null): string
    {
        return self::t('name.'.$type, $locale);
    }
}
