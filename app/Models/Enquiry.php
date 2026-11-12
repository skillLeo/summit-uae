<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A consultation enquiry from the shared Home / Contact form. API contract: docs/enquiries.md.
 *
 * @property int $id
 * @property string $reference
 * @property string $idempotency_key
 * @property string $locale
 * @property string $status
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $preferred_language
 * @property string|null $matter_type
 * @property string $summary
 * @property Carbon|null $deadline_date
 * @property string|null $deadline_details
 * @property Carbon $consent_at
 * @property string $consent_text
 * @property string $ip_hash
 * @property string|null $attachment_path
 * @property string|null $attachment_mime
 * @property int|null $attachment_size
 * @property Carbon|null $received_at
 * @property int|null $assignee_id
 * @property string|null $outcome
 * @property Carbon|null $first_reviewed_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $status_changed_at
 * @property int $spam_score
 * @property list<string>|null $spam_reasons
 * @property string|null $source_page
 * @property string|null $referrer
 * @property string|null $device_class
 * @property int|null $design
 * @property int|null $privacy_revision_id
 * @property array<string, mixed>|null $extras
 * @property bool $is_demo
 * @property Carbon|null $overdue_notified_at
 * @property Carbon|null $anonymized_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * Inbox (docs/briefs/design-2.1-and-admin-cms.md §10): the workflow columns (assignee, outcome, first_reviewed_at,
 * closed_at, status_changed_at) are written only by App\Requests\Workflow; the intake columns by App\Requests\Intake.
 */
final class Enquiry extends Model
{
    use SoftDeletes;

    public const STATUS_NEW = 'new';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_SPAM = 'spam';

    /** In the order of the workflow (brief §10.2). */
    public const STATUSES = [self::STATUS_NEW, self::STATUS_IN_REVIEW, self::STATUS_CLOSED, self::STATUS_SPAM];

    /** Closing asks for one of these (brief §10.2). */
    public const OUTCOMES = ['engaged', 'not_engaged', 'referred', 'no_reply'];

    /** The UTM values the intake keeps. */
    public const UTM = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    /** ui() key of the consent checkbox label; its text is stored as consent evidence. */
    public const CONSENT_LABEL_KEY = 'form.consent.label';

    /** Private disk for attachments (served only through signed URLs). */
    public const ATTACHMENT_DISK = 'local';

    /** Crockford base32: no I, L, O or U. */
    private const CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    protected $fillable = [
        'reference', 'idempotency_key', 'locale', 'status',
        'name', 'phone', 'email', 'preferred_language', 'matter_type',
        'summary', 'deadline_date', 'deadline_details',
        'consent_at', 'consent_text', 'ip_hash',
        'attachment_path', 'attachment_mime', 'attachment_size',
        // intake context (App\Requests\Intake)
        'received_at', 'spam_score', 'spam_reasons', 'source_page', 'referrer',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'device_class', 'design', 'privacy_revision_id', 'extras',
    ];

    protected $hidden = ['ip_hash', 'idempotency_key'];

    protected $attributes = ['status' => self::STATUS_NEW];

    protected function casts(): array
    {
        return [
            'summary' => 'encrypted',
            'deadline_details' => 'encrypted',
            'deadline_date' => 'date:Y-m-d',
            'consent_at' => 'datetime',
            'attachment_size' => 'integer',
            'received_at' => 'datetime',
            'first_reviewed_at' => 'datetime',
            'closed_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'spam_score' => 'integer',
            'spam_reasons' => 'array',
            'extras' => 'array',
            'design' => 'integer',
            'is_demo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $enquiry): void {
            $enquiry->received_at ??= $enquiry->created_at ?? now();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return HasMany<EnquiryNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(EnquiryNote::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<EnquiryEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(EnquiryEvent::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Requests that count in figures (brief §11.3): never spam; demo records only outside production.
     *
     * @param  Builder<self>  $query
     */
    public function scopeCounted(Builder $query): void
    {
        $query->where('status', '!=', self::STATUS_SPAM);
        if (app()->isProduction()) {
            $query->where('is_demo', false);
        }
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_NEW, self::STATUS_IN_REVIEW], true);
    }

    public function isAnonymised(): bool
    {
        return $this->anonymized_at !== null;
    }

    /**
     * "SLC-" + yymmdd (UAE date) + "-" + 6 random Crockford base32 characters (CSPRNG, ~1.07e9
     * values per day). Uniqueness is enforced by the database; the caller retries on a collision.
     */
    public static function newReference(?CarbonInterface $at = null): string
    {
        $at ??= now((string) config('summit.contact.timezone', 'UTC'));

        $suffix = '';
        for ($i = 0; $i < 6; $i++) {
            $suffix .= self::CROCKFORD[random_int(0, 31)];
        }

        return 'SLC-'.$at->format('ymd').'-'.$suffix;
    }

    /**
     * Whether a submission carrying this enquiry's idempotency key has the same content (a replay
     * of an uncertain submission) rather than a different enquiry reusing the key.
     *
     * @param  array<string, mixed>  $attributes  StoreEnquiryRequest::enquiryAttributes()
     */
    public function isSameSubmission(array $attributes): bool
    {
        foreach (['name', 'phone', 'email', 'preferred_language', 'matter_type', 'summary', 'deadline_details'] as $field) {
            if (($attributes[$field] ?? null) !== $this->getAttribute($field)) {
                return false;
            }
        }

        return ($attributes['deadline_date'] ?? null) === $this->deadline_date?->format('Y-m-d');
    }

    /** HMAC-SHA256 of the IP keyed with the app key: rate limiting and abuse review without storing the IP. */
    public static function hashIp(?string $ip): string
    {
        return hash_hmac('sha256', (string) $ip, (string) config('app.key'));
    }
}
