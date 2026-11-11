<?php

namespace App\Design\D2;

/**
 * Design 2 "pending client approval" switch (Design 2.1 brief §8.7, §5.1, D39).
 *
 * Copy the team wrote (the tool blocks "Start your matter", "Anatomy of a Document" and "Where Do You Stand?",
 * the calculators page, and the form's two extra fields) is pending client approval. The brief's setting "Show
 * pending copy on the public site" is on in local and staging (for the client meeting) and off in production; when
 * it is off, pending blocks and fields are hidden. Until the CMS settings of Phase 3 exist, the switch is
 * config('design.d2.show_pending') (env SUMMIT_SHOW_PENDING; by default on everywhere except production).
 *
 * Interface strings (button labels, aria labels, form messages) are not blocks or fields: they stay visible and
 * keep their data-copy-status="proposed" marker outside production.
 */
final class Pending
{
    /** Whether pending blocks and fields render on the public site. */
    public static function visible(): bool
    {
        $value = config('design.d2.show_pending');

        return $value === null ? ! app()->isProduction() : (bool) $value;
    }

    /**
     * Whether one switchable home block renders: its switch in config('design.d2.home.blocks') is on and, for a
     * block whose copy is pending, pending copy is shown.
     */
    public static function block(string $key): bool
    {
        $block = config('design.d2.home.blocks.'.$key);
        if (! is_array($block) || ! ($block['enabled'] ?? false)) {
            return false;
        }

        return ! ($block['pending'] ?? false) || self::visible();
    }
}
