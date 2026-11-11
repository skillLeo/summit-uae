<?php

namespace App\Content\Cms;

/** Publishing status of a content record (docs/briefs/design-2.1-and-admin-cms.md §8.5). */
final class Status
{
    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const ALL = [self::DRAFT, self::PUBLISHED];
}
