<?php

namespace App\Models\Content;

/** An FAQ group (cat-01–cat-12; the brief's faq_groups, §8.11). */
final class FaqCategory extends ContentModel
{
    public const DATASET = 'faq_categories';

    protected $table = 'faq_categories';
}
