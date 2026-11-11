<?php

namespace App\Filament\Resources\InterfaceText\Pages;

use App\Filament\Cms\Actions\CmsActions;
use App\Filament\Cms\Concerns\EditsCmsRecord;
use App\Filament\Resources\InterfaceText\InterfaceTextResource;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * One interface string: its text per language and the note. The views define the key, so the string is never taken off
 * the site or moved to the Trash here (that would bring back the lang file's text without saying so).
 */
class EditInterfaceText extends EditRecord
{
    use EditsCmsRecord;

    protected static string $resource = InterfaceTextResource::class;

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
