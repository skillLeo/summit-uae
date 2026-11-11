<?php

namespace App\Design\D2;

use Illuminate\Support\HtmlString;
use MessageFormatter;
use NumberFormatter;

/**
 * Office hours in Design 2 (Design 2.1 brief §3.7; fixes D02, D11, D40; shared contract E).
 *
 * 1. The approved hours SENTENCE of a place comes from the Word file that defines that place (owner decision):
 *      sentence('home'), sentence('footer'), sentence('menu')  the home page file's own line, P01 contact.hours:
 *          "Business hours: Monday to Friday, 9 am to 6 pm." (the homepage DOCX also defines the shared footer, and
 *          its red note asks the footer to "reuse the contact and language controls supplied above");
 *      sentence('contact'), sentence('cta')  the Contact page file's line, P07 details.hours: "Office Hours: Monday
 *          to Friday, 9 am to 6 pm UAE time." (the Contact page, and cards built from P07's contact details).
 *    It is approved copy, printed as plain text, so nothing depends on JavaScript. Its digits follow the Arabic
 *    digits setting like every other text of an Arabic page (App\Design\D2\Digits). keepTimes() binds each time to
 *    its am/pm with a no-break space, as the status lines do, so no line ends with "9" and starts with "am"
 *    (whitespace only; the words are the approved line). sentence() returns it bound; a view that prints a
 *    sentence it was handed (the contact card, the inner CTA card) passes it through keepTimes() itself.
 *
 * 2. The LIVE STATUS beside it is proposed copy (lang/{locale}/proposed-d2/hours.php), written by
 *    resources/js/d2/modules/hours.js in each locale's approved time style: "Open now, closes at 6 pm" (EN, as
 *    "9 am to 6 pm"), "Сейчас открыто, закрывается в 18:00" (RU, 24-hour as "с 9:00 до 18:00"), "مفتوح الآن، يُغلق
 *    الساعة 6 مساءً" (AR, as the homepage line "من 9 صباحًا إلى 6 مساءً"), and for visitors outside UAE time "UAE
 *    time is 1 hour behind yours" (D40) on its own line. No middle dots, no response-time promise. Optional:
 *    config('design.d2.live_status') (SUMMIT_LIVE_STATUS, which follows the older SUMMIT_OPEN_NOW_INDICATOR while it
 *    is unset); settings replace the flag in a later phase.
 *    copy() is the script's data, printed once per page as #d2-hours-copy by <x-d2.shell.palette>.
 *
 * 3. line(), time() and offset() write the same strings on the server (styleguide specimens, tests), and sizer()
 *    the longest line the script can write, for a slot that reserves the status line from the first paint.
 */
final class Hours
{
    /** Where each place's approved hours sentence comes from: [page code, key path in the page's content]. */
    public const SENTENCES = [
        'home' => ['P01', 'contact.hours'],
        'footer' => ['P01', 'contact.hours'],
        'menu' => ['P01', 'contact.hours'],
        'contact' => ['P07', 'details.hours'],
        'cta' => ['P07', 'details.hours'],
    ];

    /**
     * CLDR plural categories in the order of the "|"-separated forms in hours.php: Laravel's trans_choice order,
     * plus Russian "other" for fractions ("1,5 часа"), which trans_choice never picks but Intl.PluralRules does.
     */
    private const PLURALS = [
        'en' => ['one', 'other'],
        'ru' => ['one', 'few', 'many', 'other'],
        'ar' => ['zero', 'one', 'two', 'few', 'many', 'other'],
    ];

    /** keepTimes(): pattern => replacement. Only the space (or tab) between the two parts is replaced. */
    private const KEEP_TIMES = [
        // a time and its am / pm: "9 am", "6:30 pm", "9 a.m." (never "9 amounts")
        '/(?<=[0-9\x{0660}-\x{0669}\x{06F0}-\x{06F9}])[ \t]+(?=[ap]\.?m\.?(?![\p{L}\p{N}]))/iu' => "\u{00A0}",
        // a time and صباحًا / مساءً, or the letters ص / م standing alone: "9 صباحًا", "٦ مساءً" (never "5 من")
        '/(?<=[0-9\x{0660}-\x{0669}\x{06F0}-\x{06F9}])[ \t]+(?=صباح|مساء|[صم](?![\p{L}\p{M}]))/u' => "\u{00A0}",
        // a short Russian preposition and the time after it: "с 9:00", "до 18:00", "в 18:00" (not "с понедельника")
        '/(?<![\p{L}\p{N}])(с|со|до|в|во|к|ко|от|по)[ \t]+(?=[0-9]{1,2}[:.][0-9]{2}(?![0-9]))/iu' => "\$1\u{00A0}",
    ];

    /** The proposed live status is shown (default on). */
    public static function liveStatus(): bool
    {
        return (bool) config('design.d2.live_status', true);
    }

    /**
     * Office hours (config/summit.php contact; Settings in a later phase).
     *
     * @return array{timeZone: string, days: list<int>, open: string, close: string}
     */
    public static function schedule(): array
    {
        $hours = (array) config('summit.contact.hours');

        return [
            'timeZone' => (string) config('summit.contact.timezone', 'Asia/Dubai'),
            'days' => array_values(array_map('intval', (array) ($hours['days'] ?? [1, 2, 3, 4, 5]))),
            'open' => (string) ($hours['open'] ?? '09:00'),
            'close' => (string) ($hours['close'] ?? '18:00'),
        ];
    }

    /**
     * The approved hours sentence of a place (SENTENCES) as Design 2 prints it: the stored line with each time kept
     * with its am/pm (keepTimes(), whitespace only); '' when the page has none.
     */
    public static function sentence(string $place, ?string $locale = null): string
    {
        [$page, $path] = self::SENTENCES[$place] ?? self::SENTENCES['home'];

        return self::keepTimes((string) data_get(content()->page($page, $locale)['content'] ?? [], $path, ''));
    }

    /**
     * An hours sentence with each time kept on one line, as the status lines write it (D11): a no-break space
     * (U+00A0) replaces the ordinary space between a time and its "am" / "pm" ("9 am", "6:30 pm", "9 a.m."),
     * between a time and صباحًا / مساءً (or ص / م), and between a short Russian preposition and the time after it
     * ("с 9:00", "до 18:00", "в 18:00"). Whitespace only: read with ordinary spaces, the text is the line it was
     * given, and a second pass changes nothing. Any digits (0-9, ٠-٩, ۰-۹): a sentence is bound before the Arabic
     * digits setting converts the page (App\Design\D2\Digits leaves the no-break space where it is).
     */
    public static function keepTimes(string $text): string
    {
        return (string) (preg_replace(array_keys(self::KEEP_TIMES), array_values(self::KEEP_TIMES), $text) ?? $text);
    }

    /**
     * The #d2-hours-copy payload for modules/hours.js: the switch, the schedule and the proposed templates.
     *
     * @return array<string, mixed>
     */
    public static function copy(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $time = (array) __('proposed.d2.hours.time', [], $locale);

        return [
            'enabled' => self::liveStatus(),
            'proposed' => ! app()->isProduction(),
            'locale' => $locale,
            'hours' => self::schedule(),
            'text' => [
                'open' => d2p('hours.open', [], $locale),
                'closed' => d2p('hours.closed', [], $locale),
                'closedToday' => d2p('hours.closed_today', [], $locale),
                'closedTomorrow' => d2p('hours.closed_tomorrow', [], $locale),
                'days' => (array) __('proposed.d2.hours.days', [], $locale),
                'time' => [
                    'am' => (string) ($time['am'] ?? ''),
                    'pm' => (string) ($time['pm'] ?? ''),
                    'amMinutes' => (string) ($time['am_minutes'] ?? ''),
                    'pmMinutes' => (string) ($time['pm_minutes'] ?? ''),
                ],
                'ahead' => self::forms('hours.ahead', $locale),
                'behind' => self::forms('hours.behind', $locale),
            ],
        ];
    }

    /** A time of day ("18:00") as the status writes it: "at 6 pm", "в 18:00", "الساعة 6 مساءً" (Western digits). */
    public static function time(string $hhmm, ?string $locale = null): string
    {
        [$hour, $minute] = array_map('intval', array_slice(explode(':', $hhmm.':0'), 0, 2));
        $hour = max(0, min(23, $hour));
        $minute = max(0, min(59, $minute));
        $key = ($hour >= 12 ? 'pm' : 'am').($minute === 0 ? '' : '_minutes');

        return self::fill(d2p('hours.time.'.$key, [], $locale), [
            'hour' => (string) ($hour % 12 === 0 ? 12 : $hour % 12),
            'hour_of_day' => (string) $hour,
            'minute' => sprintf('%02d', $minute),
        ]);
    }

    /**
     * One status line: 'open' (closes at the closing time), 'closed_today', 'closed_tomorrow' or 'closed' (opens on
     * $weekday, ISO 1 = Monday … 7 = Sunday).
     */
    public static function line(string $state, int $weekday = 1, ?string $locale = null): string
    {
        $schedule = self::schedule();
        $days = (array) __('proposed.d2.hours.days', [], $locale);

        return self::fill(d2p('hours.'.$state, [], $locale), [
            'time' => self::time($state === 'open' ? $schedule['close'] : $schedule['open'], $locale),
            'day' => (string) ($days[$weekday] ?? ''),
        ]);
    }

    /** The time-difference line for $minutes (UAE offset minus the visitor's; positive: UAE time is ahead); '' at 0. */
    public static function offset(int $minutes, ?string $locale = null): string
    {
        if ($minutes === 0) {
            return '';
        }
        $locale ??= app()->getLocale();
        $hours = abs($minutes) / 60;
        $forms = self::forms($minutes > 0 ? 'hours.ahead' : 'hours.behind', $locale);
        $category = self::category($hours, $locale);

        return self::fill($forms[$category] ?? $forms['other'] ?? (string) end($forms), ['count' => self::number($hours, $locale)]);
    }

    /**
     * The longest status line the script can write in this locale (open, closed today / tomorrow / on each working
     * day), escaped, for slots that reserve the status line from the first paint; null while the live status is off.
     */
    public static function sizer(?string $locale = null): ?HtmlString
    {
        if (! self::liveStatus()) {
            return null;
        }
        $lines = [self::line('open', 1, $locale), self::line('closed_today', 1, $locale), self::line('closed_tomorrow', 1, $locale)];
        foreach (self::schedule()['days'] as $day) {
            $lines[] = self::line('closed', $day, $locale);
        }
        usort($lines, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return new HtmlString(e($lines[0]));
    }

    // ------------------------------------------------------------------------------------------ internals

    /**
     * Plural forms keyed by CLDR category (for Intl.PluralRules in the browser).
     *
     * @return array<string, string>
     */
    private static function forms(string $key, string $locale): array
    {
        $segments = explode('|', (string) __('proposed.d2.'.$key, [], $locale));
        $forms = [];
        foreach (self::PLURALS[$locale] ?? ['one', 'other'] as $i => $category) {
            $forms[$category] = $segments[$i] ?? (string) end($segments);
        }

        return $forms;
    }

    /** The CLDR plural category of a number (as Intl.PluralRules gives it in the browser). */
    private static function category(float $n, string $locale): string
    {
        $categories = self::PLURALS[$locale] ?? ['one', 'other'];
        $pattern = '{n, plural, '.implode(' ', array_map(fn (string $c): string => $c.'{'.$c.'}', $categories)).'}';
        $category = MessageFormatter::formatMessage($locale, $pattern, ['n' => $n]);

        return is_string($category) && in_array($category, $categories, true) ? $category : 'other';
    }

    /** A number with Western digits in the locale's style (1.5, 1,5); Arabic pages convert it with the page. */
    private static function number(float $n, string $locale): string
    {
        $formatter = new NumberFormatter($locale.'@numbers=latn', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);
        $formatter->setAttribute(NumberFormatter::GROUPING_USED, 0);

        return (string) $formatter->format($n);
    }

    /** Fills ":name" placeholders like resources/js/d2/palette/copy.js fill() (unknown names stay as they are). */
    private static function fill(string $template, array $values): string
    {
        return (string) preg_replace_callback(
            '/:([a-z_]+)/i',
            fn (array $m): string => array_key_exists($m[1], $values) ? (string) $values[$m[1]] : $m[0],
            $template,
        );
    }
}
