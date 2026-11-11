<?php

namespace App\Models\Content;

final class Guide extends ContentModel
{
    protected $table = 'guides';

    protected const TRANSLATABLE = ['title'];

    public const PLAIN = ['anchor', 'category', 'position'];
}
