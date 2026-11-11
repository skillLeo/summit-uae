<?php

namespace App\Models\Content;

/** A guide category of the knowledge hub (gc-01–gc-03) with its guide codes. */
final class GuideCategory extends ContentModel
{
    public const DATASET = 'guide_categories';

    protected $table = 'guide_categories';
}
