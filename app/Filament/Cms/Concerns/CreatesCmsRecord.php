<?php

namespace App\Filament\Cms\Concerns;

use App\Content\Cms\CopySource;
use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Status;
use App\Filament\Cms\Forms\BlockBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * A CreateRecord page of a CMS record: the new record starts as a draft (not on the site) whose copy is pending client
 * approval (written by the team, brief §8.7). The resource's static newRecordAttributes(array $data) gives the
 * non-editable columns (the key …); then the editor lands on the edit page to preview and publish.
 */
trait CreatesCmsRecord
{
    use HasEditingLocale;

    protected function afterFill(): void
    {
        $this->data['cms_editing_locale'] = $this->resolveEditingLocale();
    }

    protected function handleRecordCreation(array $data): Model
    {
        $resource = static::getResource();
        $class = static::getModel();
        /** @var Model $record */
        $record = new $class;
        $attributes = method_exists($resource, 'newRecordAttributes') ? $resource::newRecordAttributes($data) : [];
        $record->setRawAttributes($attributes + ['status' => Status::DRAFT, 'copy_source' => CopySource::PENDING]);
        if (method_exists($resource, 'fromFormState')) {
            $data = $resource::fromFormState($record, $data);
        }
        if (isset($data['blocks']) && is_array($data['blocks'])) {
            $data['blocks'] = BlockBuilder::fromState($record, $data['blocks'], method_exists($resource, 'blockTemplates') ? $resource::blockTemplates() : []);
        }
        Publisher::saveDraft($record, $data);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return (string) __('cms.action.saved');
    }
}
