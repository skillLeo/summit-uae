<?php

namespace App\Models\Content;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

final class Setting extends Model
{
    use HasTranslations;

    protected $table = 'settings';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    public array $translatable = ['value'];
}
