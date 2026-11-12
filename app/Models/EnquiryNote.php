<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An internal note on a request (docs/briefs/design-2.1-and-admin-cms.md §10.4): staff only, encrypted at rest like the
 * visitor's summary, with the users it mentions. Its author may edit or delete it within ten minutes
 * (App\Requests\Workflow::EDIT_WINDOW_MINUTES).
 *
 * @property int $id
 * @property int $enquiry_id
 * @property int|null $user_id
 * @property string $body
 * @property list<int>|null $mentions
 * @property Carbon|null $edited_at
 * @property Carbon $created_at
 */
final class EnquiryNote extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'mentions' => 'array',
            'edited_at' => 'datetime',
        ];
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
