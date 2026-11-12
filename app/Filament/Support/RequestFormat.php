<?php

namespace App\Filament\Support;

use App\Models\Enquiry;
use App\Models\User;
use App\Requests\BusinessHours;
use App\Requests\ReviewTarget;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Number;

/**
 * How the inbox and the request detail print a request's workflow values (docs/briefs/design-2.1-and-admin-cms.md
 * §10.3, §10.4): waiting time in business hours with the target state, contact given, the two languages, the
 * outcome, deadlines, all in the admin's language with Western digits and Dubai time.
 */
final class RequestFormat
{
    private static ?BusinessHours $hours = null;

    public static function hours(): BusinessHours
    {
        return self::$hours ??= BusinessHours::fromSettings();
    }

    public static function flush(): void
    {
        self::$hours = null;
    }

    public static function number(float $value, int $precision = 1): string
    {
        $out = Number::format(round($value, $precision), maxPrecision: $precision, locale: app()->getLocale().'@numbers=latn');

        return $out === false ? (string) round($value, $precision) : $out;
    }

    /** Business hours the request waited (to its first review, or until now). */
    public static function waited(Enquiry $enquiry): float
    {
        return ReviewTarget::waited($enquiry, self::hours());
    }

    /** within | near | over, against the internal target, for requests still waiting for their first review. */
    public static function targetState(Enquiry $enquiry): ?string
    {
        if ($enquiry->status !== Enquiry::STATUS_NEW) {
            return null;
        }
        $waited = self::waited($enquiry);
        $target = ReviewTarget::hours();
        if ($waited > $target) {
            return 'over';
        }

        return $waited >= $target * (float) config('cms.requests.soon_share', 0.75) ? 'near' : 'within';
    }

    public static function waitingLabel(Enquiry $enquiry): string
    {
        if ($enquiry->status === Enquiry::STATUS_NEW) {
            return (string) __('requests.value.hours', ['hours' => self::number(self::waited($enquiry))]);
        }
        if ($enquiry->first_reviewed_at !== null) {
            return (string) __('requests.value.reviewed_in', ['hours' => self::number(self::waited($enquiry))]);
        }

        return (string) __('requests.value.none');
    }

    public static function waitingColor(Enquiry $enquiry): string
    {
        return match (self::targetState($enquiry)) {
            'over' => 'warning',
            default => 'gray',
        };
    }

    public static function waitingIcon(Enquiry $enquiry): ?string
    {
        return self::targetState($enquiry) === 'over' ? 'heroicon-o-exclamation-triangle' : null;
    }

    public static function waitingTooltip(Enquiry $enquiry): string
    {
        $received = $enquiry->received_at ?? $enquiry->created_at;
        $end = $enquiry->first_reviewed_at ?? now();
        $parts = [
            (string) __('requests.value.business_hours', ['hours' => self::number(self::waited($enquiry))]),
            (string) __('requests.value.calendar_hours', ['hours' => self::number(BusinessHours::calendar($received, $end))]),
            (string) __('requests.field.target', ['hours' => ReviewTarget::hours()]),
        ];
        $state = self::targetState($enquiry);
        if ($state !== null) {
            array_unshift($parts, (string) __('requests.value.'.$state.'_target'));
        }

        return implode("\n", $parts);
    }

    public static function contactGiven(Enquiry $enquiry): string
    {
        return match (true) {
            filled($enquiry->phone) && filled($enquiry->email) => (string) __('requests.value.phone_email'),
            filled($enquiry->phone) => (string) __('requests.value.phone'),
            filled($enquiry->email) => (string) __('requests.value.email'),
            default => (string) __('requests.value.none'),
        };
    }

    public static function languages(Enquiry $enquiry): string
    {
        return EnquiryLabels::language($enquiry->locale);
    }

    public static function preferred(Enquiry $enquiry): string
    {
        return (string) __('requests.value.prefers', ['language' => EnquiryLabels::language($enquiry->preferred_language)]);
    }

    public static function received(CarbonInterface $at): string
    {
        return CarbonImmutable::instance($at)->locale(app()->getLocale())->diffForHumans();
    }

    public static function exact(?CarbonInterface $at): string
    {
        if ($at === null) {
            return (string) __('requests.value.none');
        }

        return (string) __('requests.value.received_tooltip', [
            'date' => CarbonImmutable::instance($at)->setTimezone(self::hours()->timezone())->locale(app()->getLocale())->translatedFormat(AdminFormat::dateTime()),
        ]);
    }

    public static function dateTime(?CarbonInterface $at): string
    {
        return $at === null
            ? (string) __('requests.value.none')
            : CarbonImmutable::instance($at)->setTimezone(self::hours()->timezone())->locale(app()->getLocale())->translatedFormat(AdminFormat::dateTime());
    }

    public static function outcome(?string $outcome): ?string
    {
        return $outcome === null ? null : (string) __('requests.outcome.'.$outcome);
    }

    /** @return array<string, string> */
    public static function outcomeOptions(): array
    {
        $out = [];
        foreach (Enquiry::OUTCOMES as $outcome) {
            $out[$outcome] = (string) __('requests.outcome.'.$outcome);
        }

        return $out;
    }

    public static function assignee(?User $user): string
    {
        if ($user === null) {
            return (string) __('requests.value.unassigned');
        }

        return $user->getKey() === auth()->id() ? (string) __('requests.value.you', ['name' => $user->name]) : $user->name;
    }

    /** Days until the deadline (negative when past), in the office time zone; null without a deadline. */
    public static function deadlineDays(Enquiry $enquiry): ?int
    {
        if ($enquiry->deadline_date === null) {
            return null;
        }
        $today = CarbonImmutable::now(self::hours()->timezone())->startOfDay();
        $deadline = CarbonImmutable::parse($enquiry->deadline_date->format('Y-m-d'), self::hours()->timezone())->startOfDay();

        return (int) $today->diffInDays($deadline, false);
    }

    public static function deadlineSoon(Enquiry $enquiry): bool
    {
        $days = self::deadlineDays($enquiry);

        return $days !== null && $days >= 0 && $days <= 7 && $enquiry->isOpen();
    }

    public static function deadlineRelative(Enquiry $enquiry): ?string
    {
        $days = self::deadlineDays($enquiry);
        if ($days === null) {
            return null;
        }

        return $days >= 0
            ? trans_choice('requests.value.deadline_in', $days, ['count' => $days])
            : trans_choice('requests.value.deadline_past', -$days, ['count' => -$days]);
    }

    /** Digits only, for wa.me (opens a chat with the number; no text, nothing is sent). */
    public static function whatsappUrl(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return $digits !== '' ? 'https://wa.me/'.$digits : null;
    }
}
