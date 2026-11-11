<?php

namespace App\Models\Content;

final class Faq extends ContentModel
{
    protected $table = 'faqs';

    protected const TRANSLATABLE = ['question'];

    public const PLAIN = ['anchor', 'category', 'position'];
}
