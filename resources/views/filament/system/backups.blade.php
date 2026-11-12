{{-- Backups (App\Filament\Cms\Pages\System\Backups): the schedule and where the files are, then the list. --}}
<x-filament-panels::page>
    @php($schedule = $this->getSchedule())
    <section class="slc-system-card" aria-labelledby="slc-backup-schedule">
        <h2 id="slc-backup-schedule" class="slc-system-card__title">{{ __('system.backups.schedule.heading') }}</h2>
        <dl class="slc-system-facts">
            <div class="slc-system-facts__item">
                <dt>{{ __('system.backups.schedule.daily') }}</dt>
                <dd>
                    @if ($schedule['enabled'])
                        <x-filament::badge color="success" icon="heroicon-m-check-circle">{{ __('system.backups.schedule.on') }}</x-filament::badge>
                        <span class="slc-system-figures">{{ __('system.backups.schedule.at', ['time' => $schedule['time']]) }}</span>
                    @else
                        <x-filament::badge color="warning" icon="heroicon-m-pause-circle">{{ __('system.backups.schedule.off') }}</x-filament::badge>
                    @endif
                </dd>
            </div>
            @if ($schedule['next'] !== null)
                <div class="slc-system-facts__item">
                    <dt>{{ __('system.backups.schedule.next') }}</dt>
                    <dd class="slc-system-figures">{{ $schedule['next'] }}</dd>
                </div>
            @endif
            <div class="slc-system-facts__item">
                <dt>{{ __('system.backups.schedule.contents') }}</dt>
                <dd>{{ __('system.backups.schedule.contents_value') }}</dd>
            </div>
            <div class="slc-system-facts__item">
                <dt>{{ __('system.backups.schedule.location') }}</dt>
                <dd><span class="slc-system-mono" dir="ltr"><bdi>{{ $schedule['location'] }}</bdi></span></dd>
            </div>
            <div class="slc-system-facts__item">
                <dt>{{ __('system.backups.schedule.stored') }}</dt>
                <dd class="slc-system-figures">{{ trans_choice('system.backups.schedule.stored_value', $schedule['count'], ['count' => $schedule['count'], 'size' => $schedule['total']]) }}</dd>
            </div>
        </dl>
    </section>

    {{ $this->table }}
</x-filament-panels::page>
