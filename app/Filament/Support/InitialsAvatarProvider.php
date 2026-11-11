<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin avatars drawn locally as an inline SVG data URI (initials, gold on night). Replaces Filament's
 * default UiAvatarsProvider, which would send every admin's name to ui-avatars.com: the panel makes no
 * third-party requests.
 */
final class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $name = trim((string) Filament::getNameForDefaultAvatar($record));
        $initials = collect(preg_split('/\s+/u', $name) ?: [])
            ->map(fn (string $part) => mb_substr((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $part), 0, 1))
            ->filter()
            ->take(2)
            ->implode('');
        $initials = mb_strtoupper($initials !== '' ? $initials : '·');

        $night = AdminPalette::token('night');
        $gold = AdminPalette::token('gold');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="'.$night.'"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" fill="'.$gold.'" '
            .'font-family="system-ui, sans-serif" font-size="26" font-weight="600">'
            .htmlspecialchars($initials, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
