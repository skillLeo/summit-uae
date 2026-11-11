<?php

namespace App\Models\Content;

use App\Models\Concerns\HasCmsFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A navigation menu (brief §8.2, §8.11): primary, actions, mega, footer groups, contact, tabbar. */
final class NavigationMenu extends Model
{
    use HasCmsFields;

    protected $table = 'navigation_menus';

    protected $guarded = ['id'];

    public function items(): HasMany
    {
        return $this->hasMany(NavigationItem::class, 'menu_id')->orderBy('position');
    }
}
