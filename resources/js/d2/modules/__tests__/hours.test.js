import { afterEach, describe as group, expect, it } from 'vitest';
import hours, { describe, formatTime, hoursStatus, inLanguage, paint, zonedClock } from '../hours.js';
// The `text` block of #d2-hours-copy for each locale, exactly as App\Design\D2\Hours::copy() builds it from
// lang/{en,ar,ru}/proposed-d2/hours.php (HoursTest fails when the two differ).
import COPY from './hours.copy.json';

// Lives outside modules/*.js on purpose: app.js eagerly imports every modules/*.js file.

/** An instant given as UAE wall-clock time (Asia/Dubai is UTC+4 all year). */
const uae = (y, m, d, hh, mm = 0) => Date.UTC(y, m - 1, d, hh - 4, mm);

// 9 Nov 2026 is a Monday; 12 Nov a Thursday, 13 Nov a Friday, 14 Nov a Saturday, 15 Nov a Sunday.
// (New York is on standard time, UTC−5, from 1 Nov 2026: 9 h behind the UAE.)
const MON = [2026, 11, 9];
const TUE = [2026, 11, 10];
const THU = [2026, 11, 12];
const FRI = [2026, 11, 13];
const SAT = [2026, 11, 14];
const SUN = [2026, 11, 15];

const OPTIONS = { timeZone: 'Asia/Dubai', days: [1, 2, 3, 4, 5], open: '09:00', close: '18:00', visitorOffset: 240 };
const { en: EN, ar: AR, ru: RU } = COPY;

/** The status strings for an instant, as the module paints them. */
const text = (now, options = OPTIONS, copy = EN, locale = 'en', digits = 'western') => {
    const view = describe(hoursStatus(now, options), copy, { locale, digits });
    return { text: view.text, offset: view.offset };
};

group('zonedClock', () => {
    it('reads the Dubai wall clock and offset', () => {
        expect(zonedClock(uae(...MON, 9, 30), 'Asia/Dubai')).toEqual({ weekday: 1, minutes: 9 * 60 + 30, offset: 240 });
    });

    it('handles midnight as 00:00 of the new day', () => {
        expect(zonedClock(uae(...TUE, 0, 0), 'Asia/Dubai')).toEqual({ weekday: 2, minutes: 0, offset: 240 });
    });

    it('follows daylight saving time in other zones', () => {
        expect(zonedClock(Date.UTC(2026, 0, 15, 12), 'Europe/London').offset).toBe(0);
        expect(zonedClock(Date.UTC(2026, 6, 15, 12), 'Europe/London').offset).toBe(60);
        expect(zonedClock(Date.UTC(2026, 6, 15, 12), 'America/New_York').offset).toBe(-240);
    });
});

group('hoursStatus: opening hours Monday to Friday 09:00 to 18:00 UAE time', () => {
    it('is open on a weekday afternoon', () => {
        const status = hoursStatus(uae(...MON, 14, 15), OPTIONS);
        expect(status.open).toBe(true);
        expect(status.opensDay).toBeNull();
        expect(status.closes).toBe('18:00');
    });

    it('is closed at 08:59 and opens the same day', () => {
        expect(hoursStatus(uae(...MON, 8, 59), OPTIONS)).toMatchObject({ open: false, opensDay: 1 });
    });

    it('opens at 09:00 exactly', () => {
        expect(hoursStatus(uae(...MON, 9, 0), OPTIONS).open).toBe(true);
    });

    it('is still open at 17:59', () => {
        expect(hoursStatus(uae(...MON, 17, 59), OPTIONS).open).toBe(true);
    });

    it('closes at 18:00 exactly and opens the next morning', () => {
        expect(hoursStatus(uae(...MON, 18, 0), OPTIONS)).toMatchObject({ open: false, opensDay: 2 });
    });

    it('is closed just after midnight and opens at 09:00 that day', () => {
        expect(hoursStatus(uae(...TUE, 0, 1), OPTIONS)).toMatchObject({ open: false, opensDay: 2 });
    });

    it('opens on Monday after Friday evening', () => {
        expect(hoursStatus(uae(...FRI, 17, 59), OPTIONS).open).toBe(true);
        expect(hoursStatus(uae(...FRI, 18, 0), OPTIONS)).toMatchObject({ open: false, opensDay: 1 });
        expect(hoursStatus(uae(...FRI, 23, 30), OPTIONS)).toMatchObject({ open: false, opensDay: 1 });
    });

    it('is closed all weekend and opens on Monday', () => {
        for (const [day, hh] of [[SAT, 0], [SAT, 9], [SAT, 12], [SUN, 10], [SUN, 23]]) {
            expect(hoursStatus(uae(...day, hh, 0), OPTIONS)).toMatchObject({ open: false, opensDay: 1 });
        }
        expect(hoursStatus(uae(...SUN, 23, 59), OPTIONS)).toMatchObject({ open: false, opensDay: 1 });
    });

    it('uses the configured days and times', () => {
        const sixDays = { ...OPTIONS, days: [1, 2, 3, 4, 5, 6], open: '08:30', close: '13:00' };
        expect(hoursStatus(uae(...SAT, 8, 30), sixDays).open).toBe(true);
        expect(hoursStatus(uae(...SAT, 13, 0), sixDays)).toMatchObject({ open: false, opensDay: 1 });
        expect(hoursStatus(uae(...MON, 8, 29), sixDays)).toMatchObject({ open: false, opensDay: 1 });
    });

    it('depends on UAE time, not on the visitor clock', () => {
        // 23:00 on Sunday in New York is already 08:00 on Monday in Dubai: closed, opens Monday (later today
        // in Dubai, but the visitor's calendar day is still Sunday).
        const now = Date.UTC(2026, 10, 16, 4, 0);
        expect(hoursStatus(now, { ...OPTIONS, visitorTimeZone: 'America/New_York' })).toMatchObject({ open: false, opensDay: 1, opensIn: 0, sameDay: false });
        // One hour later it is 09:00 in Dubai: open, although it is still Sunday night for the visitor.
        expect(hoursStatus(now + 3600_000, { ...OPTIONS, visitorTimeZone: 'America/New_York' }).open).toBe(true);
    });
});

group('hoursStatus: time difference', () => {
    const at = uae(...MON, 10, 0);

    it('is 0 for a visitor on UAE time', () => {
        expect(hoursStatus(at, { ...OPTIONS, visitorOffset: 240 }).diff).toBe(0);
        expect(hoursStatus(at, { ...OPTIONS, visitorTimeZone: 'Asia/Dubai' }).diff).toBe(0);
    });

    it('is positive when UAE time is ahead (zones behind the UAE)', () => {
        expect(hoursStatus(at, { ...OPTIONS, visitorTimeZone: 'Europe/London' }).diff).toBe(240);
        expect(hoursStatus(at, { ...OPTIONS, visitorTimeZone: 'Europe/Moscow' }).diff).toBe(60);
        expect(hoursStatus(at, { ...OPTIONS, visitorTimeZone: 'America/New_York' }).diff).toBe(540);
    });

    it('is negative when UAE time is behind (zones ahead of the UAE)', () => {
        expect(hoursStatus(at, { ...OPTIONS, visitorTimeZone: 'Asia/Tokyo' }).diff).toBe(-300);
        expect(hoursStatus(at, { ...OPTIONS, visitorTimeZone: 'Asia/Kolkata' }).diff).toBe(-90);
        expect(hoursStatus(at, { ...OPTIONS, visitorTimeZone: 'Asia/Kathmandu' }).diff).toBe(-105);
    });

    it('follows the visitor daylight saving time', () => {
        expect(hoursStatus(uae(2026, 7, 6, 10), { ...OPTIONS, visitorTimeZone: 'Europe/London' }).diff).toBe(180);
    });

    it('accepts an explicit offset in minutes', () => {
        expect(hoursStatus(at, { ...OPTIONS, visitorOffset: -300 }).diff).toBe(540);
    });
});

group('formatTime: the approved time style of each language (brief §3.7)', () => {
    it('English: 12-hour like "9 am to 6 pm"', () => {
        expect(formatTime('09:00', EN.time)).toBe('at 9\u00A0am');
        expect(formatTime('18:00', EN.time)).toBe('at 6\u00A0pm');
        expect(formatTime('08:30', EN.time)).toBe('at 8:30\u00A0am');
        expect(formatTime('17:45', EN.time)).toBe('at 5:45\u00A0pm');
        expect(formatTime('12:00', EN.time)).toBe('at 12\u00A0pm');
        expect(formatTime('00:00', EN.time)).toBe('at 12\u00A0am');
        expect(formatTime('00:05', EN.time)).toBe('at 12:05\u00A0am');
    });

    it('Russian: 24-hour like the approved "с 9:00 до 18:00" (no leading zero)', () => {
        const ru = (t) => formatTime(t, RU.time, { locale: 'ru' });
        expect(ru('09:00')).toBe('в\u00A09:00');
        expect(ru('18:00')).toBe('в\u00A018:00');
        expect(ru('08:30')).toBe('в\u00A08:30');
        expect(ru('00:00')).toBe('в\u00A00:00');
        // Russian pages always write 0-9, whatever the Arabic setting says.
        expect(formatTime('18:00', RU.time, { locale: 'ru', digits: 'eastern' })).toBe('в\u00A018:00');
    });

    it('Arabic: like the approved homepage line "من 9 صباحًا إلى 6 مساءً"', () => {
        const ar = (t, digits = 'western') => formatTime(t, AR.time, { locale: 'ar', digits });
        expect(ar('09:00')).toBe('الساعة 9\u00A0صباحًا');
        expect(ar('18:00')).toBe('الساعة 6\u00A0مساءً');
        expect(ar('08:30')).toBe('الساعة 8:30\u00A0صباحًا');
        expect(ar('09:00', 'eastern')).toBe('الساعة ٩\u00A0صباحًا');
        expect(ar('18:00', 'eastern')).toBe('الساعة ٦\u00A0مساءً');
        expect(ar('08:05', 'eastern')).toBe('الساعة ٨:٠٥\u00A0صباحًا');
    });

    it('falls back to the 24-hour clock without patterns', () => {
        expect(formatTime('18:00')).toBe('18:00');
        expect(formatTime('9:5', {})).toBe('9:05');
    });
});

group('describe: English (brief §3.7, D40)', () => {
    it('open now, with the closing time', () => {
        expect(text(uae(...MON, 9, 0))).toEqual({ text: 'Open now, closes at 6\u00A0pm', offset: '' });
    });

    it('closed before opening: opens today', () => {
        expect(text(uae(...MON, 8, 59)).text).toBe('Closed now, opens today at 9\u00A0am');
    });

    it('closed after closing on a weekday: opens tomorrow', () => {
        expect(text(uae(...MON, 18, 0)).text).toBe('Closed now, opens tomorrow at 9\u00A0am');
    });

    it('Friday evening and the weekend: the weekday is named', () => {
        expect(text(uae(...FRI, 19, 0)).text).toBe('Closed now, opens Monday at 9\u00A0am');
        expect(text(uae(...SAT, 11, 0)).text).toBe('Closed now, opens Monday at 9\u00A0am');
    });

    it('hides the difference on UAE time', () => {
        expect(text(uae(...MON, 10, 0)).offset).toBe('');
    });

    it('UAE time ahead of the visitor, singular and plural', () => {
        expect(text(uae(...MON, 10, 0), { ...OPTIONS, visitorTimeZone: 'Europe/Moscow' }).offset).toBe('UAE time is 1\u00A0hour ahead of yours');
        expect(text(uae(...MON, 10, 0), { ...OPTIONS, visitorTimeZone: 'Europe/London' }).offset).toBe('UAE time is 4\u00A0hours ahead of yours');
        expect(text(uae(...MON, 10, 0), { ...OPTIONS, visitorTimeZone: 'Europe/Berlin' }).offset).toBe('UAE time is 3\u00A0hours ahead of yours');
    });

    it('UAE time behind the visitor: "UAE time is 1 hour behind yours" (D40), half and quarter hours', () => {
        expect(text(uae(...MON, 10, 0), { ...OPTIONS, visitorOffset: 300 }).offset).toBe('UAE time is 1\u00A0hour behind yours');
        expect(text(uae(...MON, 10, 0), { ...OPTIONS, visitorTimeZone: 'Asia/Tokyo' }).offset).toBe('UAE time is 5\u00A0hours behind yours');
        expect(text(uae(...MON, 10, 0), { ...OPTIONS, visitorTimeZone: 'Asia/Kolkata' }).offset).toBe('UAE time is 1.5\u00A0hours behind yours');
        expect(text(uae(...MON, 10, 0), { ...OPTIONS, visitorTimeZone: 'Asia/Kathmandu' }).offset).toBe('UAE time is 1.75\u00A0hours behind yours');
    });

    it('never uses a middle dot or mentions a response time', () => {
        const all = [uae(...MON, 9, 0), uae(...MON, 20, 0), uae(...SAT, 9, 0), uae(...TUE, 7, 0)].flatMap((t) =>
            [[EN, 'en'], [RU, 'ru'], [AR, 'ar']].map(([copy, locale]) => text(t, { ...OPTIONS, visitorOffset: 0 }, copy, locale)),
        );
        for (const { text: line, offset } of all) {
            expect(`${line} ${offset}`).not.toMatch(/·|•|respond|reply|within|minutes|24\/7|ответ|رد/i);
        }
    });
});

group('describe: Russian', () => {
    it('open, today, tomorrow and a weekday after "откроется"', () => {
        expect(text(uae(...TUE, 12, 0), OPTIONS, RU, 'ru').text).toBe('Сейчас открыто, закрывается в\u00A018:00');
        expect(text(uae(...TUE, 7, 30), OPTIONS, RU, 'ru').text).toBe('Сейчас закрыто, откроется сегодня в\u00A09:00');
        expect(text(uae(...THU, 19, 0), OPTIONS, RU, 'ru').text).toBe('Сейчас закрыто, откроется завтра в\u00A09:00');
        expect(text(uae(...FRI, 18, 30), OPTIONS, RU, 'ru').text).toBe('Сейчас закрыто, откроется в понедельник в\u00A09:00');
        // 22:30 on Tuesday in Dubai is already Wednesday in Tokyo: the weekday is named, not "завтра".
        expect(text(uae(...TUE, 22, 30), { ...OPTIONS, visitorTimeZone: 'Asia/Tokyo' }, RU, 'ru').text).toBe('Сейчас закрыто, откроется в среду в\u00A09:00');
    });

    it('plural forms (1 час, 4 часа, 9 часов, 11 часов) and the decimal comma (1,5 часа)', () => {
        const ru = (offset) => text(uae(...MON, 10, 0), { ...OPTIONS, visitorOffset: offset }, RU, 'ru').offset;
        expect(ru(180)).toBe('Время в ОАЭ опережает ваше на\u00A01\u00A0час');
        expect(ru(0)).toBe('Время в ОАЭ опережает ваше на\u00A04\u00A0часа');
        expect(ru(-300)).toBe('Время в ОАЭ опережает ваше на\u00A09\u00A0часов');
        expect(ru(-420)).toBe('Время в ОАЭ опережает ваше на\u00A011\u00A0часов');
        expect(ru(300)).toBe('Время в ОАЭ отстаёт от вашего на\u00A01\u00A0час');
        expect(ru(330)).toBe('Время в ОАЭ отстаёт от вашего на\u00A01,5\u00A0часа');
        expect(ru(360)).toBe('Время в ОАЭ отстаёт от вашего на\u00A02\u00A0часа');
    });
});

group('describe: Arabic, with the digits setting', () => {
    it('open, today, tomorrow and a weekday', () => {
        expect(text(uae(...MON, 12, 0), OPTIONS, AR, 'ar').text).toBe('مفتوح الآن، يُغلق الساعة 6\u00A0مساءً');
        expect(text(uae(...MON, 6, 0), OPTIONS, AR, 'ar').text).toBe('مغلق الآن، يفتح اليوم الساعة 9\u00A0صباحًا');
        expect(text(uae(...SUN, 9, 0), OPTIONS, AR, 'ar').text).toBe('مغلق الآن، يفتح غدًا الساعة 9\u00A0صباحًا');
        expect(text(uae(...SAT, 9, 0), OPTIONS, AR, 'ar').text).toBe('مغلق الآن، يفتح يوم الاثنين الساعة 9\u00A0صباحًا');
    });

    it('plural forms with Western digits (the default)', () => {
        const ar = (offset) => text(uae(...MON, 10, 0), { ...OPTIONS, visitorOffset: offset }, AR, 'ar').offset;
        expect(ar(180)).toBe('توقيت الإمارات متقدّم على توقيتك بساعة واحدة');
        expect(ar(120)).toBe('توقيت الإمارات متقدّم على توقيتك بساعتين');
        expect(ar(0)).toBe('توقيت الإمارات متقدّم على توقيتك بـ\u00A04\u00A0ساعات');
        expect(ar(-420)).toBe('توقيت الإمارات متقدّم على توقيتك بـ\u00A011\u00A0ساعة');
        expect(ar(330)).toBe('توقيت الإمارات متأخر عن توقيتك بـ\u00A01.5\u00A0ساعة');
        expect(ar(540)).toBe('توقيت الإمارات متأخر عن توقيتك بـ\u00A05\u00A0ساعات');
    });

    it('Eastern Arabic digits when the page says so, never mixed with 0-9', () => {
        const at = (t, offset) => text(t, { ...OPTIONS, visitorOffset: offset }, AR, 'ar', 'eastern');
        expect(at(uae(...MON, 12, 0), 0)).toEqual({ text: 'مفتوح الآن، يُغلق الساعة ٦\u00A0مساءً', offset: 'توقيت الإمارات متقدّم على توقيتك بـ\u00A0٤\u00A0ساعات' });
        expect(at(uae(...SAT, 9, 0), 330).offset).toBe('توقيت الإمارات متأخر عن توقيتك بـ\u00A0١٫٥\u00A0ساعة');
        expect(at(uae(...MON, 6, 0), 240).text).toBe('مغلق الآن، يفتح اليوم الساعة ٩\u00A0صباحًا');
        for (const t of [uae(...MON, 12, 0), uae(...FRI, 20, 0)]) {
            const { text: line, offset } = at(t, -180);
            expect(`${line} ${offset}`).not.toMatch(/[0-9]/);
        }
    });

    it('English and Russian ignore the Arabic setting', () => {
        expect(text(uae(...MON, 12, 0), { ...OPTIONS, visitorOffset: 0 }, EN, 'en', 'eastern')).toEqual({ text: 'Open now, closes at 6\u00A0pm', offset: 'UAE time is 4\u00A0hours ahead of yours' });
        expect(text(uae(...MON, 12, 0), OPTIONS, RU, 'ru', 'eastern').text).toBe('Сейчас открыто, закрывается в\u00A018:00');
    });
});

group('"opens today" and "opens tomorrow"', () => {
    const status = (now, options = OPTIONS) => hoursStatus(now, options);

    it('early morning on a weekday: opens today', () => {
        expect(status(uae(...TUE, 7, 30))).toMatchObject({ open: false, opensDay: 2, opensIn: 0, sameDay: true });
        expect(text(uae(...TUE, 7, 30)).text).toBe('Closed now, opens today at 9\u00A0am');
        expect(text(uae(...MON, 0, 1)).text).toBe('Closed now, opens today at 9\u00A0am');
        expect(text(uae(...FRI, 8, 59)).text).toBe('Closed now, opens today at 9\u00A0am');
    });

    it('Thursday evening: opens tomorrow (Friday is a working day)', () => {
        expect(status(uae(...THU, 19, 0))).toMatchObject({ open: false, opensDay: 5, opensIn: 1, sameDay: true });
        expect(text(uae(...THU, 18, 0)).text).toBe('Closed now, opens tomorrow at 9\u00A0am');
        expect(text(uae(...THU, 23, 59)).text).toBe('Closed now, opens tomorrow at 9\u00A0am');
    });

    it('Friday evening: the weekday is named (Monday is three days away)', () => {
        expect(status(uae(...FRI, 19, 0))).toMatchObject({ open: false, opensDay: 1, opensIn: 3 });
        for (const hh of [18, 21, 23]) expect(text(uae(...FRI, hh, 0)).text).toBe('Closed now, opens Monday at 9\u00A0am');
    });

    it('Saturday: the weekday is named (Monday is the day after tomorrow)', () => {
        expect(status(uae(...SAT, 12, 0))).toMatchObject({ open: false, opensDay: 1, opensIn: 2 });
        for (const [hh, mm] of [[0, 0], [9, 0], [23, 59]]) expect(text(uae(...SAT, hh, mm)).text).toBe('Closed now, opens Monday at 9\u00A0am');
    });

    it('Sunday: opens tomorrow', () => {
        expect(status(uae(...SUN, 12, 0))).toMatchObject({ open: false, opensDay: 1, opensIn: 1, sameDay: true });
        for (const [hh, mm] of [[0, 0], [12, 0], [23, 59]]) expect(text(uae(...SUN, hh, mm)).text).toBe('Closed now, opens tomorrow at 9\u00A0am');
    });

    it('open: no relative day at all', () => {
        expect(status(uae(...THU, 12, 0))).toMatchObject({ open: true, opensDay: null, opensIn: null });
        expect(text(uae(...THU, 12, 0)).text).toBe('Open now, closes at 6\u00A0pm');
    });

    group('a visitor time zone ahead of the UAE (Tokyo, UTC+9)', () => {
        const tokyo = { ...OPTIONS, visitorTimeZone: 'Asia/Tokyo' };

        it('same calendar day: "today" and "tomorrow" mean the same day for the visitor', () => {
            // 06:00 Tuesday in Dubai = 11:00 Tuesday in Tokyo.
            expect(status(uae(...TUE, 6, 0), tokyo)).toMatchObject({ opensIn: 0, sameDay: true });
            expect(text(uae(...TUE, 6, 0), tokyo)).toEqual({ text: 'Closed now, opens today at 9\u00A0am', offset: 'UAE time is 5\u00A0hours behind yours' });
            // 18:30 Thursday in Dubai = 23:30 Thursday in Tokyo.
            expect(text(uae(...THU, 18, 30), tokyo).text).toBe('Closed now, opens tomorrow at 9\u00A0am');
        });

        it('the visitor is already on the next day: the weekday is named', () => {
            // 21:00 Thursday in Dubai = 02:00 Friday in Tokyo: "tomorrow" would read as Saturday there.
            expect(status(uae(...THU, 21, 0), tokyo)).toMatchObject({ opensDay: 5, opensIn: 1, sameDay: false });
            expect(text(uae(...THU, 21, 0), tokyo).text).toBe('Closed now, opens Friday at 9\u00A0am');
            // 20:00 Sunday in Dubai = 01:00 Monday in Tokyo: Monday, not "tomorrow".
            expect(text(uae(...SUN, 20, 0), tokyo).text).toBe('Closed now, opens Monday at 9\u00A0am');
        });
    });

    group('a visitor time zone behind the UAE (New York, UTC−5; London, UTC+0)', () => {
        const newYork = { ...OPTIONS, visitorTimeZone: 'America/New_York' };
        const london = { ...OPTIONS, visitorTimeZone: 'Europe/London' };

        it('same calendar day: "tomorrow"', () => {
            // 19:00 Monday in Dubai = 10:00 Monday in New York = 15:00 Monday in London.
            expect(status(uae(...MON, 19, 0), newYork)).toMatchObject({ opensIn: 1, sameDay: true });
            expect(text(uae(...MON, 19, 0), newYork)).toEqual({ text: 'Closed now, opens tomorrow at 9\u00A0am', offset: 'UAE time is 9\u00A0hours ahead of yours' });
            expect(text(uae(...MON, 19, 0), london).text).toBe('Closed now, opens tomorrow at 9\u00A0am');
            // 07:00 Tuesday in Dubai = 03:00 Tuesday in London.
            expect(text(uae(...TUE, 7, 0), london).text).toBe('Closed now, opens today at 9\u00A0am');
        });

        it('the visitor is still on the previous day: the weekday is named', () => {
            // 07:00 Tuesday in Dubai = 22:00 Monday in New York: "today" would read as Monday there.
            expect(status(uae(...TUE, 7, 0), newYork)).toMatchObject({ opensDay: 2, opensIn: 0, sameDay: false });
            expect(text(uae(...TUE, 7, 0), newYork).text).toBe('Closed now, opens Tuesday at 9\u00A0am');
            // 08:00 Monday in Dubai = 23:00 Sunday in New York.
            expect(text(uae(...MON, 8, 0), newYork).text).toBe('Closed now, opens Monday at 9\u00A0am');
            // 02:00 Friday in Dubai = 17:00 Thursday in New York.
            expect(text(uae(...FRI, 2, 0), newYork).text).toBe('Closed now, opens Friday at 9\u00A0am');
        });

        it('in Russian and Arabic too', () => {
            expect(text(uae(...TUE, 7, 0), newYork, RU, 'ru').text).toBe('Сейчас закрыто, откроется во вторник в\u00A09:00');
            expect(text(uae(...TUE, 7, 0), london, RU, 'ru').text).toBe('Сейчас закрыто, откроется сегодня в\u00A09:00');
            expect(text(uae(...TUE, 7, 0), newYork, AR, 'ar').text).toBe('مغلق الآن، يفتح يوم الثلاثاء الساعة 9\u00A0صباحًا');
            expect(text(uae(...MON, 19, 0), london, AR, 'ar').text).toBe('مغلق الآن، يفتح غدًا الساعة 9\u00A0صباحًا');
        });
    });

    it('an explicit visitor offset decides the visitor day as well', () => {
        // 07:00 Tuesday in Dubai with the device at UTC−10 (Hawaii): 17:00 Monday there.
        expect(status(uae(...TUE, 7, 0), { ...OPTIONS, visitorOffset: -600 })).toMatchObject({ opensIn: 0, sameDay: false });
        expect(text(uae(...TUE, 7, 0), { ...OPTIONS, visitorOffset: -600 }).text).toBe('Closed now, opens Tuesday at 9\u00A0am');
        expect(text(uae(...TUE, 7, 0), { ...OPTIONS, visitorOffset: 180 }).text).toBe('Closed now, opens today at 9\u00A0am');
    });

    it('a copy block without the relative lines keeps naming the weekday', () => {
        const { closedToday, closedTomorrow, ...older } = EN;
        expect(text(uae(...TUE, 7, 30), OPTIONS, older).text).toBe('Closed now, opens Tuesday at 9\u00A0am');
        expect(text(uae(...SUN, 12, 0), OPTIONS, older).text).toBe('Closed now, opens Monday at 9\u00A0am');
    });
});

// ------------------------------------------------------------------------------------------ the DOM
/**
 * A minimal element: enough of the DOM for paint() and the module (Vitest runs in Node). `lang` stands for the
 * closest [lang] around it (none: the page's <html lang>), `parentElement` for the parent holding its siblings.
 */
class FakeElement {
    constructor(doc, dataset = {}, { lang = null, className = '', attributes = {} } = {}) {
        this.ownerDocument = doc;
        this.dataset = { ...dataset };
        this.attributes = { ...attributes };
        this.children = [];
        this.className = className;
        this.textContent = '';
        this.hidden = false;
        this.parentElement = null;
        this.lang = lang;
        const classes = new Set(className ? className.split(' ') : []);
        this.classList = {
            add: (c) => classes.add(c),
            toggle: (c, on) => (on ? classes.add(c) : classes.delete(c)),
            contains: (c) => classes.has(c),
            values: () => [...classes],
        };
    }

    setAttribute(name, value) {
        this.attributes[name] = String(value);
    }

    getAttribute(name) {
        return name === 'lang' ? this.lang : (this.attributes[name] ?? null);
    }

    closest(selector) {
        return selector.startsWith('[lang]') && this.lang ? this : null;
    }

    replaceChildren(...nodes) {
        this.children = nodes;
    }

    get text() {
        return this.children.map((child) => child.textContent).join('|');
    }
}

function fakeDocument({ digits, copy } = {}) {
    const doc = {
        hidden: false,
        documentElement: { lang: copy?.locale ?? 'en', dataset: digits ? { digits } : {} },
        slots: [],
        createElement: () => new FakeElement(doc),
        querySelectorAll: () => doc.slots,
        getElementById: (id) => (id === 'd2-hours-copy' && copy ? { textContent: JSON.stringify(copy) } : null),
        addEventListener: () => {},
    };
    return doc;
}

group('paint: the slot markup', () => {
    const doc = fakeDocument();
    const view = { open: true, text: 'Open now, closes at 6\u00A0pm', offset: 'UAE time is 4\u00A0hours ahead of yours' };

    it('writes the dot, the text and the time difference on its own line', () => {
        const el = new FakeElement(doc);
        paint(el, view, { markProposed: true });
        expect(el.children.map((child) => child.className)).toEqual(['hours-status__dot', 'hours-status__text', 'hours-status__offset']);
        expect(el.children[0].attributes['aria-hidden']).toBe('true');
        expect(el.text).toBe('|Open now, closes at 6\u00A0pm|UAE time is 4\u00A0hours ahead of yours');
        expect(el.classList.values()).toEqual(['hours-status', 'is-open']);
        expect(el.attributes['data-copy-status']).toBe('proposed');
    });

    it('the short slot leaves out the time difference', () => {
        const el = new FakeElement(doc, { hoursStatus: 'short' });
        paint(el, view);
        expect(el.children.map((child) => child.className)).toEqual(['hours-status__dot', 'hours-status__text']);
        expect(el.attributes['data-copy-status']).toBeUndefined();
    });

    it('marks a closed status and repaints only when the words change', () => {
        const el = new FakeElement(doc);
        paint(el, { open: false, text: 'Closed now, opens today at 9\u00A0am', offset: '' });
        expect(el.classList.contains('is-closed')).toBe(true);
        expect(el.classList.contains('is-open')).toBe(false);
        const first = el.children;
        paint(el, { open: false, text: 'Closed now, opens today at 9\u00A0am', offset: '' });
        expect(el.children).toBe(first);
    });
});

group('the module', () => {
    const saved = {};

    afterEach(() => {
        for (const name of ['document', 'window']) {
            if (saved[name] === undefined) delete globalThis[name];
            else globalThis[name] = saved[name];
        }
    });

    const run = (doc) => {
        saved.document = globalThis.document;
        saved.window = globalThis.window;
        globalThis.document = doc;
        globalThis.window = { setTimeout: () => 0, clearTimeout: () => {}, addEventListener: () => {} };
        hours();
    };
    const block = (locale, enabled = true) => ({ enabled, proposed: false, locale, hours: { timeZone: 'Asia/Dubai', days: [1, 2, 3, 4, 5], open: '09:00', close: '18:00' }, text: COPY[locale] });

    it('fills every slot in the page language with the page digits', () => {
        const doc = fakeDocument({ digits: 'eastern', copy: block('ar') });
        doc.slots = [new FakeElement(doc), new FakeElement(doc, { hoursStatus: 'short' })];
        run(doc);
        for (const el of doc.slots) {
            expect(el.classList.contains('hours-status')).toBe(true);
            expect(el.children[1].textContent).toMatch(/^(مفتوح|مغلق) الآن، /);
            expect(el.children[1].textContent).not.toMatch(/[0-9]/);
        }
    });

    it('does nothing while the live status is switched off', () => {
        const doc = fakeDocument({ copy: block('en', false) });
        doc.slots = [new FakeElement(doc)];
        run(doc);
        expect(doc.slots[0].children).toEqual([]);
    });

    it('leaves a slot in another language empty, and hides its reserve copy (no mixed-language card)', () => {
        // The styleguide's Arabic contact card on an English page: the copy block is English, so the Arabic card's
        // slot is not written ("Open now, closes at 6 pm" under "من 9 صباحًا إلى 6 مساءً"), and the invisible
        // reserve line beside it collapses; the English card on the same page is written as usual.
        const doc = fakeDocument({ copy: block('en') });
        const own = new FakeElement(doc);
        const foreign = new FakeElement(doc, {}, { lang: 'ar' });
        const reserve = new FakeElement(doc, {}, { lang: 'ar', className: 'contact-card__status-sizer hours-status', attributes: { 'aria-hidden': 'true' } });
        const slot = { children: [foreign, reserve] };
        foreign.parentElement = slot;
        reserve.parentElement = slot;
        doc.slots = [own, foreign];
        run(doc);
        expect(own.children[1].textContent).toMatch(/^(Open|Closed) now, /);
        expect(foreign.children).toEqual([]);
        expect(foreign.classList.contains('hours-status')).toBe(false);
        expect(reserve.hidden).toBe(true);
        expect(own.hidden).toBe(false);
    });
});

group('inLanguage', () => {
    const doc = fakeDocument();

    it('a slot under the page language only (or a region tag of it) is written', () => {
        expect(inLanguage(new FakeElement(doc), 'en')).toBe(true);
        expect(inLanguage(new FakeElement(doc, {}, { lang: 'en' }), 'en')).toBe(true);
        expect(inLanguage(new FakeElement(doc, {}, { lang: 'en-GB' }), 'en')).toBe(true);
        expect(inLanguage(new FakeElement(doc, {}, { lang: 'AR' }), 'ar')).toBe(true);
    });

    it('a slot inside a block in another language is not', () => {
        expect(inLanguage(new FakeElement(doc, {}, { lang: 'ar' }), 'en')).toBe(false);
        expect(inLanguage(new FakeElement(doc, {}, { lang: 'en' }), 'ru')).toBe(false);
    });
});
