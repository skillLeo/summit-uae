<?php

namespace App\Models\Content;

final class Page extends ContentModel
{
    protected $table = 'pages';

    protected const TRANSLATABLE = ['h1', 'seo_title', 'meta_description'];

    public const PLAIN = ['slug', 'position'];
}
