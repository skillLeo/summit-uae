<?php

namespace App\Filament\Widgets;

use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Support\RequestFormat;
use App\Models\Enquiry;
use App\Requests\ReviewTarget;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Dashboard row 2 (docs/briefs/design-2.1-and-admin-cms.md §11.1): "Needs attention": open requests over the internal
 * target, open requests without an assignee, and open requests with a deadline within 7 days. A case handler sees
 * their own requests first. Up to five per group, with the rest counted.
 */
class NeedsAttention extends Widget
{
    protected string $view = 'filament.widgets.needs-attention';

    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    public const LIMIT = 5;

    public static function canView(): bool
    {
        return CmsAccess::can('requests', 'view');
    }

    /** @return array<string, array{items: Collection<int, Enquiry>, more: int}> */
    public function groups(): array
    {
        $open = CmsAccess::scopeRequests(Enquiry::query()->counted())->whereIn('status', [Enquiry::STATUS_NEW, Enquiry::STATUS_IN_REVIEW])
            ->with('assignee')->orderBy('received_at')->get();
        $mine = fn (Collection $c) => $c->sortBy(fn (Enquiry $e) => $e->assignee_id === auth()->id() ? 0 : 1)->values();

        $over = $open->filter(fn (Enquiry $e) => ReviewTarget::isOverdue($e, RequestFormat::hours()));
        $unassigned = $open->filter(fn (Enquiry $e) => $e->assignee_id === null);
        $deadline = $open->filter(fn (Enquiry $e) => RequestFormat::deadlineSoon($e))->sortBy(fn (Enquiry $e) => RequestFormat::deadlineDays($e));

        $out = [];
        foreach (['over_target' => $mine($over), 'unassigned' => $unassigned->values(), 'deadline' => $mine($deadline)] as $key => $items) {
            if ($items->isNotEmpty()) {
                $out[$key] = ['items' => $items->take(self::LIMIT), 'more' => max(0, $items->count() - self::LIMIT)];
            }
        }

        return $out;
    }

    public function url(Enquiry $e): ?string
    {
        return CmsAccess::readOnly() ? null : EnquiryResource::getUrl('view', ['record' => $e]);
    }
}
