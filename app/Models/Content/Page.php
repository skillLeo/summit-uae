<?php

namespace App\Models\Content;

/** A page (P01–P08, L01, L02, U01; brief §8.4): key, slug, SEO, page fields and its blocks. */
final class Page extends ContentModel
{
    public const DATASET = 'pages';

    protected $table = 'pages';
}
