<?php

namespace App\Requests;

use App\Filament\Support\EnquiryLabels;
use App\Models\Enquiry;
use App\Models\EnquiryEvent;
use App\Models\User;
use App\Settings\RequestSettings;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Privacy tools of the inbox (docs/briefs/design-2.1-and-admin-cms.md §10.6): export one person's data (JSON),
 * anonymise, delete, and the retention job. Every action writes a timeline event (no personal data in it) and, from
 * Phase 7, an activity-log entry.
 *
 * "One person" = every request that shares the request's e-mail address or phone number.
 */
final class Privacy
{
    public const ANONYMISED_NAME = 'Anonymised';

    /** @return Collection<int, Enquiry> the requests of the person who sent $enquiry (itself included) */
    public static function personRequests(Enquiry $enquiry): Collection
    {
        if ($enquiry->isAnonymised()) {
            return collect([$enquiry]);
        }

        return Enquiry::query()->withTrashed()
            ->where(function ($q) use ($enquiry): void {
                $q->whereKey($enquiry->getKey());
                if (filled($enquiry->email)) {
                    $q->orWhere('email', $enquiry->email);
                }
                if (filled($enquiry->phone)) {
                    $q->orWhere('phone', $enquiry->phone);
                }
            })
            ->orderBy('received_at')
            ->get();
    }

    /** @return array<string, mixed> the person's data, as stored, for a subject-access request */
    public static function export(Enquiry $enquiry, ?User $by = null): array
    {
        $zone = BusinessHours::fromSettings()->timezone();
        $iso = fn ($at) => $at === null ? null : CarbonImmutable::instance($at)->utc()->toIso8601ZuluString();
        $requests = [];
        foreach (self::personRequests($enquiry) as $request) {
            $request->loadMissing(['notes.user', 'events']);
            $requests[] = [
                'reference' => $request->reference,
                'received_at' => $iso($request->received_at),
                'site_language' => $request->locale,
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'preferred_language' => $request->preferred_language,
                'matter_type' => $request->matter_type,
                'matter_label' => $request->matter_type === null ? null : EnquiryLabels::matter($request->matter_type, 'en'),
                'summary' => $request->summary,
                'deadline_date' => $request->deadline_date?->format('Y-m-d'),
                'deadline_details' => $request->deadline_details,
                'consent' => [
                    'given_at' => $iso($request->consent_at),
                    'text' => $request->consent_text,
                    'privacy_policy_revision' => $request->privacy_revision_id,
                ],
                'status' => $request->status,
                'outcome' => $request->outcome,
                'closed_at' => $iso($request->closed_at),
                'source_page' => $request->source_page,
                'referrer' => $request->referrer,
                'utm' => array_filter(array_combine(Enquiry::UTM, array_map(fn ($k) => $request->getAttribute($k), Enquiry::UTM))),
                'device_class' => $request->device_class,
                'attachment' => $request->attachment_path === null ? null : ['type' => $request->attachment_mime, 'bytes' => $request->attachment_size],
                'extras' => $request->extras,
                'notes' => $request->notes->map(fn ($n) => ['written_at' => $iso($n->created_at), 'by' => $n->user?->name, 'text' => $n->body])->all(),
                'history' => $request->events->map(fn (EnquiryEvent $e) => ['at' => $iso($e->created_at), 'type' => $e->type, 'from' => $e->from, 'to' => $e->to])->all(),
                'anonymised_at' => $iso($request->anonymized_at),
                'deleted_at' => $iso($request->deleted_at),
            ];
            Workflow::make()->event($request, 'export', $by);
        }

        Audit::log('requests', 'export_person', $enquiry, ['reference' => $enquiry->reference, 'requests' => count($requests)], $by);

        return [
            'exported_at' => now()->utc()->toIso8601ZuluString(),
            'time_zone_of_times' => 'UTC',
            'office_time_zone' => $zone,
            'controller' => 'Summit Legal Consultancy',
            'requests' => $requests,
        ];
    }

    /** Removes the personal data of one request (the record and its figures stay). */
    public static function anonymise(Enquiry $enquiry, ?User $by = null): bool
    {
        if ($enquiry->isAnonymised()) {
            return false;
        }
        DB::transaction(function () use ($enquiry, $by): void {
            $path = $enquiry->attachment_path;
            $enquiry->notes()->delete();
            $enquiry->forceFill([
                'name' => self::ANONYMISED_NAME,
                'phone' => null,
                'email' => null,
                'summary' => '',
                'deadline_details' => null,
                'deadline_date' => null,
                'ip_hash' => str_repeat('0', 64),
                'attachment_path' => null,
                'attachment_mime' => null,
                'attachment_size' => null,
                'extras' => null,
                'anonymized_at' => now(),
            ])->save();
            if ($path !== null) {
                rescue(fn () => Storage::disk(Enquiry::ATTACHMENT_DISK)->deleteDirectory(dirname($path)), report: false);
            }
            Workflow::make()->event($enquiry, 'anonymised', $by);
        });
        Audit::log('requests', 'anonymise', $enquiry, ['reference' => $enquiry->reference], $by);

        return true;
    }

    /** Moves a request to the Trash (it is deleted for good by the retention job, or by a super admin). */
    public static function delete(Enquiry $enquiry, ?User $by = null): void
    {
        Workflow::make()->event($enquiry, 'deleted', $by);
        $enquiry->delete();
        Audit::log('requests', 'delete', $enquiry, ['reference' => $enquiry->reference], $by);
    }

    public static function restore(Enquiry $enquiry, ?User $by = null): void
    {
        $enquiry->restore();
        Workflow::make()->event($enquiry, 'restored', $by);
        Audit::log('requests', 'restore', $enquiry, ['reference' => $enquiry->reference], $by);
    }

    /** Deletes a request, its notes, its timeline and its attachment for good. */
    public static function forceDelete(Enquiry $enquiry, ?User $by = null, bool $audit = true): void
    {
        $path = $enquiry->attachment_path;
        $reference = $enquiry->reference;
        $enquiry->forceDelete();
        if ($audit) {
            Audit::log('requests', 'force_delete', null, ['reference' => $reference], $by);
        }
        if ($path !== null) {
            rescue(fn () => Storage::disk(Enquiry::ATTACHMENT_DISK)->deleteDirectory(dirname($path)), report: false);
        }
    }

    /**
     * The retention job (settings): spam deleted after N days; requests in the Trash deleted after the same N days;
     * closed requests anonymised N months after they were closed.
     *
     * @return array{spam_deleted: int, trash_deleted: int, anonymised: int}
     */
    public static function retention(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        try {
            $settings = app(RequestSettings::class);
            $spamDays = max(1, $settings->retention_spam_days);
            $months = max(1, $settings->retention_closed_months);
        } catch (Throwable) {
            [$spamDays, $months] = [30, 24];
        }

        $out = ['spam_deleted' => 0, 'trash_deleted' => 0, 'anonymised' => 0];
        Enquiry::query()->withTrashed()->where('status', Enquiry::STATUS_SPAM)
            ->where('received_at', '<', $now->subDays($spamDays))
            ->eachById(function (Enquiry $e) use (&$out): void {
                self::forceDelete($e, audit: false);
                $out['spam_deleted']++;
            });
        Enquiry::onlyTrashed()->where('deleted_at', '<', $now->subDays($spamDays))
            ->eachById(function (Enquiry $e) use (&$out): void {
                self::forceDelete($e, audit: false);
                $out['trash_deleted']++;
            });
        Enquiry::query()->where('status', Enquiry::STATUS_CLOSED)->whereNull('anonymized_at')
            ->where('closed_at', '<', $now->subMonths($months))
            ->eachById(function (Enquiry $e) use (&$out): void {
                if (self::anonymise($e)) {
                    $out['anonymised']++;
                }
            });

        if (array_sum($out) > 0) {
            Audit::log('requests', 'retention', null, $out + ['spam_days' => $spamDays, 'closed_months' => $months]);
        }

        return $out;
    }
}
