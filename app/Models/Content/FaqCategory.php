<?php

namespace App\Models\Content;

final class FaqCategory extends ContentModel
{
    protected $table = 'faq_categories';

    protected const TRANSLATABLE = ['name'];

    public const PLAIN = ['anchor', 'position'];
}
