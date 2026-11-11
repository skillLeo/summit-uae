<?php

namespace App\Models\Content;

final class TeamMember extends ContentModel
{
    protected $table = 'team_members';

    protected const TRANSLATABLE = ['name'];

    public const PLAIN = ['anchor', 'position', 'portrait'];
}
