{{-- Health (App\Filament\Cms\Pages\System\Health): when the checks ran, then one row per check. --}}
<x-filament-panels::page>
    @php($checkedAt = $this->getCheckedAt())
    @if ($checkedAt !== null)
        <p class="slc-system-note">
            {{ __('system.health.checked_at', ['time' => $checkedAt]) }}
        </p>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
