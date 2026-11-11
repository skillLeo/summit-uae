<?php

namespace App\Filament\Support;

use App\Models\Enquiry;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Review status of an enquiry. The public form writes only "new" (and "spam" for a filled honeypot,
 * Enquiry::STATUS_NEW / STATUS_SPAM); staff move a request to "in_review" and "closed" in the panel.
 * The column stays a plain string (no enum cast on the model), so an unknown value never breaks a page.
 */
enum EnquiryStatus: string implements HasColor, HasIcon, HasLabel
{
    case New = Enquiry::STATUS_NEW;
    case InReview = 'in_review';
    case Closed = 'closed';
    case Spam = Enquiry::STATUS_SPAM;

    /** Statuses that still need someone's attention. */
    public const OPEN = [Enquiry::STATUS_NEW, 'in_review'];

    public function getLabel(): string
    {
        return (string) __('admin.status.'.$this->value);
    }

    /** Semantic colours: bronze = arrived, warning = being handled, success = done, danger = rejected. */
    public function getColor(): string
    {
        return match ($this) {
            self::New => 'primary',
            self::InReview => 'warning',
            self::Closed => 'success',
            self::Spam => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::New => Heroicon::OutlinedInboxArrowDown,
            self::InReview => Heroicon::OutlinedClock,
            self::Closed => Heroicon::OutlinedCheckCircle,
            self::Spam => Heroicon::OutlinedNoSymbol,
        };
    }

    /** @return array<string, string> value => label, for selects and filters */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }

    /** Form state (enum instance or string) → a known status value, or null. */
    public static function normalize(mixed $value): ?string
    {
        if ($value instanceof self) {
            return $value->value;
        }

        return is_string($value) ? self::tryFrom($value)?->value : null;
    }

    public static function label(?string $value): string
    {
        return self::tryFrom((string) $value)?->getLabel() ?? (string) $value;
    }

    public static function color(?string $value): string
    {
        return self::tryFrom((string) $value)?->getColor() ?? 'gray';
    }

    public static function icon(?string $value): ?Heroicon
    {
        return self::tryFrom((string) $value)?->getIcon();
    }
}
