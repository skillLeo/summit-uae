<?php

namespace App\Models\Concerns;

use App\Content\Cms\ContentCache;
use App\Content\Cms\Status;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The CMS fields every content record carries (docs/briefs/design-2.1-and-admin-cms.md §8.5, §8.7): status (draft |
 * published), draft (JSON text of unpublished changes), published_at, publish_at (scheduled), updated_by, copy_source
 * (docx | pending | client_approved), soft deletes (the Trash) and polymorphic revisions. Any saved, deleted or
 * restored record replaces the published-content cache version (App\Content\Cms\ContentCache).
 */
trait HasCmsFields
{
    use SoftDeletes;

    public static function bootHasCmsFields(): void
    {
        $bump = fn () => ContentCache::bump();
        static::saved($bump);
        static::deleted($bump);
        static::restored($bump);
        static::forceDeleted($bump);
    }

    public function initializeHasCmsFields(): void
    {
        $this->mergeCasts([
            'draft' => 'array',
            'published_at' => 'datetime',
            'publish_at' => 'datetime',
        ]);
    }

    /** Only what the public site may show: published and not in the Trash. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), Status::PUBLISHED);
    }

    public function isPublished(): bool
    {
        return $this->status === Status::PUBLISHED;
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable')->latest('id');
    }
}
