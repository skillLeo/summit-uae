<?php

namespace App\Filament\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The CSV of one report table (docs/briefs/design-2.1-and-admin-cms.md §11.2 "every chart has a table view and CSV
 * export"): exactly the header and the cells the "Table" view shows for the current filters, UTF-8 with a BOM (Excel
 * opens Arabic and Russian correctly). A cell that a spreadsheet would read as a formula (= + - @, tab, carriage
 * return; not a plain number) is prefixed with an apostrophe.
 */
final class ReportCsv
{
    public const BOM = "\xEF\xBB\xBF";

    /** @param  array{columns: list<string>, rows: list<list<string>>}  $table */
    public static function content(array $table): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, self::BOM);
        fputcsv($out, array_map(self::guard(...), $table['columns']), escape: '');
        foreach ($table['rows'] as $row) {
            fputcsv($out, array_map(self::guard(...), $row), escape: '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    public static function guard(string $cell): string
    {
        if ($cell === '' || is_numeric($cell)) {
            return $cell;
        }

        return in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$cell : $cell;
    }

    /**
     * Parse a CSV written by content() back into header and rows (tests and the Playwright flow compare it with the
     * table on screen).
     *
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    public static function parse(string $csv): array
    {
        $csv = str_starts_with($csv, self::BOM) ? substr($csv, 3) : $csv;
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        $lines = [];
        while (($cells = fgetcsv($stream, escape: '')) !== false) {
            if ($cells !== [null]) {
                $lines[] = array_map('strval', $cells);
            }
        }
        fclose($stream);

        return ['columns' => $lines[0] ?? [], 'rows' => array_slice($lines, 1)];
    }

    public static function filename(string $key, ReportData $data): string
    {
        $p = $data->period();

        return sprintf('summit-report-%s-%s-to-%s.csv', str_replace('_', '-', $key), $p->start->format('Y-m-d'), $p->end->subDay()->format('Y-m-d'));
    }

    /** @param  array{columns: list<string>, rows: list<list<string>>}  $table */
    public static function download(array $table, string $filename): StreamedResponse
    {
        $csv = self::content($table);

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
