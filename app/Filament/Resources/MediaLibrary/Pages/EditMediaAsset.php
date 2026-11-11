<?php

namespace App\Filament\Resources\MediaLibrary\Pages;

use App\Content\Cms\CopySource;
use App\Content\Cms\MediaFiles;
use App\Content\Cms\Tree;
use App\Filament\Cms\Concerns\HasEditingLocale;
use App\Filament\Resources\MediaLibrary\MediaLibraryResource;
use App\Models\MediaAsset;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Edit a Media Library asset: its name, collection and approval (media_assets), and the file's alt text per language and
 * focal point (custom properties of its media row). Saving is immediate: media are not drafted.
 */
class EditMediaAsset extends EditRecord
{
    use HasEditingLocale;

    protected static string $resource = MediaLibraryResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var MediaAsset $asset */
        $asset = $this->getRecord();
        $media = $asset->file();
        $alt = (array) ($media?->getCustomProperty('alt') ?? []);
        $data['alt'] = [];
        foreach (Tree::LOCALES as $locale) {
            $data['alt'][$locale] = is_string($alt[$locale] ?? null) ? $alt[$locale] : '';
        }
        $data['focal_point'] = MediaFiles::point($media?->getCustomProperty('focal_point'));
        $data['cms_editing_locale'] = $this->resolveEditingLocale();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var MediaAsset $record */
        $record->fill([
            'name' => trim((string) ($data['name'] ?? $record->name)),
            'collection' => in_array($data['collection'] ?? null, MediaAsset::COLLECTIONS, true) ? $data['collection'] : $record->collection,
            'copy_source' => in_array($data['copy_source'] ?? null, CopySource::ALL, true) ? $data['copy_source'] : $record->copy_source,
        ]);
        $media = $record->file();
        if ($media !== null) {
            $media->name = (string) $record->name;
            $media->setCustomProperty('copy_source', (string) $record->copy_source);
            if (MediaFiles::isImage($media)) {
                $alt = [];
                foreach (Tree::LOCALES as $locale) {
                    $alt[$locale] = trim((string) ($data['alt'][$locale] ?? ''));
                }
                $media->setCustomProperty('alt', $alt);
                $point = MediaFiles::point($data['focal_point'] ?? null);
                $point === null ? $media->forgetCustomProperty('focal_point') : $media->setCustomProperty('focal_point', $point);
            }
            $media->save();
        }
        $record->touch();
        $record->save();

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return MediaLibraryResource::editHeaderActions();
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return (string) __('cms_media.library.saved');
    }
}
