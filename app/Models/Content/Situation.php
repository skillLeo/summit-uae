<?php

namespace App\Models\Content;

use App\Models\Concerns\HasCmsFields;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * A situation of "Start your matter" and "Where Do You Stand?" (brief §8.2, §8.11): one approved P02 line "This service
 * may be relevant if:" of a practice area. code = practice area + 1-based position (S05-2). documents, first_steps and
 * mistakes override the practice area's lists when set (null = the practice area's). featured = a quick pick.
 */
final class Situation extends Model
{
    use HasCmsFields;
    use HasTranslations;

    protected $table = 'situations';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    public array $translatable = ['title', 'documents', 'first_steps', 'mistakes'];

    protected $casts = [
        'position' => 'integer',
        'featured' => 'boolean',
        'featured_position' => 'integer',
    ];
}
