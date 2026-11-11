<?php

namespace App\Models\Content;

final class GuideCategory extends ContentModel
{
    protected $table = 'guide_categories';

    protected const TRANSLATABLE = ['name'];

    public const PLAIN = ['position'];
}
