<?php

namespace App\Filament\Cms\Pages\Settings;

use App\Filament\Cms\Pages\Settings\Concerns\EditsSettings;
use App\Requests\ReviewTarget;
use App\Settings\NotificationSettings;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Notifications (docs/briefs/design-2.1-and-admin-cms.md §10.5, §12.3; App\Settings\NotificationSettings, read by
 * App\Requests\Notifier and the digest schedule in routes/console.php): the on-duty e-mail list (empty = the enquiry
 * inbox SUMMIT_ENQUIRY_INBOX), the panel notifications for staff, the daily digest and its time (Dubai), the overdue
 * alerts and whether the e-mails carry the visitor's details (never with the `log` mailer). The internal review target
 * the overdue alerts count against is on Office Hours.
 */
class Notifications extends SettingsPage
{
    use EditsSettings;

    protected static string $screen = 'settings_notifications';

    protected static string $settings = NotificationSettings::class;

    protected static ?string $slug = 'settings/notifications';

    protected static string|UnitEnum|null $navigationGroup = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?int $navigationSort = 60;

    public static function getNavigationLabel(): string
    {
        return (string) __('cms_site.settings_notifications.title');
    }

    public function getTitle(): string
    {
        return (string) __('cms_site.settings_notifications.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('cms_site.settings_notifications.subheading');
    }

    public function form(Schema $schema): Schema
    {
        $inbox = (string) config('summit.enquiries.inbox');
        $mailer = (string) config('mail.default');

        return $schema->columns(1)->components([
            Section::make((string) __('cms_site.settings_notifications.recipients'))
                ->description((string) __('cms_site.settings_notifications.recipients_hint'))
                ->schema([
                    Repeater::make('recipients')->hiddenLabel()
                        ->simple(TextInput::make('email')->email()->required()->maxLength(190)->distinct()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'email']))
                        ->defaultItems(0)
                        ->maxItems(20)
                        ->reorderable(false)
                        ->live()
                        ->addActionLabel((string) __('cms_site.settings_notifications.add_recipient')),
                    Text::make(fn (Get $get): HtmlString => new HtmlString(e((string) __('cms_site.settings_notifications.recipients_empty')).' <bdi dir="ltr">'.e($inbox).'</bdi>'))
                        ->visible(fn (Get $get): bool => array_filter((array) $get('recipients'), fn ($v) => filled($v)) === [])
                        ->extraAttributes(['class' => 'cms-settings-note']),
                ]),
            Section::make((string) __('cms_site.settings_notifications.panel'))
                ->schema([
                    Toggle::make('notify_staff')->label((string) __('cms_site.settings_notifications.notify_staff'))
                        ->helperText((string) __('cms_site.settings_notifications.notify_staff_hint')),
                ]),
            Section::make((string) __('cms_site.settings_notifications.digest'))
                ->description((string) __('cms_site.settings_notifications.digest_hint'))
                ->schema([
                    Toggle::make('digest_enabled')->label((string) __('cms_site.settings_notifications.digest_enabled'))->live(),
                    TimePicker::make('digest_time')->label((string) __('cms_site.settings_notifications.digest_time'))
                        ->helperText((string) __('cms_site.settings_notifications.digest_time_hint'))
                        ->seconds(false)->format('H:i')->displayFormat('H:i')->required()
                        ->disabled(fn (Get $get): bool => ! $get('digest_enabled'))
                        ->dehydrated()
                        ->extraInputAttributes(['dir' => 'ltr']),
                ]),
            Section::make((string) __('cms_site.settings_notifications.overdue'))
                ->schema([
                    Toggle::make('overdue_alerts')->label((string) __('cms_site.settings_notifications.overdue_alerts'))
                        ->helperText((string) __('cms_site.settings_notifications.overdue_alerts_hint', ['hours' => ReviewTarget::hours()])),
                    Text::make(fn (): HtmlString => self::officeHoursLink())->visible(fn (): bool => OfficeHours::canAccess()),
                ]),
            Section::make((string) __('cms_site.settings_notifications.details'))
                ->description((string) __('cms_site.settings_notifications.details_hint'))
                ->schema([
                    Toggle::make('include_details')->label((string) __('cms_site.settings_notifications.include_details'))
                        ->helperText((string) __('cms_site.settings_notifications.include_details_hint')),
                    Text::make(new HtmlString(e((string) __('cms_site.settings_notifications.mailer')).' <bdi dir="ltr">'.e($mailer).'</bdi>'
                        .($mailer === 'log' ? '. '.e((string) __('cms_site.settings_notifications.mailer_log')) : '')))
                        ->extraAttributes(['class' => 'cms-settings-note'.($mailer === 'log' ? ' cms-settings-note--warning' : '')]),
                ]),
        ]);
    }

    private static function officeHoursLink(): HtmlString
    {
        return new HtmlString('<a class="cms-settings-link" href="'.e(OfficeHours::getUrl()).'">'.e((string) __('cms_site.settings_notifications.review_target_link')).'</a>');
    }

    protected function fieldLabels(): array
    {
        return ['recipients' => (string) __('cms_site.settings_notifications.recipients')];
    }

    protected function legalFields(): array
    {
        return ['recipients', 'include_details'];
    }

    protected function settingsToForm(array $data): array
    {
        $data['recipients'] = array_values(array_map('strval', (array) ($data['recipients'] ?? [])));

        return $data;
    }

    protected function formToSettings(array $data): array
    {
        $recipients = [];
        foreach ((array) ($data['recipients'] ?? []) as $address) {
            $address = trim((string) $address);
            if ($address !== '' && ! in_array(strtolower($address), array_map('strtolower', $recipients), true)) {
                $recipients[] = $address;
            }
        }
        $time = substr((string) ($data['digest_time'] ?? ''), 0, 5);

        return [
            'recipients' => $recipients,
            'notify_staff' => (bool) ($data['notify_staff'] ?? false),
            'digest_enabled' => (bool) ($data['digest_enabled'] ?? false),
            'digest_time' => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1 ? $time : (string) app(NotificationSettings::class)->digest_time,
            'overdue_alerts' => (bool) ($data['overdue_alerts'] ?? false),
            'include_details' => (bool) ($data['include_details'] ?? false),
        ];
    }
}
