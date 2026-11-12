<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a request's timeline and status history (docs/briefs/design-2.1-and-admin-cms.md §10.2, §10.4, §10.6):
 * who changed what and when. It never holds personal data: statuses, outcomes, user ids and counts only.
 *
 * @property int $id
 * @property int $enquiry_id
 * @property int|null $user_id
 * @property string $type received | status | assigned | outcome | note | note_edited | note_deleted | export | anonymised | deleted | restored | notified | overdue
 * @property string|null $from
 * @property string|null $to
 * @property array<string, mixed>|null $meta
 * @property Carbon $created_at
 */
final class EnquiryEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<Enquiry, $this> */
    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
