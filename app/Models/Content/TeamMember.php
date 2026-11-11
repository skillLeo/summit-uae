<?php

namespace App\Models\Content;

/** A team member (brief §8.11): name, role, bio, focus, languages, the portrait file stem, its focal point [x, y] in % and the photo-pending flag. */
final class TeamMember extends ContentModel
{
    public const DATASET = 'team_members';

    protected $table = 'team_members';

    protected static function extraCasts(): array
    {
        return ['focal_point' => 'array', 'photo_pending' => 'boolean'];
    }
}
