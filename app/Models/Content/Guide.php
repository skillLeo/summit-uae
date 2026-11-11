<?php

namespace App\Models\Content;

/** A legal guide (G01–G09, sections of P03; brief §8.11): card and homepage fields, the body as blocks. */
final class Guide extends ContentModel
{
    public const DATASET = 'guides';

    protected $table = 'guides';

    protected static function extraCasts(): array
    {
        return ['standalone' => 'boolean'];
    }
}
