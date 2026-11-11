<?php

namespace App\Models\Content;

/** An FAQ (faq-01–faq-74; brief §8.11): question, answer, group (category), order, placements. */
final class Faq extends ContentModel
{
    public const DATASET = 'faqs';

    protected $table = 'faqs';

    protected static function extraCasts(): array
    {
        return ['placements' => 'array'];
    }
}
