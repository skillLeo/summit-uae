<?php

namespace App\Content\Import\Mappers;

use App\Content\Import\Cursor;
use App\Content\Import\ImportException;
use App\Content\Import\Mapper;

/**
 * 00_Site_Map*.docx → the registry of public labels:
 *   pages.{P01–P08, L01, L02, U01}.registry_label, services.{S01–S11}.registry_label,
 *   guides.{G01–G09}.title (the "Public guide title") and settings.link_registry
 *   ({H-L001: label, …, H-L056: label}, the homepage link registry).
 *
 * Each registry table row holds a red ID cell (column 0) and the black public label in column 1
 * of the same row; the link registry pairs each red "H-L0nn |" marker with the next black line.
 *
 * The Arabic folder does not supply an Arabic site map: its 00_Site_Map_and_Content_Rules_EN.docx
 * is an English governing map written entirely in red, with no black labels and no homepage link
 * registry. Rows without a black label are reported as parity differences and left empty; they
 * are never filled from another file.
 */
final class SiteMapMapper extends Mapper
{
    private const PAGES = ['P01', 'P02', 'P03', 'P04', 'P05', 'P06', 'P07', 'P08'];

    private const SERVICES = ['S01', 'S02', 'S03', 'S04', 'S05', 'S06', 'S07', 'S08', 'S09', 'S10', 'S11'];

    private const GUIDES = ['G01', 'G02', 'G03', 'G04', 'G05', 'G06', 'G07', 'G08', 'G09'];

    /** Legal and utility registry rows in source order; P08 is listed a second time here. */
    private const LEGAL = ['L01', 'L02', 'P08', 'U01'];

    private const LINKS = 56;

    public function sources(): array
    {
        return ['map' => '00_Site_Map'];
    }

    public function map(array $docs, string $locale): array
    {
        $c = $docs['map'];
        $file = $c->doc->filename();
        $pages = [];
        $services = [];
        $guides = [];
        $registry = [];
        /** @var array<string, list<string>> $missing report subject => registry IDs without a black label */
        $missing = [];

        // ---------------------------------------------------------------- page registries
        // Primary page registry (P01–P08), read before the service and guide tables; the legal and
        // utility table (L01, L02, P08 again, U01) follows them. Labels are collected per page.
        /** @var array<string, list<string>> $pageLabels */
        $pageLabels = [];
        foreach (self::PAGES as $code) {
            $label = $this->rowLabel($c, $code, "pages.{$code}.registry_label");
            $pageLabels[$code] = $label === null ? [] : [$label];
        }

        // ---------------------------------------------------------------- service detail registry
        foreach (self::SERVICES as $code) {
            $label = $this->rowLabel($c, $code, "services.{$code}.registry_label");
            if ($label === null) {
                $missing['services.*.registry_label'][] = $code;
            } else {
                $services[$code]['registry_label'] = $label;
            }
        }

        // ---------------------------------------------------------------- guide registry
        foreach (self::GUIDES as $code) {
            $label = $this->rowLabel($c, $code, "guides.{$code}.title");
            if ($label === null) {
                $missing['guides.*.title'][] = $code;
            } else {
                $guides[$code]['title'] = $label;
            }
        }

        // ---------------------------------------------------------------- legal and utility pages
        foreach (self::LEGAL as $code) {
            $repeat = ($pageLabels[$code] ?? []) !== [];
            $label = $this->rowLabel($c, $code, "pages.{$code}.registry_label".($repeat ? '#legal' : ''));
            $pageLabels[$code] ??= [];
            if ($label !== null) {
                $pageLabels[$code][] = $label;
            }
        }
        foreach ($pageLabels as $code => $labels) {
            if ($labels === []) {
                $missing['pages.*.registry_label'][] = $code;

                continue;
            }
            $pages[$code]['registry_label'] = $labels[0];
            foreach (array_unique(array_slice($labels, 1)) as $other) {
                if ($other !== $labels[0]) {
                    $this->report->add('label-mismatch', "pages.{$code}.registry_label",
                        "the primary page registry says \"{$labels[0]}\", the legal and utility registry says \"{$other}\"; the first is kept", $locale);
                }
            }
        }

        // ---------------------------------------------------------------- homepage link registry
        $hasRegistry = $c->indexOfNote('/^H-L001 \|/') !== null;
        if ($hasRegistry) {
            for ($i = 1; $i <= self::LINKS; $i++) {
                $id = sprintf('H-L%03d', $i);
                $c->expectNote('/^'.$id.' \|/');
                $label = $this->labelBefore($c, $c->indexOfNote('/^H-L\d{3} \|/'), "settings.link_registry.{$id}");
                if ($label === null) {
                    $missing['settings.link_registry'][] = $id;
                } else {
                    $registry[$id] = $label;
                }
            }
        }

        // ---------------------------------------------------------------- parity report
        if ($c->doc->publicBlocks() === []) {
            $this->report->add('parity', $file, sprintf('contains no black public copy: all %d blocks are red designer notes', count($c->doc->blocks)), $locale);
        }
        foreach (['pages.*.registry_label', 'services.*.registry_label', 'guides.*.title', 'settings.link_registry'] as $subject) {
            if (isset($missing[$subject])) {
                $this->report->add('parity', $subject, sprintf('%s has no black public label for %s; the field is absent in this language', $file, implode(', ', $missing[$subject])), $locale);
            }
        }
        if (! $hasRegistry) {
            $this->report->add('parity', 'settings.link_registry', sprintf('%s has no homepage link registry (H-L001 to H-L%03d); the field is absent in this language', $file, self::LINKS), $locale);
        }

        return array_filter([
            'pages' => $pages,
            'services' => $services,
            'guides' => $guides,
            'settings' => $registry === [] ? [] : ['link_registry' => $registry],
        ]);
    }

    /** Black label in the same table row as the red ID cell, or null when that row has none. */
    private function rowLabel(Cursor $c, string $code, string $field): ?string
    {
        $id = $c->expectNote('/^'.$code.'$/');
        if ($id->cell === null || $id->cell['c'] !== 0) {
            throw new ImportException($c->where($id)." expected the registry ID cell {$code} in the first table column");
        }
        $next = $c->peekPublic();
        if ($next === null || $next->cell === null || $next->cell['t'] !== $id->cell['t'] || $next->cell['r'] !== $id->cell['r']) {
            return null;
        }

        return $c->take($field);
    }

    /** Next black line when it comes before block $limit (the next marker), otherwise null. */
    private function labelBefore(Cursor $c, ?int $limit, string $field): ?string
    {
        $next = $c->peekPublic();
        if ($next === null || ($limit !== null && $next->index >= $limit)) {
            return null;
        }

        return $c->take($field);
    }
}
