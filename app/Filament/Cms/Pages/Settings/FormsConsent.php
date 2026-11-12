<?php

namespace App\Filament\Cms\Pages\Settings;

use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Tree;
use App\Filament\Cms\Pages\Settings\Concerns\EditsSettings;
use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Cms\Support\CmsLabels;
use App\Filament\Resources\InterfaceText\InterfaceTextResource;
use App\Filament\Support\RequestFormat;
use App\Models\Content\UiString;
use App\Models\Revision;
use App\Settings\RequestSettings;
use BackedEnum;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Throwable;
use UnitEnum;

/**
 * Forms and Consent (docs/briefs/design-2.1-and-admin-cms.md §12.3, §9.4, §10.6; App\Settings\RequestSettings): the
 * consent text of the request form (approved DOCX copy: shown here, edited in Interface Text with its drafts) and the
 * Privacy Policy revision it is linked to, the attachment field (locked off until uploads are scanned), the spam rules,
 * the retention periods and which requests a case handler sees. Every field has a legal or data impact, so the
 * confirmation says so. App\Requests\Intake, SpamCheck, Privacy and App\Filament\Cms\Support\CmsAccess read the values.
 */
class FormsConsent extends SettingsPage
{
    use EditsSettings;

    protected static string $screen = 'settings_forms';

    protected static string $settings = RequestSettings::class;

    protected static ?string $slug = 'settings/forms';

    protected static string|UnitEnum|null $navigationGroup = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?int $navigationSort = 50;

    public const CONSENT_KEY = 'form.consent.label';

    public static function getNavigationLabel(): string
    {
        return (string) __('cms_site.settings_forms.title');
    }

    public function getTitle(): string
    {
        return (string) __('cms_site.settings_forms.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('cms_site.settings_forms.subheading');
    }

    public function form(Schema $schema): Schema
    {
        $ltr = ['dir' => 'ltr'];
        $number = fn (string $name, int $min, int $max, string $suffix) => TextInput::make($name)
            ->label((string) __("cms_site.settings_forms.{$name}"))
            ->helperText((string) __("cms_site.settings_forms.{$name}_hint"))
            ->required()->numeric()->integer()->minValue($min)->maxValue($max)
            ->suffix((string) __("cms_site.settings_forms.unit.{$suffix}"))
            ->extraInputAttributes($ltr + ['inputmode' => 'numeric']);

        return $schema->columns(1)->components([
            Section::make((string) __('cms_site.settings_forms.consent'))
                ->description((string) __('cms_site.settings_forms.consent_hint'))
                ->schema([
                    Text::make(fn (): HtmlString => self::consentText())->extraAttributes(['class' => 'cms-settings-consent']),
                    Select::make('consent_privacy_revision_id')->label((string) __('cms_site.settings_forms.revision'))
                        ->helperText((string) __('cms_site.settings_forms.revision_hint'))
                        ->options(fn (): array => self::revisionOptions())
                        ->placeholder(fn (): string => self::latestPlaceholder())
                        ->live()
                        ->in(fn (): array => array_keys(self::revisionOptions())),
                    Text::make(fn (Get $get): string => self::newerRevisionWarning($get('consent_privacy_revision_id')) ?? '')
                        ->visible(fn (Get $get): bool => self::newerRevisionWarning($get('consent_privacy_revision_id')) !== null)
                        ->extraAttributes(['class' => 'cms-settings-note cms-settings-note--warning', 'role' => 'status']),
                ]),
            Section::make((string) __('cms_site.settings_forms.attachments'))
                ->description((string) __('cms_site.settings_forms.attachments_hint'))
                ->schema([
                    Text::make(fn (): HtmlString => self::stateLine(
                        (string) __('cms_site.settings_forms.attachments_field'),
                        (bool) config('summit.features.attachments_enabled'),
                        (string) __('cms_site.settings_forms.attachments_locked'),
                    ))->extraAttributes(['class' => 'cms-settings-state']),
                ]),
            Section::make((string) __('cms_site.settings_forms.spam'))
                ->description((string) __('cms_site.settings_forms.spam_hint'))
                ->schema([
                    Grid::make(['default' => 1, 'md' => 3])->schema([
                        $number('spam_min_fill_seconds', 0, 120, 'seconds'),
                        $number('spam_max_links', 0, 20, 'links'),
                        $number('spam_burst_per_day', 1, 100, 'requests'),
                    ]),
                    Text::make(fn (): HtmlString => self::stateLine(
                        (string) __('cms_site.settings_forms.turnstile'),
                        (bool) config('summit.turnstile.enabled'),
                        (string) __('cms_site.settings_forms.turnstile_note'),
                    ))->extraAttributes(['class' => 'cms-settings-state']),
                ]),
            Section::make((string) __('cms_site.settings_forms.retention'))
                ->description((string) __('cms_site.settings_forms.retention_hint'))
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        $number('retention_spam_days', 1, 365, 'days'),
                        $number('retention_closed_months', 1, 120, 'months'),
                    ]),
                ]),
            Section::make((string) __('cms_site.settings_forms.case_handlers'))
                ->description((string) __('cms_site.settings_forms.case_handlers_hint'))
                ->schema([
                    Radio::make('case_handler_scope')->hiddenLabel()
                        ->options([
                            'all' => (string) __('cms_site.settings_forms.scope_all'),
                            'assigned' => (string) __('cms_site.settings_forms.scope_assigned'),
                        ])
                        ->required(),
                ]),
        ]);
    }

    /** The approved consent text in the three languages, as published in Interface Text, with a link to edit it. */
    public static function consentText(): HtmlString
    {
        $row = rescue(fn () => UiString::query()->where('group', 'ui')->where('key', self::CONSENT_KEY)->first(), null, report: false);
        $lines = [];
        foreach (Tree::LOCALES as $locale) {
            $text = $row?->getTranslation($locale) ?? ui(self::CONSENT_KEY, $locale);
            $lines[] = '<div class="cms-settings-consent__row"><span class="cms-settings-consent__lang">'.e(CmsLabels::locale($locale)).'</span>'
                .'<span class="cms-settings-consent__text" lang="'.e($locale).'" dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'">'.e($text).'</span></div>';
        }
        $footer = [];
        if ($row !== null && Publisher::draft($row) !== null) {
            $footer[] = '<span class="cms-settings-consent__draft">'.e((string) __('cms_site.settings_forms.consent_draft')).'</span>';
        }
        if ($row !== null && CmsAccess::can('interface_text', 'view')) {
            $footer[] = '<a class="cms-settings-link" href="'.e(InterfaceTextResource::getUrl('edit', ['record' => $row])).'">'.e((string) __('cms_site.settings_forms.consent_edit')).'</a>';
        }

        return new HtmlString(implode('', $lines).($footer === [] ? '' : '<div class="cms-settings-consent__footer">'.implode('', $footer).'</div>'));
    }

    /** @return array<int, string> revision id => "Revision 8, published 11 Nov 2026, 22:20" (newest first) */
    public static function revisionOptions(): array
    {
        $out = [];
        foreach (self::revisions() as $revision) {
            $out[(int) $revision->id] = (string) __('cms_site.settings_forms.revision_option', [
                'id' => $revision->id,
                'date' => RequestFormat::dateTime($revision->created_at),
            ]);
        }

        return $out;
    }

    /** @return list<Revision> */
    private static function revisions(): array
    {
        try {
            return Revision::query()->where('revisionable_type', 'page')
                ->where('revisionable_id', (string) config('cms.privacy_page', 'L01'))
                ->orderByDesc('id')->limit(50)->get()->all();
        } catch (Throwable) {
            return [];
        }
    }

    private static function latestPlaceholder(): string
    {
        $latest = self::revisions()[0] ?? null;

        return $latest === null
            ? (string) __('cms_site.settings_forms.revision_latest_none')
            : (string) __('cms_site.settings_forms.revision_latest', ['id' => $latest->id]);
    }

    /** A warning when the consent links to an older revision than the latest published one. */
    public static function newerRevisionWarning(mixed $linked): ?string
    {
        if ($linked === null || $linked === '') {
            return null;
        }
        $latest = self::revisions()[0] ?? null;
        if ($latest === null || (int) $latest->id <= (int) $linked) {
            return null;
        }

        return (string) __('cms_site.settings_forms.revision_newer', [
            'id' => $latest->id,
            'date' => RequestFormat::dateTime($latest->created_at),
            'linked' => (int) $linked,
        ]);
    }

    /** "Attachment field: Off. <why>" as one state line (read-only: the environment decides). */
    private static function stateLine(string $label, bool $on, string $note): HtmlString
    {
        $state = (string) __($on ? 'cms_site.settings.state_on' : 'cms_site.settings.state_off');

        return new HtmlString('<span class="cms-settings-state__label">'.e($label).'</span>'
            .'<span class="cms-settings-state__value" data-state="'.($on ? 'on' : 'off').'">'.e($state).'</span>'
            .'<span class="cms-settings-state__note">'.e($note).'</span>');
    }

    protected function fieldLabels(): array
    {
        return [
            'consent_privacy_revision_id' => (string) __('cms_site.settings_forms.revision'),
            'case_handler_scope' => (string) __('cms_site.settings_forms.case_handlers'),
        ];
    }

    protected function legalFields(): array
    {
        return ['consent_privacy_revision_id', 'spam_min_fill_seconds', 'spam_max_links', 'spam_burst_per_day', 'retention_spam_days', 'retention_closed_months', 'case_handler_scope'];
    }

    protected function formToSettings(array $data): array
    {
        $revision = $data['consent_privacy_revision_id'] ?? null;

        return [
            'spam_min_fill_seconds' => (int) $data['spam_min_fill_seconds'],
            'spam_max_links' => (int) $data['spam_max_links'],
            'spam_burst_per_day' => (int) $data['spam_burst_per_day'],
            'retention_spam_days' => (int) $data['retention_spam_days'],
            'retention_closed_months' => (int) $data['retention_closed_months'],
            'case_handler_scope' => ($data['case_handler_scope'] ?? 'all') === 'assigned' ? 'assigned' : 'all',
            'consent_privacy_revision_id' => $revision === null || $revision === '' ? null : (int) $revision,
        ];
    }
}
