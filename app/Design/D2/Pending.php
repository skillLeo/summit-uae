<?php

namespace App\Design\D2;

use App\Content\Cms\CopySource;
use App\Content\Cms\HomeBlocks;

/**
 * Design 2 "pending client approval" switch (Design 2.1 brief §8.7, §5.1, D39).
 *
 * Copy the team wrote (the tool blocks "Start your matter", "Anatomy of a Document" and "Where Do You Stand?",
 * the calculators page, and the form's two extra fields) is pending client approval. The setting "Show pending copy on
 * the public site" is on in local and staging (for the client meeting) and off in production; when it is off, pending
 * blocks and fields are hidden. Since Phase 3 it is the CMS setting content.show_pending (App\Settings\ContentSettings:
 * true, false, or null = on everywhere except production); config('design.d2.show_pending') (env SUMMIT_SHOW_PENDING),
 * when set, decides for one environment.
 *
 * Home blocks: their order, switch and copy_source are the blocks of page P01 in the CMS (App\Content\Cms\HomeBlocks); a
 * block whose copy_source is pending renders only while pending copy is shown.
 *
 * Interface strings (button labels, aria labels, form messages) are not blocks or fields: they stay visible and
 * keep their data-copy-status="proposed" marker outside production.
 */
final class Pending
{
    /** The home blocks the switch concerns (DocxBlack reports them to scripts/qa/docx-conformance.mjs). */
    public const SWITCHABLE = ['start', 'anatomy', 'navigator', 'team'];

    /** Whether pending blocks and fields render on the public site. */
    public static function visible(): bool
    {
        $value = config('design.d2.show_pending');
        $value ??= content()->settings('content')['show_pending'] ?? null;

        return $value === null ? ! app()->isProduction() : (bool) $value;
    }

    /** Whether the home block of this type renders: it is on and, when its copy is pending, pending copy is shown. */
    public static function block(string $type): bool
    {
        $block = content()->block(HomeBlocks::PAGE, $type);

        return $block !== null && self::shows($block);
    }

    /**
     * Whether a resolved page block renders (content()->blocks()): its switch (or the environment's override,
     * config design.d2.home.block_overrides) is on and its copy is not pending, or pending copy is shown.
     *
     * @param  array{type: string, enabled: bool, copy_source: string}  $block
     */
    public static function shows(array $block): bool
    {
        $override = config('design.d2.home.block_overrides.'.$block['type']);
        if (! (is_bool($override) ? $override : $block['enabled'])) {
            return false;
        }

        return $block['copy_source'] !== CopySource::PENDING || self::visible();
    }

    /**
     * The switchable home blocks as {type: {enabled, pending}} (the shape config('design.d2.home.blocks') had).
     *
     * @return array<string, array{enabled: bool, pending: bool}>
     */
    public static function homeBlocks(): array
    {
        $out = [];
        foreach (self::SWITCHABLE as $type) {
            $block = content()->block(HomeBlocks::PAGE, $type);
            if ($block !== null) {
                $override = config('design.d2.home.block_overrides.'.$type);
                $out[$type] = [
                    'enabled' => is_bool($override) ? $override : $block['enabled'],
                    'pending' => $block['copy_source'] === CopySource::PENDING,
                ];
            }
        }

        return $out;
    }
}
