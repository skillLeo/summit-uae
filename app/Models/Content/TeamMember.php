<?php

namespace App\Models\Content;

use App\Content\Cms\MediaFiles;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A team member (brief §8.11): name, role, bio, focus, languages, the portrait file stem, its focal point [x, y] in % and
 * the photo-pending flag. Its photos are media of the collection "photo" (brief §8.9): the imported original
 * (summit:media-import, custom property source = import) and at most one photo uploaded in the Team screen
 * (source = upload). Only an uploaded photo changes the public portrait (<x-d2.team.portrait>, App\Content\Cms\MediaFiles);
 * its conversions are cut to the 4:5 portrait frame.
 */
final class TeamMember extends ContentModel implements HasMedia
{
    use InteractsWithMedia;

    public const DATASET = 'team_members';

    public const PHOTO = 'photo';

    protected $table = 'team_members';

    protected static function extraCasts(): array
    {
        return ['focal_point' => 'array', 'photo_pending' => 'boolean'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PHOTO)->acceptsMimeTypes(MediaFiles::IMAGES);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        MediaFiles::registerConversions($this, $media, portrait: true, collection: self::PHOTO);
    }
}
