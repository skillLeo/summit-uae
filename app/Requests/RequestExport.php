<?php

namespace App\Requests;

use App\Filament\Support\EnquiryLabels;
use App\Models\Enquiry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV and XLSX export of inbox rows (docs/briefs/design-2.1-and-admin-cms.md §10.3): the columns of the list plus the
 * workflow times, in the admin's language, times in Dubai. The summary, the deadline details and the notes are never
 * exported (they stay on the request page); the name and contact details are, because staff export to follow up.
 * Every export is logged (activity log, Phase 7) with the number of rows, never their content.
 */
final class RequestExport
{
    /** @return list<string> */
    public static function headings(): array
    {
        return [
            (string) __('requests.column.reference'),
            (string) __('requests.column.received'),
            (string) __('requests.column.status'),
            (string) __('requests.field.outcome'),
            (string) __('requests.column.matter'),
            (string) __('requests.field.site_language'),
            (string) __('requests.field.preferred_language'),
            (string) __('requests.column.name'),
            (string) __('requests.field.phone'),
            (string) __('requests.field.email'),
            (string) __('requests.column.contact'),
            (string) __('requests.column.assignee'),
            (string) __('requests.field.first_review'),
            (string) __('requests.column.waiting'),
            (string) __('requests.field.closed'),
            (string) __('requests.field.source_page'),
            (string) __('requests.field.referrer'),
            'utm_source', 'utm_medium', 'utm_campaign',
            (string) __('requests.field.device'),
            (string) __('requests.field.design'),
        ];
    }

    /**
     * @param  iterable<Enquiry>  $rows
     * @return list<list<string|int|float|null>>
     */
    public static function rows(iterable $rows): array
    {
        $hours = BusinessHours::fromSettings();
        $zone = $hours->timezone();
        $at = fn ($t) => $t === null ? '' : CarbonImmutable::instance($t)->setTimezone($zone)->format('Y-m-d H:i');
        $out = [];
        foreach ($rows as $e) {
            $out[] = [
                $e->reference,
                $at($e->received_at ?? $e->created_at),
                \App\Filament\Support\EnquiryStatus::label($e->status),
                $e->outcome === null ? '' : (string) __('requests.outcome.'.$e->outcome),
                EnquiryLabels::matter($e->matter_type),
                EnquiryLabels::language($e->locale),
                EnquiryLabels::language($e->preferred_language),
                $e->name,
                (string) $e->phone,
                (string) $e->email,
                \App\Filament\Support\RequestFormat::contactGiven($e),
                $e->assignee?->name ?? '',
                $at($e->first_reviewed_at),
                round(ReviewTarget::waited($e, $hours), 1),
                $at($e->closed_at),
                (string) $e->source_page,
                (string) $e->referrer,
                (string) $e->utm_source,
                (string) $e->utm_medium,
                (string) $e->utm_campaign,
                $e->device_class === null ? '' : (string) __('requests.value.device.'.$e->device_class),
                $e->design === null ? '' : (string) $e->design,
            ];
        }

        return $out;
    }

    /** @param  Collection<int, Enquiry>|iterable<Enquiry>  $records */
    public static function download(iterable $records, string $format): StreamedResponse
    {
        $records = collect($records)->each(fn (Enquiry $e) => $e->loadMissing('assignee'));
        $name = 'requests-'.now((string) config('summit.contact.timezone', 'Asia/Dubai'))->format('Ymd-His');
        $rows = self::rows($records);
        $headings = self::headings();
        \App\Support\Audit::log('requests', 'export', null, ['format' => $format, 'rows' => count($rows)]);

        if ($format === 'xlsx') {
            return response()->streamDownload(
                fn () => self::xlsx('php://output', $headings, $rows),
                $name.'.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            );
        }

        return response()->streamDownload(function () use ($headings, $rows): void {
            echo self::csv($headings, $rows);
        }, $name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The one sanitiser of both exports (CSV and XLSX): a text cell that starts with = @ tab or CR, or with + or - not
     * followed by digits and spaces only, would run as a formula in a spreadsheet, so it gets a leading apostrophe.
     * Visitors type these fields. Numbers and phone numbers ("+971 52 466 6191") stay as they are.
     */
    public static function cell(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^(?:[=@\t\r]|[+\-](?![0-9 ]*$))/', $value) === 1 ? "'".$value : $value;
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<mixed>>  $rows
     */
    public static function xlsx(string $path, array $headings, array $rows): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_map(self::cell(...), $headings)));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(self::cell(...), $row)));
        }
        $writer->close();
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<mixed>>  $rows
     */
    public static function csv(array $headings, array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_map(self::cell(...), $headings), ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, array_map(self::cell(...), $row), ',', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
