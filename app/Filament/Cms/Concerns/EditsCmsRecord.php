<?php

namespace App\Filament\Cms\Concerns;

use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Editing\Registry;
use App\Filament\Cms\Actions\CmsActions;
use App\Filament\Cms\Forms\BlockBuilder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

/**
 * An EditRecord page of a CMS record (docs/briefs/design-2.1-and-admin-cms.md §8.5, §9.3):
 *   - the form shows the working copy (the draft, else the published values);
 *   - Save (Cmd/Ctrl+S) stores the draft: the site keeps the published version (App\Content\Cms\Editing\Publisher);
 *   - Publish (Cmd/Ctrl+Enter, after a confirmation) and Preview live in the Publishing panel (CmsForm::panel());
 *     both save the form first, so they act on what the editor sees;
 *   - the header has View on site, the approval status and a menu: schedule, discard draft, take off the site, Trash;
 *   - the locale switcher (HasEditingLocale).
 * A resource may convert the working copy for its form with static toFormState() / fromFormState(); page blocks are
 * converted for the block builder here.
 */
trait EditsCmsRecord
{
    use HasEditingLocale;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $working = Publisher::workingCopy($record);
        if (array_key_exists('blocks', $working) && (Registry::fields($record)['blocks'] ?? null) === Registry::TREE) {
            $working['blocks'] = BlockBuilder::toState($working['blocks']);
        }
        $resource = static::getResource();
        if (method_exists($resource, 'toFormState')) {
            $working = $resource::toFormState($record, $working);
        }

        return ['code' => (string) $record->getKey(), 'cms_editing_locale' => $this->resolveEditingLocale()] + $working;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $resource = static::getResource();
        if (method_exists($resource, 'fromFormState')) {
            $data = $resource::fromFormState($record, $data);
        }
        if (isset($data['blocks']) && is_array($data['blocks']) && $this->isBuilderState($data['blocks'])) {
            $data['blocks'] = BlockBuilder::fromState($record, $data['blocks'], method_exists($resource, 'blockTemplates') ? $resource::blockTemplates() : []);
        }
        Publisher::saveDraft($record, $data);

        return $record;
    }

    /** @param array<mixed> $items */
    private function isBuilderState(array $items): bool
    {
        foreach ($items as $item) {
            if (! is_array($item) || ! array_key_exists('type', $item) || ! array_key_exists('data', $item) || array_key_exists('id', $item)) {
                return false;
            }
        }

        return true;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return (string) __('cms.action.saved');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()->success()->title((string) __('cms.action.saved'))->body((string) __('cms.action.saved_body'));
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label((string) __('cms.action.save_draft'))->keyBindings(['mod+s']);
    }

    /** Stay on the record after saving. */
    protected function getRedirectUrl(): ?string
    {
        return null;
    }

    /** Publish and Preview call this first (CmsActions::saveFirst()). */
    public function saveDraftSilently(): void
    {
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
    }

    #[On('cms-revision-restored')]
    public function revisionRestored(): void
    {
        $this->refreshCmsRecord(refill: true);
    }

    public function refreshCmsRecord(bool $refill = false): void
    {
        $this->getRecord()->refresh();
        if ($refill) {
            $this->fillForm();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            CmsActions::viewOnSite(),
            CmsActions::approval(),
            ActionGroup::make([
                CmsActions::schedule(),
                CmsActions::unschedule(),
                CmsActions::discard(),
                CmsActions::unpublish(),
                CmsActions::delete(),
            ])->icon(Heroicon::OutlinedEllipsisVertical)->color('gray')->button()->label((string) __('admin.action.menu')),
        ];
    }
}
