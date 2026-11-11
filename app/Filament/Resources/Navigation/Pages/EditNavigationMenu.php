<?php

namespace App\Filament\Resources\Navigation\Pages;

use App\Filament\Cms\Actions\CmsActions;
use App\Filament\Cms\Concerns\EditsCmsRecord;
use App\Filament\Resources\Navigation\NavigationResource;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * A menu's items (NavigationResource). Like every CMS edit page, except that a menu is never taken off the site or
 * moved to the Trash: the shell always renders it (an item is hidden with its own switch instead).
 */
class EditNavigationMenu extends EditRecord
{
    use EditsCmsRecord;

    protected static string $resource = NavigationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CmsActions::viewOnSite(),
            CmsActions::approval(),
            ActionGroup::make([
                CmsActions::schedule(),
                CmsActions::unschedule(),
                CmsActions::discard(),
            ])->icon(Heroicon::OutlinedEllipsisVertical)->color('gray')->button()->label((string) __('admin.action.menu')),
        ];
    }
}
