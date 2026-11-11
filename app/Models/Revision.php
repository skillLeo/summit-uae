<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A snapshot of a content record (docs/briefs/design-2.1-and-admin-cms.md §8.5): written on publish (and by the
 * content import when it creates or changes a record). Restoring one creates a new draft (Phase 4).
 */
final class Revision extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['snapshot' => 'array'];

    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
