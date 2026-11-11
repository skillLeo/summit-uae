<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A redirect (docs/briefs/design-2.1-and-admin-cms.md §8.3, §8.11): from_path → to_path with 301 or 302. Changing a
 * slug in the admin creates one automatically (Phase 4).
 */
final class Redirect extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'code' => 'integer',
        'hits' => 'integer',
        'enabled' => 'boolean',
        'last_hit_at' => 'datetime',
    ];
}
