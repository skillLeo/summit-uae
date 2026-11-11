<?php

namespace App\Models;

use App\Content\Cms\MediaFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One item of the Media Library (docs/briefs/design-2.1-and-admin-cms.md §8.9): an image or a PDF download, with one
 * file in the media collection "file". collection groups the library (library, brand, social); copy_source is the
 * approval of its alt text (brief §8.7). The file's alt text per language ({en, ar, ru}), focal point ([x, y] in %),
 * source (upload | import) and dimensions are custom properties of the media row (App\Content\Cms\MediaFiles).
 *
 * Not drafted (no Registry entry): saving is immediate. Soft deletes are the Trash; the file is removed only when the
 * asset is deleted permanently. import_key is the public/ path summit:media-import copied, so the import is idempotent.
 */
final class MediaAsset extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    public const FILE = 'file';

    public const COLLECTIONS = ['library', 'brand', 'social'];

    protected $table = 'media_assets';

    protected $guarded = [];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::FILE)
            ->singleFile()
            ->acceptsMimeTypes(MediaFiles::ACCEPTED);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        MediaFiles::registerConversions($this, $media, portrait: false, collection: self::FILE);
    }

    /** The asset's file (null while it is being created). */
    public function file(): ?Media
    {
        return $this->getFirstMedia(self::FILE);
    }
}
