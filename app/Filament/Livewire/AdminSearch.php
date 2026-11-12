<?php

namespace App\Filament\Livewire;

use App\Content\Cms\ContentCache;
use App\Content\Cms\Editing\Registry;
use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Models\Enquiry;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Throwable;

/**
 * Cmd/Ctrl+K search (docs/briefs/design-2.1-and-admin-cms.md §9.1, §13): requests by reference, name, phone or e-mail,
 * and every content record by its title in English, Arabic and Russian, with the recently opened items (kept in this
 * browser only). Each result honours the permission matrix: a user sees only what they may open.
 */
final class AdminSearch extends Component
{
    public string $q = '';

    public const LIMIT = 8;

    /** @return list<array{group: string, title: string, meta: string, url: string}> */
    public function results(): array
    {
        $q = trim($this->q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $out = [];

        if (CmsAccess::can('requests', 'view')) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q).'%';
            $digits = preg_replace('/\D+/', '', $q);
            $requests = CmsAccess::scopeRequests(Enquiry::query())
                ->where(function ($w) use ($like, $digits): void {
                    $w->where('reference', 'like', $like)->orWhere('name', 'like', $like)->orWhere('email', 'like', $like);
                    if (strlen((string) $digits) >= 4) {
                        $w->orWhere('phone', 'like', '%'.$digits.'%');
                    }
                })
                ->orderByDesc('received_at')->limit(self::LIMIT)->get(['id', 'reference', 'name', 'status']);
            foreach ($requests as $e) {
                $out[] = [
                    'group' => (string) __('requests.nav.inbox'),
                    'title' => $e->reference,
                    'meta' => $e->name.', '.\App\Filament\Support\EnquiryStatus::label($e->status),
                    'url' => EnquiryResource::getUrl('view', ['record' => $e]),
                    'ltr' => true,
                ];
            }
        }

        $needle = mb_strtolower($q);
        $count = 0;
        foreach (self::index() as $row) {
            if ($count >= self::LIMIT * 2) {
                break;
            }
            if (! str_contains($row['haystack'], $needle) || ! CmsAccess::can($row['screen'], 'view')) {
                continue;
            }
            $out[] = ['group' => $row['group'], 'title' => $row['title'], 'meta' => $row['key'], 'url' => $row['url'], 'ltr' => false];
            $count++;
        }

        return $out;
    }

    /**
     * Every content record with its titles in the three languages, cached per content version.
     *
     * @return list<array{screen: string, group: string, key: string, title: string, haystack: string, url: string}>
     */
    public static function index(): array
    {
        $version = (string) rescue(fn () => ContentCache::version(), 'v', report: false);
        $locale = app()->getLocale();

        return Cache::remember("admin.search.{$version}.{$locale}", 600, function (): array {
            $rows = [];
            foreach (Filament::getResources() as $resource) {
                if (! method_exists($resource, 'screen') || $resource === EnquiryResource::class) {
                    continue;
                }
                $model = $resource::getModel();
                if (! Registry::supports($model)) {
                    continue;
                }
                try {
                    $records = $model::query()->get();
                } catch (Throwable) {
                    continue;
                }
                foreach ($records as $record) {
                    /** @var Model $record */
                    $titles = [];
                    foreach (['en', 'ar', 'ru'] as $l) {
                        $titles[] = rescue(fn () => Registry::label($record, $l), '', report: false);
                    }
                    try {
                        $url = $resource::hasPage('edit') ? $resource::getUrl('edit', ['record' => $record]) : $resource::getUrl('index');
                    } catch (Throwable) {
                        continue;
                    }
                    $rows[] = [
                        'screen' => $resource::screen(),
                        'group' => $resource::getPluralModelLabel(),
                        'key' => (string) $record->getKey(),
                        'title' => $titles[array_search(app()->getLocale(), ['en', 'ar', 'ru'], true) ?: 0] ?: $titles[0],
                        'haystack' => mb_strtolower(implode(' ', [...$titles, (string) $record->getKey()])),
                        'url' => $url,
                    ];
                }
            }

            return $rows;
        });
    }

    public function render(): View
    {
        return view('filament.admin.search', ['results' => $this->results()]);
    }
}
