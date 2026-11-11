<?php

namespace App\Content\Cms\Editing;

use App\Content\Cms\Status;
use App\Content\Cms\Tree;
use App\Design\D2\Calculators;
use App\Models\Content\CalculatorRuleSet;
use App\Models\Content\CaseStudy;
use App\Models\Content\DocumentSample;
use App\Models\Content\Faq;
use App\Models\Content\FaqCategory;
use App\Models\Content\Guide;
use App\Models\Content\GuideCategory;
use App\Models\Content\NavigationMenu;
use App\Models\Content\Page;
use App\Models\Content\Service;
use App\Models\Content\Situation;
use App\Models\Content\SpecialistSite;
use App\Models\Content\TeamMember;
use App\Support\SiteUrl;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Signed preview links (docs/briefs/design-2.1-and-admin-cms.md §8.5, §12.2): the record's public address in Design 2
 * and one language, with ?cms_preview=type:id&cms_expires=…&cms_signature=…, valid 60 minutes. The signature is an
 * HMAC of the path, the record and the expiry with the application key; App\Http\Middleware\CmsPreview checks it and
 * renders the page with the record's working copy (its draft) in place of the published row. An expired or altered
 * link is refused (403), never silently shown live.
 *
 * Where a record is shown (target()): its own page, or the page and anchor that show it (a guide: Legal Resources #its
 * anchor; a situation: the home's "Start your matter").
 */
final class Preview
{
    public const MINUTES = 60;

    public const PARAM = 'cms_preview';

    public const EXPIRES = 'cms_expires';

    public const SIGNATURE = 'cms_signature';

    /** The signed preview URL of a record in a language. */
    public static function url(Model $record, string $locale, ?CarbonInterface $expires = null): string
    {
        $expires ??= now()->addMinutes(self::MINUTES);
        [$path, $fragment] = self::target($record, $locale);
        $id = Registry::type($record).':'.$record->getKey();
        $ts = $expires->getTimestamp();
        $query = http_build_query([
            self::PARAM => $id,
            self::EXPIRES => $ts,
            self::SIGNATURE => self::signature($path, $id, $ts),
        ], '', '&', PHP_QUERY_RFC3986);

        return SiteUrl::abs($path).'?'.$query.($fragment !== null ? '#'.$fragment : '');
    }

    /** The public address of the record (no preview). */
    public static function publicUrl(Model $record, string $locale): string
    {
        [$path, $fragment] = self::target($record, $locale);

        return SiteUrl::abs($path).($fragment !== null ? '#'.$fragment : '');
    }

    /**
     * The page path and anchor that show a record.
     *
     * @return array{0: string, 1: string|null}
     */
    public static function target(Model $record, string $locale): array
    {
        $locale = in_array($locale, Tree::LOCALES, true) ? $locale : 'en';
        $page = fn (string $code, ?string $fragment = null) => [SiteUrl::pagePath($code, $locale), $fragment];

        return match (true) {
            $record instanceof Page => $record->getKey() === 'U01'
                ? ['/'.$locale.'/cms-preview-not-found/', null]
                : $page((string) $record->getKey()),
            $record instanceof Service => [SiteUrl::servicePath((string) $record->getKey(), $locale), null],
            $record instanceof Guide => $page('P03', $record->anchor ?: (string) $record->getKey()),
            $record instanceof GuideCategory => $page('P03'),
            $record instanceof CaseStudy => $page('P04', $record->anchor ?: (string) $record->getKey()),
            $record instanceof FaqCategory => $page('P05', $record->anchor ?: (string) $record->getKey()),
            $record instanceof Faq => $page('P05', $record->anchor ?: (string) $record->getKey()),
            $record instanceof TeamMember => $page('P06', $record->anchor ?: (string) $record->getKey()),
            $record instanceof SpecialistSite => is_string($record->practice_area) && config("summit.services.{$record->practice_area}") !== null
                ? [SiteUrl::servicePath($record->practice_area, $locale), null]
                : $page('P02'),
            $record instanceof Situation => $page('P01', 'start'),
            $record instanceof DocumentSample => $page('P01', 'anatomy'),
            $record instanceof CalculatorRuleSet => ['/'.$locale.'/'.trim(Calculators::PATH, '/').'/', null],
            $record instanceof NavigationMenu => $page('P01'),
            default => $page('P01'),
        };
    }

    public static function signature(string $path, string $id, int $expires): string
    {
        return hash_hmac('sha256', $path.'|'.$id.'|'.$expires, (string) config('app.key'));
    }

    /**
     * The record a preview request asks for, or null when the request is not a preview.
     *
     * @return array{valid: bool, expired: bool, record: Model|null}|null
     */
    public static function fromRequest(Request $request): ?array
    {
        $id = $request->query(self::PARAM);
        if (! is_string($id) || $id === '') {
            return null;
        }
        $expires = $request->query(self::EXPIRES);
        $signature = $request->query(self::SIGNATURE);
        $ts = is_string($expires) && ctype_digit($expires) ? (int) $expires : 0;
        $valid = is_string($signature) && hash_equals(self::signature($request->getPathInfo(), $id, $ts), $signature);
        $expired = $ts < now()->getTimestamp();
        $record = null;
        if ($valid && ! $expired) {
            [$type, $key] = array_pad(explode(':', $id, 2), 2, '');
            $class = Registry::models()[$type] ?? null;
            $record = $class !== null ? $class::withTrashed()->find($key) : null;
        }

        return ['valid' => $valid && $record !== null, 'expired' => $expired, 'record' => $record];
    }

    /**
     * The overlay rows the repository reads instead of the published ones: table => [key column, rows]. A menu's
     * preview replaces its items.
     *
     * @return array<string, array{key: string, rows: list<array<string, mixed>>, scope?: array<string, mixed>}>
     */
    public static function overlay(Model $record): array
    {
        $data = Publisher::workingCopy($record);
        $row = $record->getAttributes();
        foreach (Registry::fields($record) as $column => $kind) {
            if (! array_key_exists($column, $data) || $kind === Registry::ITEMS) {
                continue;
            }
            $value = $data[$column];
            $row[$column] = $kind === Registry::PLAIN ? (is_bool($value) ? (int) $value : $value) : ($value === null ? null : json_encode($value, Publisher::JSON));
        }
        $row['status'] = Status::PUBLISHED;
        $row['deleted_at'] = null;
        $row['draft'] = null;
        $overlay = [$record->getTable() => ['key' => $record->getKeyName(), 'rows' => [$row]]];

        if ($record instanceof NavigationMenu) {
            $items = [];
            foreach (array_values((array) ($data['items'] ?? [])) as $i => $item) {
                $items[] = [
                    'id' => $item['id'] ?? ('preview-'.$i),
                    'menu_id' => $record->getKey(),
                    'parent_id' => null,
                    'link_key' => $item['link_key'] ?? null,
                    'label' => isset($item['label']) ? json_encode($item['label'], Publisher::JSON) : null,
                    'target_type' => $item['target_type'] ?? 'url',
                    'target' => $item['target'] ?? null,
                    'new_tab' => (int) ($item['new_tab'] ?? false),
                    'enabled' => (int) ($item['enabled'] ?? true),
                    'position' => $i + 1,
                    'options' => isset($item['options']) ? json_encode($item['options'], Publisher::JSON) : null,
                    'status' => Status::PUBLISHED,
                    'deleted_at' => null,
                ];
            }
            $overlay['navigation_items'] = ['key' => 'id', 'rows' => $items, 'scope' => ['menu_id' => $record->getKey()]];
        }

        return $overlay;
    }
}
