<?php

namespace App\Http\Requests;

use Closure;
use finfo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Field validation for POST /{locale}/enquiries (field names from 07_Contact_Us). Contract,
 * error keys and states: docs/enquiries.md. Every message is approved copy read through ui().
 *
 * Validation runs on a normalised copy of the input (validationData()). The request itself is
 * left untouched, so that without JavaScript the form re-renders exactly what the visitor typed.
 */
final class StoreEnquiryRequest extends FormRequest
{
    public const LANGUAGES = ['ar', 'en', 'ru'];

    public const EXTRA_MATTERS = ['other', 'unsure'];

    /** Inputs never flashed back to the form without JavaScript (single-use or regenerated per render). */
    public const NOT_FLASHED = ['_token', 'idempotency_key', 'cf-turnstile-response', 'attachment'];

    /** Every key a 422 "invalid" response can carry, in form order ('attachment' only while attachments are enabled). */
    public const ERROR_KEYS = ['name', 'phone', 'email', 'contact', 'summary', 'deadline_date', 'deadline_details', 'attachment', 'enquiry_consent'];

    private const ARABIC_DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /** @var array<string, mixed>|null */
    private ?array $normalised = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', $this->utf8(...), 'max:120'],
            'phone' => ['bail', 'nullable', 'string', 'max:64', $this->phone(...)],
            'email' => ['bail', 'nullable', 'string', 'max:254', 'email:rfc'],
            // Always valid here: unknown values were already turned into "not chosen".
            'preferred_language' => ['nullable', Rule::in(self::LANGUAGES)],
            'matter_type' => ['nullable', Rule::in(self::matterTypes())],
            'summary' => ['bail', 'required', 'string', $this->utf8(...), 'max:5000'],
            'deadline_date' => ['bail', 'nullable', 'string', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'],
            'deadline_details' => ['bail', 'nullable', 'string', $this->utf8(...), 'max:500'],
            'enquiry_consent' => ['accepted'],
        ];
    }

    /**
     * One approved message per field, whichever rule failed ('bail' stops at the first failure).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name' => ui('form.name.error'),
            'phone' => ui('form.phone.error'),
            'email' => ui('form.email.error'),
            'summary' => ui('form.summary.error'),
            'deadline_date' => ui('form.deadline_date.error'),
            // No field-specific message is approved; only reachable past the browser's maxlength="500".
            'deadline_details' => ui('form.state.summary'),
            'enquiry_consent' => ui('form.consent.error'),
        ];
    }

    /** @return list<Closure> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $data = $this->validationData();
                if (($data['phone'] ?? null) === null && ($data['email'] ?? null) === null) {
                    $validator->errors()->add('contact', ui('form.contact_group.error'));
                }
                if (self::attachmentsEnabled() && ($error = $this->attachmentError()) !== null) {
                    $validator->errors()->add('attachment', ui('form.attachment.'.$error));
                }
            },
        ];
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        return $this->normalised ??= $this->normalise($this->all());
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            $errors = $validator->errors()->toArray();
            $ordered = array_filter(array_replace(array_fill_keys(self::ERROR_KEYS, null), $errors));

            throw new HttpResponseException(response()->json([
                'status' => 'invalid',
                'message' => ui('form.state.summary'),
                'errors' => $ordered,
            ], 422));
        }

        // Without JavaScript: back to the form with the standard error bag and old input flashed.
        throw new HttpResponseException(
            redirect()->to(self::formUrl(), 303)
                ->withInput(Arr::except($this->input(), self::NOT_FLASHED))
                ->withErrors($validator)
                ->with('enquiry_status', 'invalid'),
        );
    }

    // ------------------------------------------------------------------ accessors

    /** The client's UUID for this enquiry, lower-cased; null when missing or malformed. */
    public function idempotencyKey(): ?string
    {
        $key = $this->input('idempotency_key');

        return is_string($key) && Str::isUuid($key) ? strtolower($key) : null;
    }

    public function turnstileToken(): ?string
    {
        $token = $this->input('cf-turnstile-response');

        return is_string($token) && $token !== '' ? $token : null;
    }

    /** The hidden "website" field is empty for people; anything in it marks the enquiry as spam. */
    public function honeypotFilled(): bool
    {
        $value = $this->input('website');

        return is_array($value) ? $value !== [] : trim((string) $value) !== '';
    }

    /**
     * Validated, normalised values for storage (call after validation has passed).
     *
     * @return array{name: string, phone: ?string, email: ?string, preferred_language: ?string, matter_type: ?string, summary: string, deadline_date: ?string, deadline_details: ?string}
     */
    public function enquiryAttributes(): array
    {
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'phone' => isset($data['phone']) ? self::canonicalPhone($data['phone']) : null,
            'email' => $data['email'] ?? null,
            'preferred_language' => $data['preferred_language'] ?? null,
            'matter_type' => $data['matter_type'] ?? null,
            'summary' => $data['summary'],
            'deadline_date' => $data['deadline_date'] ?? null,
            'deadline_details' => $data['deadline_details'] ?? null,
        ];
    }

    /**
     * The validated document, or null. Always null while attachments are disabled: an uploaded
     * file is then ignored and never read or stored.
     */
    public function attachment(): ?UploadedFile
    {
        if (! self::attachmentsEnabled()) {
            return null;
        }
        $files = $this->attachmentFiles();

        return count($files) === 1 && $files[0] instanceof UploadedFile ? $files[0] : null;
    }

    // ------------------------------------------------------------------ helpers

    public static function attachmentsEnabled(): bool
    {
        return (bool) config('summit.features.attachments_enabled');
    }

    /** @return list<string> S01–S11, other, unsure */
    public static function matterTypes(): array
    {
        return [...array_keys((array) config('summit.services')), ...self::EXTRA_MATTERS];
    }

    /**
     * Where the consultation form lives (P07 once released, otherwise the homepage), with the
     * trailing slash the page routes require: url() would drop it and cost a 301 hop that
     * consumes the flashed errors.
     */
    public static function formUrl(?string $locale = null): string
    {
        $page = config('summit.contact_page_released') ? 'P07' : 'P01';

        return rtrim(url('/'), '/').'/'.($locale ?? app()->getLocale()).'/'.config("summit.pages.{$page}.path").'#consultation-form';
    }

    /**
     * International number: starts with "+" or "00", then 7–15 digits; spaces, hyphens, dots and
     * parentheses allowed. Returns "+" and the digits, or null when invalid.
     */
    public static function canonicalPhone(string $value): ?string
    {
        if (preg_match('/^[0-9+().\h\p{Pd}]+$/u', $value) !== 1) {
            return null;
        }
        $compact = (string) preg_replace('/[().\h\p{Pd}]/u', '', $value);

        return preg_match('/^(?:\+|00)([0-9]{7,15})$/', $compact, $m) === 1 ? '+'.$m[1] : null;
    }

    /** Only a country-code prefix such as "+971" (the form pre-fills "+971 "): counts as no phone. */
    public static function isPrefixOnly(string $value): bool
    {
        $compact = preg_replace('/[().\h\p{Pd}]/u', '', $value);

        return is_string($compact) && preg_match('/^(?:\+|00)[0-9]{0,3}$/', $compact) === 1;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalise(array $input): array
    {
        // Browsers submit textarea line breaks as CRLF; count them as one character, as maxlength does.
        // Control characters other than tab and line feed are dropped.
        foreach (['name', 'summary', 'deadline_details'] as $key) {
            if (is_string($input[$key] ?? null)) {
                $text = str_replace(["\r\n", "\r"], "\n", $input[$key]);
                $text = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text);
                $input[$key] = $text === '' ? null : $text;
            }
        }

        // Invisible direction marks (copied from RTL text) and Arabic-Indic digits in machine values.
        foreach (['phone', 'email', 'deadline_date'] as $key) {
            if (is_string($input[$key] ?? null)) {
                $clean = preg_replace('/\p{Cf}/u', '', $input[$key]);
                if ($clean === null) {
                    continue; // invalid UTF-8: left as is, so the field fails validation
                }
                $clean = trim($key === 'email' ? $clean : strtr($clean, self::ARABIC_DIGITS));
                $input[$key] = $clean === '' ? null : $clean;
            }
        }
        if (is_string($input['phone'] ?? null) && self::isPrefixOnly($input['phone'])) {
            $input['phone'] = null;
        }

        // Optional selects: an unknown value is silently treated as "not chosen".
        foreach (['preferred_language' => self::LANGUAGES, 'matter_type' => self::matterTypes()] as $key => $allowed) {
            $input[$key] = in_array($input[$key] ?? null, $allowed, true) ? $input[$key] : null;
        }

        return $input;
    }

    private function phone(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::canonicalPhone($value) === null) {
            $fail(ui('form.phone.error'));
        }
    }

    private function utf8(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8')) {
            $fail($attribute);
        }
    }

    /** @return list<mixed> */
    private function attachmentFiles(): array
    {
        $files = $this->allFiles()['attachment'] ?? null;

        return $files === null ? [] : array_values(Arr::flatten(Arr::wrap($files)));
    }

    /**
     * Server-side checks for the one optional document: count, upload integrity, size, then the
     * type sniffed from the content (never the client's declared type or extension).
     */
    private function attachmentError(): ?string
    {
        $files = $this->attachmentFiles();
        if ($files === []) {
            return null;
        }
        if (count($files) > 1) {
            return 'error_count';
        }
        $file = $files[0];
        if (! $file instanceof UploadedFile) {
            return 'error_rejected';
        }
        if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return 'error_size';
        }
        if (! $file->isValid()) {
            return 'error_rejected';
        }
        $size = (int) $file->getSize();
        if ($size > (int) config('summit.enquiries.attachment_max_bytes')) {
            return 'error_size';
        }
        $mime = self::sniffMime($file);
        if ($size === 0 || $mime === null) {
            return 'error_rejected';
        }

        return in_array($mime, (array) config('summit.enquiries.attachment_mimes'), true) ? null : 'error_type';
    }

    public static function sniffMime(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            return null;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        return is_string($mime) ? $mime : null;
    }
}
