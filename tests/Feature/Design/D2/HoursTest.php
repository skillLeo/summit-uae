<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Digits;
use App\Design\D2\Hours;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Office hours in Design 2.1 (brief §3.7; fixes D02, D11, D40; owner decisions): each place prints the hours
 * sentence of the Word file that defines it, and the proposed live status (resources/js/d2/modules/hours.js, fed by
 * App\Design\D2\Hours::copy()) follows each language's approved time style, without middle dots. The script's own
 * cases are in resources/js/d2/modules/__tests__/hours.test.js, which reads the same copy (hours.copy.json).
 */
final class HoursTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'ru'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    /** A no-break space for "~" (the status copy keeps a number with its unit on one line). */
    private static function nb(string $text): string
    {
        return str_replace('~', "\u{00A0}", $text);
    }

    private function page(string $path, string $locale): string
    {
        app()->setLocale($locale);

        return $this->withUnencryptedCookies(['summit_design' => '2'])->get($path)->assertOk()->getContent();
    }

    /** @return array<string, mixed> the #d2-hours-copy block of a page */
    private function copyBlock(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" id="d2-hours-copy">(.*?)</script>#s', $html, $m), 'one hours copy block');

        return json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_each_place_reads_the_hours_sentence_of_its_word_file(): void
    {
        foreach (self::LOCALES as $locale) {
            $home = content()->page('P01', $locale)['content']['contact']['hours'];
            $contact = content()->page('P07', $locale)['content']['details']['hours'];
            $this->assertNotSame($home, $contact, "{$locale}: two different approved lines");
            foreach (['home', 'footer', 'menu'] as $place) {
                $this->assertSame(Hours::keepTimes($home), Hours::sentence($place, $locale), "{$locale} {$place}: the home page file's own line");
            }
            foreach (['contact', 'cta'] as $place) {
                $this->assertSame(Hours::keepTimes($contact), Hours::sentence($place, $locale), "{$locale} {$place}: the Contact file's line");
            }
            $this->assertSame(Hours::keepTimes($home), Hours::sentence('anywhere-else', $locale));
            // D11: each time is bound to its am/pm, and only the whitespace differs (read with ordinary spaces, the
            // sentence is the approved line)
            foreach (['home' => $home, 'contact' => $contact] as $place => $approved) {
                $this->assertNotSame($approved, Hours::sentence($place, $locale), "{$locale} {$place}: its times are bound");
                $this->assertSame($approved, str_replace("\u{00A0}", ' ', Hours::sentence($place, $locale)), "{$locale} {$place}: the approved words");
            }
        }
        $this->assertSame(self::nb('Business hours: Monday to Friday, 9~am to 6~pm.'), Hours::sentence('home', 'en'), 'owner decision for the home page');
    }

    /**
     * D11 (round 1): the approved sentence broke between the hour and "am" ("…Monday to Friday, 9 / am to 6 pm." in
     * the contact card at 390 px and the trust card at 768 px). keepTimes() binds each time to its am/pm, صباحًا /
     * مساءً, or its Russian preposition with a no-break space, as the status lines do; it changes nothing else.
     */
    public function test_keep_times_binds_each_time_and_changes_only_that_whitespace(): void
    {
        $cases = [
            'Business hours: Monday to Friday, 9 am to 6 pm.' => 'Business hours: Monday to Friday, 9~am to 6~pm.',
            'Office Hours: Monday to Friday, 9 am to 6 pm UAE time.' => 'Office Hours: Monday to Friday, 9~am to 6~pm UAE time.',
            'From 9:30 a.m. to 6 P.M.' => 'From 9:30~a.m. to 6~P.M.',
            'ساعات العمل: الاثنين إلى الجمعة، من 9 صباحًا إلى 6 مساءً.' => 'ساعات العمل: الاثنين إلى الجمعة، من 9~صباحًا إلى 6~مساءً.',
            // the Contact file writes ٩ and ٦: bound before the digits setting converts the page
            'من ٩ صباحًا إلى ٦ مساءً بتوقيت الإمارات.' => 'من ٩~صباحًا إلى ٦~مساءً بتوقيت الإمارات.',
            'من ۹ ص إلى ۶ م' => 'من ۹~ص إلى ۶~م',
            'Часы работы: с понедельника по пятницу, с 9:00 до 18:00.' => 'Часы работы: с понедельника по пятницу, с~9:00 до~18:00.',
            'С 9:00 до 18:00 по времени ОАЭ' => 'С~9:00 до~18:00 по времени ОАЭ',
            // not a time and its am/pm, nor a preposition before a time: unchanged
            '5 amounts, 9 amendments, 3 PMs' => '5 amounts, 9 amendments, 3 PMs',
            'المادة 5 من القانون' => 'المادة 5 من القانون',
            'с понедельника по пятницу, в 2.5 раза' => 'с понедельника по пятницу, в 2.5 раза',
            '' => '',
        ];
        foreach ($cases as $given => $expected) {
            $bound = Hours::keepTimes($given);
            $this->assertSame(self::nb($expected), $bound, $given);
            $this->assertSame($given, str_replace("\u{00A0}", ' ', $bound), "{$given}: whitespace only");
            $this->assertSame($bound, Hours::keepTimes($bound), "{$given}: a second pass changes nothing");
        }
    }

    /**
     * Every place that prints an hours sentence prints it bound: the contact card (home, Contact, offline), the
     * shell and home places that read Hours::sentence() (footer, menu sheet, and until Phase 2 the hero and the
     * trust card) and the inner CTA card. The unbound line, whose "9 am" can break at the space, appears nowhere.
     */
    public function test_every_place_prints_its_hours_sentence_with_the_times_bound(): void
    {
        foreach (self::LOCALES as $locale) {
            $home = content()->page('P01', $locale)['content']['contact']['hours'];
            $contact = content()->page('P07', $locale)['content']['details']['hours'];
            foreach (["/{$locale}/" => $home, "/{$locale}/contact/" => $contact, "/{$locale}/offline/" => $home] as $path => $approved) {
                $html = (string) preg_replace('~<script\b.*?</script>~s', '', $this->page($path, $locale));
                $this->assertStringContainsString(e(Digits::localise(Hours::keepTimes($approved), $locale)), $html, "{$path}: the line, bound");
                $this->assertStringNotContainsString(e(Digits::localise($approved, $locale)), $html, "{$path}: never unbound");
            }
            // The inner CTA card (every service page has contents beside it today, so it shows its hours on pages
            // without contents only): the Contact file's line, bound (a fragment: the page converts the digits).
            app()->setLocale($locale);
            $card = \Illuminate\Support\Facades\Blade::render('<x-d2.inner.cta-card id="hours-cta" />');
            $this->assertStringContainsString('<span>'.e(Hours::keepTimes($contact)).'</span>', $card, "{$locale}: the CTA card");
            $this->assertStringNotContainsString(e($contact), $card, "{$locale}: the CTA card, never unbound");
        }
    }

    public function test_the_home_page_prints_only_its_own_files_line_and_the_contact_page_its_own(): void
    {
        foreach (self::LOCALES as $locale) {
            $home = (string) preg_replace('~<script\b.*?</script>~s', '', $this->page("/{$locale}/", $locale));
            $this->assertStringContainsString(e(Digits::localise(Hours::sentence('home', $locale), $locale)), $home, "{$locale}: the home page file's line");
            $this->assertStringNotContainsString(e(Digits::localise(Hours::sentence('contact', $locale), $locale)), $home, "{$locale}: never the Contact file's line on the home page");

            $contact = $this->page("/{$locale}/contact/", $locale);
            $line = e(Digits::localise(Hours::sentence('contact', $locale), $locale));
            $at = strpos($contact, $line);
            $this->assertNotFalse($at, "{$locale}: the Contact page shows its own line");
            $this->assertStringContainsString('data-hours-status', substr($contact, $at, 600), "{$locale}: with the live status beside it");
        }
    }

    public function test_the_status_lines_follow_the_approved_time_style_of_each_language(): void
    {
        $expected = [
            'en' => [self::nb('Open now, closes at 6~pm'), self::nb('Closed now, opens today at 9~am'), self::nb('Closed now, opens tomorrow at 9~am'), self::nb('Closed now, opens Monday at 9~am')],
            'ru' => [self::nb('Сейчас открыто, закрывается в~18:00'), self::nb('Сейчас закрыто, откроется сегодня в~9:00'), self::nb('Сейчас закрыто, откроется завтра в~9:00'), self::nb('Сейчас закрыто, откроется в понедельник в~9:00')],
            'ar' => [self::nb('مفتوح الآن، يُغلق الساعة 6~مساءً'), self::nb('مغلق الآن، يفتح اليوم الساعة 9~صباحًا'), self::nb('مغلق الآن، يفتح غدًا الساعة 9~صباحًا'), self::nb('مغلق الآن، يفتح يوم الاثنين الساعة 9~صباحًا')],
        ];
        foreach ($expected as $locale => $lines) {
            $this->assertSame($lines, [
                Hours::line('open', 1, $locale), Hours::line('closed_today', 1, $locale),
                Hours::line('closed_tomorrow', 1, $locale), Hours::line('closed', 1, $locale),
            ], $locale);
        }

        // The approved sentences use the same style, printed with the same no-break spaces: "9~am to 6~pm",
        // "с~9:00 до~18:00", "من 9~صباحًا إلى 6~مساءً".
        $this->assertStringContainsString(self::nb('9~am to 6~pm'), Hours::sentence('home', 'en'));
        $this->assertStringContainsString(self::nb('с~9:00 до~18:00'), Hours::sentence('home', 'ru'));
        $this->assertStringContainsString(self::nb('من 9~صباحًا إلى 6~مساءً'), Hours::sentence('home', 'ar'));

        foreach (self::LOCALES as $locale) {
            foreach (['open', 'closed', 'closed_today', 'closed_tomorrow'] as $key) {
                $line = Hours::line($key, 3, $locale);
                $this->assertDoesNotMatchRegularExpression('/[·•]|:time|:day/u', $line, "{$locale} {$key}: no middle dot, no placeholder (D02)");
                $this->assertDoesNotMatchRegularExpression('/respond|reply|within|24\/7|ответ|نرد/iu', $line, 'no response-time promise');
            }
        }
    }

    public function test_times_off_the_hour_noon_and_midnight(): void
    {
        $this->assertSame([self::nb('at 8:30~am'), self::nb('at 12~pm'), self::nb('at 12:15~am'), self::nb('at 11:05~pm')], array_map(fn ($t) => Hours::time($t, 'en'), ['08:30', '12:00', '00:15', '23:05']));
        $this->assertSame([self::nb('в~8:30'), self::nb('в~12:00'), self::nb('в~0:15'), self::nb('в~23:05')], array_map(fn ($t) => Hours::time($t, 'ru'), ['08:30', '12:00', '00:15', '23:05']));
        $this->assertSame([self::nb('الساعة 8:30~صباحًا'), self::nb('الساعة 12~مساءً'), self::nb('الساعة 12:15~صباحًا')], array_map(fn ($t) => Hours::time($t, 'ar'), ['08:30', '12:00', '00:15']));
    }

    public function test_the_time_difference_reads_uae_time_is_n_hours_behind_yours(): void
    {
        // D40: "UAE is 1 h behind you" becomes "UAE time is 1~hour behind yours".
        $this->assertSame(self::nb('UAE time is 1~hour behind yours'), Hours::offset(-60, 'en'));
        $this->assertSame(self::nb('UAE time is 1~hour ahead of yours'), Hours::offset(60, 'en'));
        $this->assertSame(self::nb('UAE time is 4~hours ahead of yours'), Hours::offset(240, 'en'));
        $this->assertSame(self::nb('UAE time is 1.5~hours behind yours'), Hours::offset(-90, 'en'));
        $this->assertSame('', Hours::offset(0, 'en'), 'nothing for a visitor on UAE time');

        $this->assertSame(
            [self::nb('на~1~час'), self::nb('на~2~часа'), self::nb('на~5~часов'), self::nb('на~11~часов'), self::nb('на~1,5~часа')],
            array_map(fn ($m) => mb_substr(Hours::offset($m, 'ru'), mb_strpos(Hours::offset($m, 'ru'), self::nb('на~'))), [-60, -120, -300, -660, -90]),
        );
        $this->assertStringStartsWith('Время в ОАЭ отстаёт от вашего', Hours::offset(-60, 'ru'));
        $this->assertStringStartsWith('Время в ОАЭ опережает ваше', Hours::offset(60, 'ru'));

        $this->assertSame([
            'توقيت الإمارات متأخر عن توقيتك بساعة واحدة',
            'توقيت الإمارات متأخر عن توقيتك بساعتين',
            self::nb('توقيت الإمارات متأخر عن توقيتك بـ~5~ساعات'),
            self::nb('توقيت الإمارات متأخر عن توقيتك بـ~11~ساعة'),
            self::nb('توقيت الإمارات متأخر عن توقيتك بـ~1.5~ساعة'),
        ], array_map(fn ($m) => Hours::offset($m, 'ar'), [-60, -120, -300, -660, -90]));
        $this->assertSame(self::nb('توقيت الإمارات متقدّم على توقيتك بـ~4~ساعات'), Hours::offset(240, 'ar'));
    }

    public function test_the_copy_block_on_every_page_is_the_hours_data_and_matches_the_script_fixture(): void
    {
        $fixture = json_decode((string) file_get_contents(resource_path('js/d2/modules/__tests__/hours.copy.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach (self::LOCALES as $locale) {
            foreach (["/{$locale}/", "/{$locale}/contact/", "/{$locale}/faq/"] as $path) {
                $block = $this->copyBlock($this->page($path, $locale));
                $this->assertSame(json_decode(json_encode(Hours::copy($locale)), true), $block, "{$path}: Hours::copy()");
            }
            $this->assertTrue($block['enabled']);
            $this->assertSame(['timeZone' => 'Asia/Dubai', 'days' => [1, 2, 3, 4, 5], 'open' => '09:00', 'close' => '18:00'], $block['hours']);
            // The Vitest fixture is this copy: when hours.php changes, regenerate it so both test suites read the same words.
            $this->assertSame($fixture[$locale], $block['text'], "{$locale}: resources/js/d2/modules/__tests__/hours.copy.json is current");
        }
    }

    public function test_the_live_status_switch_and_the_reserve_line(): void
    {
        $this->assertTrue(config('design.d2.live_status'), 'on by default');
        app()->setLocale('en');
        $this->assertSame(self::nb('Closed now, opens Wednesday at 9~am'), html_entity_decode((string) Hours::sizer('en')), 'the longest line the script can write');
        $this->assertSame(self::nb('Сейчас закрыто, откроется в понедельник в~9:00'), html_entity_decode((string) Hours::sizer('ru')));
        foreach (self::LOCALES as $locale) {
            $sizer = (string) Hours::sizer($locale);
            $lines = [Hours::line('open', 1, $locale), Hours::line('closed_today', 1, $locale), Hours::line('closed_tomorrow', 1, $locale)];
            foreach ([1, 2, 3, 4, 5] as $day) {
                $lines[] = Hours::line('closed', $day, $locale);
                $this->assertGreaterThanOrEqual(mb_strlen(end($lines)), mb_strlen(html_entity_decode($sizer)), "{$locale}: no line is longer");
            }
            $this->assertContains(html_entity_decode($sizer), $lines);
        }

        config(['design.d2.live_status' => false]);
        $this->assertNull(Hours::sizer('en'), 'nothing to reserve without the live status');
        $this->assertFalse($this->copyBlock($this->page('/en/', 'en'))['enabled'], 'the script stays idle');
    }

    /**
     * Design 2's switch is SUMMIT_LIVE_STATUS (config/design.php d2.live_status). Before Design 2.1 the status
     * followed SUMMIT_OPEN_NOW_INDICATOR (Design 1's switch), so while the new flag is unset the old one decides: an
     * environment that turned the status off with it keeps it off (docs/d2/running.md §6, .env.example).
     */
    public function test_the_live_status_flag_falls_back_to_the_older_open_now_switch(): void
    {
        $read = function (array $env): bool {
            $saved = [];
            foreach (['SUMMIT_LIVE_STATUS', 'SUMMIT_OPEN_NOW_INDICATOR'] as $key) {
                $saved[$key] = [$_SERVER[$key] ?? null, $_ENV[$key] ?? null, getenv($key)];
                unset($_SERVER[$key], $_ENV[$key]);
                putenv($key);
                if (array_key_exists($key, $env)) {
                    $_SERVER[$key] = $_ENV[$key] = $env[$key];
                    putenv("{$key}={$env[$key]}");
                }
            }
            try {
                return (require config_path('design.php'))['d2']['live_status'];
            } finally {
                foreach ($saved as $key => [$server, $value, $process]) {
                    unset($_SERVER[$key], $_ENV[$key]);
                    putenv($key);
                    if ($server !== null) {
                        $_SERVER[$key] = $server;
                    }
                    if ($value !== null) {
                        $_ENV[$key] = $value;
                    }
                    if ($process !== false) {
                        putenv("{$key}={$process}");
                    }
                }
            }
        };

        $this->assertTrue($read([]), 'on when neither flag is set');
        $this->assertFalse($read(['SUMMIT_OPEN_NOW_INDICATOR' => 'false']), 'off with the older switch off');
        $this->assertTrue($read(['SUMMIT_OPEN_NOW_INDICATOR' => 'true']));
        $this->assertTrue($read(['SUMMIT_OPEN_NOW_INDICATOR' => 'false', 'SUMMIT_LIVE_STATUS' => 'true']), 'the new flag wins when set');
        $this->assertFalse($read(['SUMMIT_OPEN_NOW_INDICATOR' => 'true', 'SUMMIT_LIVE_STATUS' => 'false']));
        $this->assertFalse($read(['SUMMIT_LIVE_STATUS' => 'false']));
    }

    public function test_the_proposed_copy_is_complete_and_has_no_middle_dots(): void
    {
        foreach (self::LOCALES as $locale) {
            $copy = require lang_path("{$locale}/proposed-d2/hours.php");
            array_walk_recursive($copy, function ($value, $key) use ($locale) {
                $this->assertStringNotContainsString('·', (string) $value, "{$locale} {$key}");
            });
            $this->assertCount(7, $copy['days']);
            $this->assertSame(['am', 'pm', 'am_minutes', 'pm_minutes'], array_keys($copy['time']));
            // 12-hour clock in English and Arabic (as the approved "9 am to 6 pm" / "9 صباحًا"), 24-hour in Russian.
            foreach ($copy['time'] as $key => $pattern) {
                $this->assertMatchesRegularExpression($locale === 'ru' ? '/:hour_of_day::minute/' : (str_ends_with($key, 'minutes') ? '/:hour::minute/' : '/:hour(?!_|:)/'), $pattern, "{$locale} time.{$key}");
            }
        }
        // D40 in the copy itself
        $this->assertStringStartsWith(self::nb('UAE time is :count~hour behind yours|'), __('proposed.d2.hours.behind', [], 'en'));
    }
}
