<?php

namespace App\Filament\Cms\Forms;

use App\Models\MediaAsset;
use Filament\Forms\Components\Select;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Pick an image of the Media Library (docs/briefs/design-2.1-and-admin-cms.md §8.9) by its UUID: the image-and-text
 * block stores the UUID, and the public component reads the file, its conversions, its alt text per language and its
 * focal point from the media record (App\Content\Cms\MediaFiles).
 */
final class MediaPicker
{
    public static function make(string $name): Select
    {
        return Select::make($name)
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => self::options($search))
            ->options(fn (): array => self::options())
            ->getOptionLabelUsing(fn (?string $value): ?string => $value === null ? null : Media::query()->where('uuid', $value)->value('name'))
            ->extraInputAttributes(['dir' => 'ltr']);
    }

    /** @return array<string, string> uuid => name */
    private static function options(string $search = ''): array
    {
        return Media::query()
            ->where('mime_type', 'like', 'image/%')
            ->where('model_type', (new MediaAsset)->getMorphClass())
            ->whereIn('model_id', MediaAsset::query()->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('file_name', 'like', "%{$search}%")))
            ->latest('id')
            ->limit(50)
            ->pluck('name', 'uuid')
            ->all();
    }
}
