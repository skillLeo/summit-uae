<?php

namespace App\Models\Content;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * Base for the imported content tables. Core columns are real columns; any other imported
 * field lives in the translatable `data` JSON column (per-language structures) or the
 * language-independent `meta` JSON column.
 */
abstract class ContentModel extends Model
{
    use HasTranslations;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = ['meta' => 'array'];

    /** Translatable core columns besides `data`. */
    protected const TRANSLATABLE = [];

    /** Language-independent core columns besides `meta`. */
    public const PLAIN = [];

    public function getTranslatableAttributes(): array
    {
        return [...static::TRANSLATABLE, 'data'];
    }

    /** Core column names that the seeder writes directly. */
    public static function columns(): array
    {
        return [...static::TRANSLATABLE, ...static::PLAIN];
    }
}
