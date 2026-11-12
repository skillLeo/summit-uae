<?php

namespace App\Requests;

use App\Mail\EnquiryReceived;
use App\Mail\RequestOverdue;
use App\Mail\RequestsDigest;
use App\Models\Enquiry;
use App\Models\User;
use App\Settings\NotificationSettings;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Request notifications (docs/briefs/design-2.1-and-admin-cms.md §10.5):
 *
 * - a new request: an e-mail to the on-duty list (Notifications settings; empty = SUMMIT_ENQUIRY_INBOX) and a panel
 *   notification (the bell) for the staff who handle requests;
 * - the daily digest at the digest time, Dubai (`summit:requests-digest`, scheduled in routes/console.php);
 * - the overdue alert once per request when it waits longer than the internal target in business hours
 *   (`summit:requests-overdue`);
 * - a panel notification when someone assigns a request to you or mentions you in a note.
 *
 * E-mails carry the reference and a link to the panel only. The visitor's details are added only when the
 * "include details" setting is on and the mailer really sends mail: with the `log` mailer (local and the server
 * today) an e-mail body lands in the log, which must never hold personal data. Panel texts are in each recipient's
 * admin language. Failures are logged with the reference only and never undo anything.
 */
final class Notifier
{
    public function received(Enquiry $enquiry): void
    {
        if ($enquiry->status === Enquiry::STATUS_SPAM) {
            return;
        }
        $this->mail(fn (string $to) => Mail::to($to)->queue(new EnquiryReceived($enquiry, self::includeDetails())), $enquiry);

        if (! self::settings()['notify_staff']) {
            return;
        }
        $this->panel(self::staff(), $enquiry, 'requests.notify.received_title', 'requests.notify.received_body');
    }

    public function assigned(Enquiry $enquiry, ?User $to, ?User $by): void
    {
        if ($to !== null) {
            $this->panel(collect([$to]), $enquiry, 'requests.notify.assigned_title', 'requests.notify.assigned_body', ['name' => $by?->name ?? '']);
        }
    }

    public function mentioned(Enquiry $enquiry, ?User $to, ?User $by): void
    {
        if ($to !== null) {
            $this->panel(collect([$to]), $enquiry, 'requests.notify.mentioned_title', 'requests.notify.mentioned_body', ['name' => $by?->name ?? '']);
        }
    }

    public function overdue(Enquiry $enquiry): void
    {
        $this->mail(fn (string $to) => Mail::to($to)->queue(new RequestOverdue($enquiry)), $enquiry);
        $staff = $enquiry->assignee_id !== null ? User::query()->whereKey($enquiry->assignee_id)->get() : self::staff();
        $this->panel($staff, $enquiry, 'requests.notify.overdue_title', 'requests.notify.overdue_body', ['hours' => ReviewTarget::hours()]);
    }

    /** @param  array<string, mixed>  $figures  App\Requests\Digest::figures() */
    public function digest(array $figures): int
    {
        $sent = 0;
        foreach (self::recipients() as $to) {
            try {
                Mail::to($to)->queue(new RequestsDigest($figures));
                $sent++;
            } catch (Throwable $e) {
                rescue(fn () => Log::error('Requests digest could not be queued', ['exception' => $e::class]), report: false);
            }
        }

        return $sent;
    }

    /** @return list<string> the on-duty e-mail list */
    public static function recipients(): array
    {
        $list = array_values(array_filter(array_map('trim', self::settings()['recipients']), fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL) !== false));
        if ($list === []) {
            $inbox = (string) config('summit.enquiries.inbox');
            $list = $inbox !== '' ? [$inbox] : [];
        }

        return $list;
    }

    /**
     * The staff who handle requests: the users the access rules let open the inbox (App\Filament\Cms\Support\CmsAccess).
     *
     * @return Collection<int, User>
     */
    public static function staff(): Collection
    {
        return User::query()->where('is_admin', true)->where('is_demo', false)->get()
            ->filter(fn (User $user) => \App\Filament\Cms\Support\CmsAccess::can('requests', 'view', $user))
            ->values();
    }

    public static function includeDetails(): bool
    {
        return self::settings()['include_details'] && config('mail.default') !== 'log';
    }

    /** @return array{recipients: list<string>, notify_staff: bool, include_details: bool, digest_enabled: bool, digest_time: string, overdue_alerts: bool} */
    public static function settings(): array
    {
        try {
            $s = app(NotificationSettings::class);

            return [
                'recipients' => array_values(array_map('strval', $s->recipients)),
                'notify_staff' => $s->notify_staff,
                'include_details' => $s->include_details,
                'digest_enabled' => $s->digest_enabled,
                'digest_time' => $s->digest_time,
                'overdue_alerts' => $s->overdue_alerts,
            ];
        } catch (Throwable) {
            return ['recipients' => [], 'notify_staff' => true, 'include_details' => false, 'digest_enabled' => true, 'digest_time' => '09:00', 'overdue_alerts' => true];
        }
    }

    private function mail(callable $send, Enquiry $enquiry): void
    {
        foreach (self::recipients() as $to) {
            try {
                $send($to);
            } catch (Throwable $e) {
                rescue(fn () => Log::error('Request notification could not be queued', [
                    'reference' => $enquiry->reference,
                    'status' => $enquiry->status,
                    'exception' => $e::class,
                ]), report: false);
            }
        }
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  array<string, mixed>  $replace
     */
    private function panel(Collection $users, Enquiry $enquiry, string $title, string $body, array $replace = []): void
    {
        $url = rescue(fn () => route('filament.admin.resources.requests.view', ['record' => $enquiry]), null, report: false);
        foreach ($users as $user) {
            $locale = in_array($user->admin_locale, User::ADMIN_LOCALES, true) ? $user->admin_locale : 'en';
            $replace += ['reference' => $enquiry->reference];
            try {
                Notification::make()
                    ->title((string) __($title, $replace, $locale))
                    ->body((string) __($body, $replace, $locale))
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->actions($url === null ? [] : [
                        Action::make('open')->label((string) __('requests.notify.open', [], $locale))->url($url)->markAsRead(),
                    ])
                    ->sendToDatabase($user);
            } catch (Throwable $e) {
                rescue(fn () => Log::error('Panel notification could not be stored', ['reference' => $enquiry->reference, 'exception' => $e::class]), report: false);
            }
        }
    }
}
