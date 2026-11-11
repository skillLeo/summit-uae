<?php

namespace App\Models\Content;

use App\Models\Concerns\HasCmsFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * One link of a navigation menu (brief §8.2, §8.11). link_key is the client's site-map key (H-L001 …, WA01, TEL, EMAIL;
 * read-only in the admin). target_type: page | record | anchor | url | tel | email | whatsapp; target is a site-map id
 * (P02, S06, A01 …) or a URL. label null = the target record's own label (a practice area's title, a site's name).
 */
final class NavigationItem extends Model
{
    use HasCmsFields;
    use HasTranslations;

    public const TARGET_TYPES = ['page', 'record', 'anchor', 'url', 'tel', 'email', 'whatsapp'];

    protected $table = 'navigation_items';

    protected $guarded = ['id'];

    public array $translatable = ['label', 'registry_label'];

    protected $casts = [
        'position' => 'integer',
        'new_tab' => 'boolean',
        'enabled' => 'boolean',
        'options' => 'array',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(NavigationMenu::class, 'menu_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }
}
