<?php

namespace App\Models\Content;

use App\Models\Concerns\HasCmsFields;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * A sample document of "Anatomy of a Document" (brief §8.2, §8.11). title is translatable; parts is one structure for
 * every language with {en, ar, ru} texts inside: [{key, label: {…}, can_show: {…}, we_check: {…}}] (the keys are the
 * illustration's field ids, App\Design\D2\Docs). The illustrations stay in code.
 */
final class DocumentSample extends Model
{
    use HasCmsFields;
    use HasTranslations;

    protected $table = 'document_samples';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    public array $translatable = ['title'];

    protected $casts = [
        'position' => 'integer',
        'enabled' => 'boolean',
        'parts' => 'array',
    ];
}
