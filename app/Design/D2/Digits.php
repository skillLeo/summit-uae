<?php

namespace App\Design\D2;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Arabic digits (Design 2.1 brief §3.7, fix D11; owner decision: Western 0-9 by default).
 *
 * The approved Arabic copy mixes Western digits (the homepage) and Eastern Arabic digits (Contact, About, the
 * Legal Notice, Terms and Privacy), and scripts write numbers of their own. One setting,
 * the CMS setting languages.arabic_digits ("western", the default, or "eastern"; SUMMIT_ARABIC_DIGITS, read as
 * config('design.d2.arabic_digits'), decides for one environment when it is set), is applied at
 * render time to every Design 2 HTML response, so Arabic text never mixes 0-9 and ٠-٩ and shows the same digits
 * wherever it appears: on an Arabic page the whole page is converted; on an English or Russian page only its
 * Arabic blocks are (an element with an Arabic lang and everything in it, such as the styleguide's specimens in the
 * other direction), by the same rules, and the rest of the page is left as it is:
 *
 *  - western: Eastern Arabic digits (٠-٩, and the Persian ۰-۹) become 0-9 in the visible text and in the
 *    attributes that are shown or read aloud (SPOKEN below); an Arabic decimal or thousands separator between
 *    two digits becomes "." or ",".
 *  - eastern: Western digits become ٠-٩ in the same places (with "٫" and "٬" for a "." or "," between two digits, as
 *    Intl.NumberFormat('ar-u-nu-arab') writes them), except inside left-to-right runs: any element with
 *    dir="ltr" (<bdi dir="ltr">: App\Design\D2\Bidi::isolate(), the phone, e-mail and domain values, the sample
 *    documents' fields) or a lang other than Arabic, tel: and mailto: links, and inside a text: phone numbers
 *    (+971 …, 00 …), runs of seven or more digits (account, reference and phone numbers), e-mail addresses, URLs,
 *    Latin and Cyrillic runs ("AED 1,000", "S01", "SLC-261110-ABC123", "Art. 53"), digits fused to Latin letters
 *    ("10MB") and character references ("&#039;"). A <bdi> without dir is not such a run: the views use it to
 *    isolate a number inside an Arabic sentence ("<bdi>4</bdi> مستندات", "<bdi>0</bdi> من <bdi>4</bdi>"), so its
 *    digits follow the setting like the rest of the sentence (the text rules above still keep a phone number,
 *    e-mail or code in it).
 *
 * Never touched in either mode: the markup itself, every other attribute (href, src, id, class, data-*, value,
 * content …), the content of <script> (JSON included), <style>, <textarea> (the visitor's own text) and the other
 * raw-text elements, comments, and verbatim subtrees: <code>, <kbd>, <samp>, <pre>, <var>, translate="no" and
 * dir="auto" (text the visitor typed, specimens shown as they are).
 *
 * The <html> start tag of an Arabic page gets data-digits="western|eastern": scripts read it
 * (resources/js/d2/lib/digits.js), so the numbers they write follow the same setting (modules/hours.js). An English
 * or Russian page keeps its <html> tag as it is: its scripts write that language's 0-9 and never write into an
 * Arabic block (modules/hours.js leaves a slot inside a block in another language empty).
 *
 * Registered as global middleware (bootstrap/app.php, the outermost layer, so error pages and maintenance mode's
 * 503 pass through it too). A response is changed only when its <html> start tag carries data-design="2": Design 1
 * pages, the admin, JSON, files and redirects pass through byte for byte, and so does an English or Russian page
 * whose Arabic blocks (if any) have nothing to convert. Cost (measured, docs in
 * tests/Feature/Design/D2/DigitsTest.php): a western page without an Eastern digit, or an English or Russian page
 * without an Arabic block, is not tokenised at all; a full pass is a single forward scan of the page, which on an
 * English or Russian page ends after its last Arabic block.
 */
final class Digits
{
    public const WESTERN = 'western';

    public const EASTERN = 'eastern';

    /** Attributes whose value is shown or read aloud: converted like text. */
    private const SPOKEN = [
        'aria-label' => true, 'aria-description' => true, 'aria-placeholder' => true, 'aria-roledescription' => true,
        'aria-valuetext' => true, 'title' => true, 'alt' => true, 'placeholder' => true, 'label' => true,
    ];

    /** Elements whose content is raw text: never converted, never tokenised. */
    private const RAW = [
        'script' => true, 'style' => true, 'textarea' => true, 'xmp' => true, 'iframe' => true, 'noembed' => true,
        'noframes' => true,
    ];

    /** Elements whose content is verbatim (code, keys, samples): never converted. */
    private const VERBATIM = ['code' => true, 'kbd' => true, 'samp' => true, 'pre' => true, 'var' => true];

    /** Elements without content (they never open a skipped subtree). */
    private const VOID = [
        'area' => true, 'base' => true, 'br' => true, 'col' => true, 'embed' => true, 'hr' => true, 'img' => true,
        'input' => true, 'keygen' => true, 'link' => true, 'meta' => true, 'param' => true, 'source' => true,
        'track' => true, 'wbr' => true,
    ];

    private const TO_WESTERN = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    private const TO_EASTERN = [
        '0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩',
        '۰' => '٠', '۱' => '١', '۲' => '٢', '۳' => '٣', '۴' => '٤', '۵' => '٥', '۶' => '٦', '۷' => '٧', '۸' => '٨', '۹' => '٩',
        // inside a number (EASTERN_SPLIT only hands over digits with "." or "," between them): the Arabic decimal
        // and thousands separators, as Intl.NumberFormat('ar-u-nu-arab') writes them
        '.' => '٫', ',' => '٬',
    ];

    /** Eastern Arabic or Persian digits, or an Arabic decimal / thousands separator, as UTF-8 bytes (no /u). */
    private const EASTERN_BYTES = '/\xD9[\xA0-\xA9\xAB\xAC]|\xDB[\xB0-\xB9]/';

    /**
     * Where an element may get an Arabic lang (lang="ar", lang='ar-AE', lang=ar …; not hreflang or xml:lang): the
     * cheap test for the Arabic blocks of a page in another language. It may also match text, a script or a comment
     * (which only costs a scan), never miss an attribute that self::arabic() accepts.
     */
    private const ARABIC_LANG = '~(?<=[\s/"\'])lang\s*=\s*["\']?[\s\x00]*ar(?![a-z0-9_])~i';

    /**
     * Eastern mode: what keeps its Western digits inside a text (group 1), or a run of digits to convert.
     * Mirrored by EASTERN_SPLIT in resources/js/d2/lib/digits.js (the shared cases are in both test files), which
     * must not use lookbehind (Safari before 16.4): none is needed, because the last alternative takes any run of
     * digits, so a match never starts in the middle of a number.
     */
    private const EASTERN_SPLIT = '~('
        .'&(?:#[0-9]+|#[xX][0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]*);'                 // character references
        .'|(?:https?://|www\.)[^\t\n\f\r <>"\']+'                                     // URLs
        .'|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}'                          // e-mail addresses
        .'|(?:\+|00)[0-9][0-9 ().-]{5,}[0-9]'                                       // international phone numbers
        .'|[0-9]{7,}'                                                                 // account, reference, phone numbers
        .'|[0-9]+(?:[.,][0-9]+)*(?=[A-Za-z])[A-Za-z0-9.,:/+#%&_-]*'                 // digits fused to Latin: 10MB, 2nd
        .'|[A-Za-z\x{0400}-\x{04FF}][A-Za-z\x{0400}-\x{04FF}0-9.,:/+#%&_-]*'         // a Latin or Cyrillic run: words,
        .'(?:[ \t]+[A-Za-z\x{0400}-\x{04FF}0-9][A-Za-z\x{0400}-\x{04FF}0-9.,:/+#%&_-]*)*' // codes and the numbers in it
        .')|[0-9\x{06F0}-\x{06F9}]+(?:[.,][0-9\x{06F0}-\x{06F9}]+)*~u';                            // a number to convert

    public static function mode(): string
    {
        return strtolower(trim((string) (config('design.d2.arabic_digits') ?? content()->settings('languages')['arabic_digits'] ?? self::WESTERN))) === self::EASTERN
            ? self::EASTERN
            : self::WESTERN;
    }

    /**
     * Plain text as a Design 2 page in that locale shows it under the setting (Arabic only; other locales
     * unchanged). For strings built outside the HTML response (tests, payloads compared with the page).
     */
    public static function localise(string $text, ?string $locale = null, ?string $mode = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? self::text($text, $mode ?? self::mode()) : $text;
    }

    /** The digits of one text (or raw attribute value) in the mode; no markup is parsed. */
    public static function text(string $text, string $mode): string
    {
        return $mode === self::EASTERN ? self::toEastern($text) : self::toWestern($text);
    }

    // ------------------------------------------------------------------------------------------ middleware

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof StreamedResponse
            || $response instanceof BinaryFileResponse
            || ! str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html')) {
            return $response;
        }

        $html = $response->getContent();
        if (is_string($html) && $html !== '') {
            $converted = self::page($html);
            if ($converted !== $html) {
                $response->setContent($converted);
                $response->headers->remove('Content-Length');
            }
        }

        return $response;
    }

    /**
     * A whole HTML document: returned unchanged unless its <html> start tag (in the first 8 KB) carries
     * data-design="2". With an Arabic lang, the tag gets data-digits="{mode}" and the document after it is
     * converted; in another language, only the Arabic blocks after it are (the tag stays as it is).
     */
    public static function page(string $html, ?string $mode = null): string
    {
        if (preg_match('~<html\b(?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+>~i', substr($html, 0, 8192), $m, PREG_OFFSET_CAPTURE) !== 1) {
            return $html;
        }
        [$tag, $at] = $m[0];
        $attributes = self::attributes(substr($tag, 5, -1));
        if (($attributes['data-design'] ?? null) !== '2') {
            return $html;
        }

        $mode ??= self::mode();
        $end = $at + strlen($tag);
        if (! self::arabic($attributes['lang'] ?? '')) {
            // Nothing to do without an Arabic block or (western) an Eastern digit: tested before the substr(), so
            // the common case costs no copy of the page.
            if (($mode !== self::EASTERN && preg_match(self::EASTERN_BYTES, $html, $_, 0, $end) !== 1)
                || preg_match(self::ARABIC_LANG, $html, $_, 0, $end) !== 1) {
                return $html;
            }
            $rest = substr($html, $end);
            $converted = self::html($rest, $mode, false);

            return $converted === $rest ? $html : substr($html, 0, $end).$converted;
        }

        $root = array_key_exists('data-digits', $attributes) ? $tag : rtrim(substr($tag, 0, -1)).' data-digits="'.$mode.'">';

        return substr($html, 0, $at).$root.self::html(substr($html, $end), $mode);
    }

    /**
     * Any HTML (a document after its <html> tag, or a fragment): the text and the shown or spoken attributes
     * converted, every other byte as it was. $arabic is the language the HTML starts in: true for an Arabic page
     * (everything is converted), false for a page in another language, where only its Arabic blocks are (an element
     * with an Arabic lang outside a verbatim subtree, and everything in it, under the rules of an Arabic page).
     */
    public static function html(string $html, string $mode, bool $arabic = true): string
    {
        $eastern = $mode === self::EASTERN;
        $length = strlen($html);
        // The walk ends early where nothing after can change: in western mode after the last Eastern digit, and on a
        // page in another language outside an Arabic block after the last place that may give an element an
        // Arabic lang ($last).
        if ($eastern) {
            if (strpbrk($html, '0123456789') === false && preg_match('/\xDB[\xB0-\xB9]/', $html) !== 1) {
                return $html;
            }
            $limit = $length;
        } else {
            if (preg_match_all(self::EASTERN_BYTES, $html, $found, PREG_OFFSET_CAPTURE) === 0) {
                return $html;
            }
            $limit = (int) end($found[0])[1];
        }

        $scoped = ! $arabic;
        $last = 0;
        if ($scoped) {
            if (preg_match_all(self::ARABIC_LANG, $html, $found, PREG_OFFSET_CAPTURE) === 0) {
                return $html;
            }
            $last = (int) end($found[0])[1];
        }

        $out = '';
        $copied = 0;
        $pos = 0;
        $skip = null; // [element name, depth] of a verbatim (or, in eastern mode, left-to-right) subtree
        // A page in another language: [element name, depth] of the Arabic block being converted, null outside one
        // (where nothing is converted). On an Arabic page the whole document is converted and this stays null.
        $block = null;
        // Kept in step with $skip and $block, so the loop tests one value: outside an Arabic block of a page in
        // another language; text converted here; the walk ends at a tag that starts after $end.
        $outside = $scoped;
        $on = ! $scoped;
        $end = $scoped ? min($limit, $last) : $limit;

        $put = function (int $start, int $bytes) use ($html, $mode, &$out, &$copied): void {
            $chunk = substr($html, $start, $bytes);
            $converted = self::text($chunk, $mode);
            if ($converted !== $chunk) {
                $out .= substr($html, $copied, $start - $copied).$converted;
                $copied = $start + $bytes;
            }
        };

        while ($pos < $length) {
            $lt = strpos($html, '<', $pos);
            $stop = $lt === false ? $length : $lt;
            if ($on && $stop > $pos) {
                $put($pos, $stop - $pos);
            }
            if ($lt === false || $lt > $end) {
                break;
            }
            $pos = $lt;
            $next = $html[$pos + 1] ?? '';

            if ($next === '!' || $next === '?') {
                // comment, CDATA section, doctype or processing instruction: kept as it is
                [$close, $size] = match (true) {
                    substr_compare($html, '<!--', $pos, 4) === 0 => [strpos($html, '-->', $pos + 4), 3],
                    substr_compare($html, '<![CDATA[', $pos, 9) === 0 => [strpos($html, ']]>', $pos + 9), 3],
                    default => [strpos($html, '>', $pos + 2), 1],
                };
                $pos = $close === false ? $length : $close + $size;

                continue;
            }

            if ($next === '/') {
                if (preg_match('~\G</([a-zA-Z][^\t\n\f\r />]*+)[^>]*+>~', $html, $t, 0, $pos) === 1) {
                    if ($skip !== null) {
                        if (strtolower($t[1]) === $skip[0] && --$skip[1] === 0) {
                            $skip = null;
                            $on = ! $outside;
                        }
                    } elseif ($block !== null && strtolower($t[1]) === $block[0] && --$block[1] === 0) {
                        $block = null; // the Arabic block ends
                        $outside = true;
                        $on = false;
                        $end = min($limit, $last);
                    }
                    $pos += strlen($t[0]);
                } else {
                    $pos++;
                }

                continue;
            }

            if (! ctype_alpha($next)
                || preg_match('~\G<([a-zA-Z][^\t\n\f\r />]*+)((?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+)>~', $html, $t, 0, $pos) !== 1) {
                $pos++; // a "<" that starts no tag is text

                continue;
            }

            $name = strtolower($t[1]);
            $body = $t[2];
            $tagEnd = $pos + strlen($t[0]);
            $opens = ! isset(self::VOID[$name]) && ! str_ends_with(rtrim($body), '/');

            if ($skip !== null) {
                if ($opens && $name === $skip[0]) {
                    $skip[1]++;
                }
            } else {
                // Most tags carry nothing that matters here: parse the attributes only when one might.
                $attributes = $body !== '' && self::relevant($body, $name, $eastern, $scoped) ? self::attributes($body, true) : [];
                $dir = strtolower(trim($attributes['dir'][0] ?? ''));
                $lang = trim($attributes['lang'][0] ?? '');
                $verbatim = isset(self::VERBATIM[$name]) || $dir === 'auto' || strtolower(trim($attributes['translate'][0] ?? '')) === 'no';
                if ($outside && ! $verbatim && ($lang === '' || ! self::arabic($lang))) {
                    // outside the Arabic blocks of a page in another language: nothing to convert
                } elseif ($verbatim || ($eastern && (
                    // a left-to-right run (a <bdi> without dir only isolates a number inside a sentence: it follows
                    // the setting)
                    $dir === 'ltr'
                    || ($lang !== '' && ! self::arabic($lang))
                    || ($name === 'a' && preg_match('~^\s*(?:tel|mailto):~i', $attributes['href'][0] ?? '') === 1)
                ))) {
                    if ($opens) {
                        $skip = [$name, 1];
                        $on = false;
                    }
                } else {
                    if ($scoped && $opens) {
                        if ($block === null) {
                            $block = [$name, 1]; // an Arabic block starts
                            $outside = false;
                            $on = true;
                            $end = $limit;
                        } elseif ($name === $block[0]) {
                            $block[1]++;
                        }
                    }
                    $offset = $pos + 1 + strlen($t[1]);
                    foreach ($attributes as $attribute => [$text, $at]) {
                        if (isset(self::SPOKEN[$attribute]) && $at >= 0 && $text !== '') {
                            $put($offset + $at, strlen($text));
                        }
                    }
                }
            }

            if ($opens && isset(self::RAW[$name])) {
                // raw text: continue at its end tag (or the end of the document)
                $close = self::endTag($html, $name, $tagEnd);
                $pos = $close === false ? $length : $close;

                continue;
            }

            $pos = $tagEnd;
        }

        return $copied === 0 ? $html : $out.substr($html, $copied);
    }

    // ------------------------------------------------------------------------------------------ internals

    private static function toWestern(string $text): string
    {
        if (preg_match(self::EASTERN_BYTES, $text) !== 1) {
            return $text;
        }
        $text = strtr($text, self::TO_WESTERN);

        return (string) preg_replace(['/([0-9])\x{066B}(?=[0-9])/u', '/([0-9])\x{066C}(?=[0-9])/u'], ['$1.', '$1,'], $text);
    }

    private static function toEastern(string $text): string
    {
        if (strpbrk($text, '0123456789') === false && preg_match('/\xDB[\xB0-\xB9]/', $text) !== 1) {
            return $text;
        }

        return (string) preg_replace_callback(
            self::EASTERN_SPLIT,
            fn (array $m): string => ($m[1] ?? '') !== '' ? $m[1] : strtr($m[0], self::TO_EASTERN),
            $text,
        );
    }

    /**
     * Whether a start tag's attributes can matter (a cheap test before parsing them): a dir, translate, lang (eastern
     * mode, or a page in another language: $scoped) or (on a link, eastern mode) href attribute, or a digit to convert
     * in a shown or spoken attribute.
     */
    private static function relevant(string $body, string $name, bool $eastern, bool $scoped = false): bool
    {
        if (stripos($body, 'dir') !== false || stripos($body, 'translate') !== false
            || (($eastern || $scoped) && stripos($body, 'lang') !== false)) {
            return true;
        }
        if (! $eastern) {
            return preg_match(self::EASTERN_BYTES, $body) === 1;
        }
        if ($name === 'a' && stripos($body, 'href') !== false) {
            return true;
        }

        return (strpbrk($body, '0123456789') !== false || preg_match('/\xDB[\xB0-\xB9]/', $body) === 1)
            && preg_match('/(?:label|title|alt|placeholder|description|valuetext)\s*=/i', $body) === 1;
    }

    private static function arabic(string $lang): bool
    {
        return preg_match('/^ar(?:-|$)/i', trim($lang)) === 1;
    }

    /**
     * Attributes of a start tag's body (after the name): name => value, or with $offsets name => [value, offset of
     * the value in $body] (-1 for an attribute without a value). The value is raw (character references kept).
     *
     * @return array<string, string>|array<string, array{0: string, 1: int}>
     */
    private static function attributes(string $body, bool $offsets = false): array
    {
        preg_match_all(
            '~([^\t\n\f\r />"\'=]+)(?:[\t\n\f\r ]*=[\t\n\f\r ]*(?:"([^"]*)"|\'([^\']*)\'|([^\t\n\f\r >"\']+)))?~',
            $body,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL,
        );
        $out = [];
        foreach ($matches as $match) {
            $name = strtolower($match[1][0]);
            if (array_key_exists($name, $out)) {
                continue; // the first one wins, as in a browser
            }
            $value = [null, -1];
            foreach ([2, 3, 4] as $group) {
                if (isset($match[$group]) && $match[$group][0] !== null) {
                    $value = $match[$group];
                    break;
                }
            }
            $out[$name] = $offsets ? [(string) $value[0], (int) $value[1]] : (string) $value[0];
        }

        return $out;
    }

    /** Offset of the end tag "</name" that closes a raw-text element, or false. */
    private static function endTag(string $html, string $name, int $from): int|false
    {
        $needle = '</'.$name;
        while (($at = stripos($html, $needle, $from)) !== false) {
            $after = $html[$at + strlen($needle)] ?? '>';
            if ($after === '>' || $after === '/' || ctype_space($after)) {
                return $at;
            }
            $from = $at + 1;
        }

        return false;
    }
}
