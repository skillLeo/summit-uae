<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
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
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Enquiry extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_SPAM = 'spam';

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
        ];
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
