<?php

namespace App\Filament\Resources\MediaLibrary\Pages;

use App\Content\Cms\CopySource;
use App\Content\Cms\Tree;
use App\Filament\Cms\Concerns\HasEditingLocale;
use App\Filament\Resources\MediaLibrary\MediaLibraryResource;
use App\Models\MediaAsset;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Upload a file to the Media Library: the asset is saved at once (no draft). Its copy is pending client approval (the
 * alt text is written by the team, brief §8.7). The alt text typed here is stored on the file; the editor then lands on
 * the edit page to set the focal point.
 */
class CreateMediaAsset extends CreateRecord
{
    use HasEditingLocale;

    protected static string $resource = MediaLibraryResource::class;

    /** @var array<string, string> */
    private array $pendingAlt = [];

    protected function afterFill(): void
    {
        $this->data['cms_editing_locale'] = $this->resolveEditingLocale();
    }

    protected function handleRecordCreation(array $data): Model
    {
        $this->pendingAlt = [];
        foreach (Tree::LOCALES as $locale) {
            $this->pendingAlt[$locale] = trim((string) ($data['alt'][$locale] ?? ''));
        }

        return MediaAsset::query()->create([
            'name' => trim((string) ($data['name'] ?? '')),
            'collection' => in_array($data['collection'] ?? null, MediaAsset::COLLECTIONS, true) ? $data['collection'] : 'library',
            'copy_source' => CopySource::PENDING,
        ]);
    }

    protected function afterCreate(): void
    {
        /** @var MediaAsset $asset */
        $asset = $this->getRecord()->refresh();
        $media = $asset->file();
        if ($media === null) {
            return;
        }
        $media->setCustomProperty('alt', $this->pendingAlt);
        $media->save();
        if ($asset->name === '') {
            $asset->forceFill(['name' => (string) $media->name])->save();
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return (string) __('cms_media.library.created');
    }
}
