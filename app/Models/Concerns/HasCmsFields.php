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
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The CMS fields every content record carries (docs/briefs/design-2.1-and-admin-cms.md §8.5, §8.7): status (draft |
 * published), draft (JSON text of unpublished changes), published_at, publish_at (scheduled), updated_by, copy_source
 * (docx | pending | client_approved), soft deletes (the Trash) and polymorphic revisions. Any saved, deleted or
 * restored record replaces the published-content cache version (App\Content\Cms\ContentCache).
 */
trait HasCmsFields
{
    use LogsActivity;
    use SoftDeletes;

    /**
     * Activity log (brief §12.2, Phase 7; App\Support\ActivityLogging): created, updated (the changed fields only),
     * deleted and restored, on log "admin", with the signed-in user as causer. Translatable columns are logged as their
     * {en, ar, ru} JSON; a draft change keeps only the draft fields that differ (ActivityLogging::content()). The content
     * import writes with the query builder and logs nothing.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(\App\Support\ActivityLogging::LOG)
            ->logAll()
            ->logExcept(['created_at', 'updated_at'])
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->dontLogEmptyChanges()
            ->useAttributeRawValues(method_exists($this, 'getTranslatableAttributes') ? $this->getTranslatableAttributes() : []);
    }

    /** spatie/laravel-activitylog hook: keep the entry small and name the record (ActivityLogging::content()). */
    public function beforeActivityLogged(Activity $activity, string $eventName): void
    {
        \App\Support\ActivityLogging::content($this, $activity, $eventName);
    }

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
