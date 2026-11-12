<?php

namespace App\Filament\Widgets;

use App\Content\Cms\ContentCache;
use App\Content\Cms\Editing\ApprovalReport;
use App\Content\Cms\Editing\Registry;
use App\Content\Cms\Editing\TranslationStatus;
use App\Filament\Cms\Support\CmsAccess;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Dashboard row 3 (docs/briefs/design-2.1-and-admin-cms.md §11.1): Content status: drafts waiting, scheduled items,
 * pending client approval and missing translations per locale, each linked to its screen. Counted once per content
 * version (App\Content\Cms\ContentCache) and at most every five minutes.
 */
class ContentStatus extends Widget
{
    protected string $view = 'filament.widgets.content-status';

    protected static ?int $sort = 5;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    public static function canView(): bool
    {
        return CmsAccess::can('pages', 'view');
    }

    /** @return array{drafts: int, scheduled: int, pending: int, missing: array<string, int>} */
    public function figures(): array
    {
        $version = (string) rescue(fn () => ContentCache::version(), 'v', report: false);

        return Cache::remember('dashboard.content-status.'.$version, 300, function (): array {
            $drafts = $scheduled = 0;
            $missing = ['en' => 0, 'ar' => 0, 'ru' => 0];
            foreach (Registry::models() as $class) {
                $model = new $class;
                $table = $model->getTable();
                $drafts += $class::query()->whereNotNull('draft')->count();
                $scheduled += $class::query()->whereNotNull('publish_at')->count();
                if ($table === 'ui_strings') {
                    continue;
                }
                foreach ($class::query()->get() as $record) {
                    try {
                        foreach (TranslationStatus::missingLocales($record) as $locale) {
                            if (isset($missing[$locale])) {
                                $missing[$locale]++;
                            }
                        }
                    } catch (Throwable) {
                        // a record the checker cannot read is left out
                    }
                }
            }

            return ['drafts' => $drafts, 'scheduled' => $scheduled, 'pending' => count(ApprovalReport::rows()), 'missing' => $missing];
        });
    }
}
