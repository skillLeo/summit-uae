<?php

namespace App\Models\Content;

use App\Content\Cms\Schema;
use App\Models\Concerns\HasCmsFields;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * Base of the content datasets (App\Content\Cms\Schema): keyed by the stable site-map code (P01, S06, G01, faq-12 …),
 * one column per field. Translatable fields are {en, ar, ru} JSON text (spatie/laravel-translatable); blocks is one
 * structure for every language with the texts inside (brief §8.3, §8.4). CMS fields: App\Models\Concerns\HasCmsFields.
 */
abstract class ContentModel extends Model
{
    use HasCmsFields;
    use HasTranslations;

    /** The dataset name in App\Content\Cms\Schema::DATASETS. */
    public const DATASET = '';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    public function getTranslatableAttributes(): array
    {
        return Schema::DATASETS[static::DATASET]['translatable'];
    }

    protected function casts(): array
    {
        $schema = Schema::DATASETS[static::DATASET];
        $casts = ['position' => 'integer'];
        foreach ($schema['json'] as $column) {
            $casts[$column] = 'array';
        }
        if ($schema['blocks'] !== null) {
            $casts['blocks'] = 'array';
        }

        return $casts + static::extraCasts();
    }

    /** @return array<string, string> */
    protected static function extraCasts(): array
    {
        return [];
    }
}
