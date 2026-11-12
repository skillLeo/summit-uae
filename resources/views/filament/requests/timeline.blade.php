{{--
    The timeline of a request (brief §10.4): every change, who made it and when (App\Models\EnquiryEvent; no personal
    data). Times are Dubai time; the exact time is in the title.
--}}
@php
    /** @var \App\Models\Enquiry $record */
    $record = $getRecord();
    $events = $record->events()->with('user')->get()->reverse();
    $users = \App\Models\User::query()->whereIn('id', $events->where('type', 'assigned')->flatMap(fn ($e) => [$e->from, $e->to])->filter()->unique())->pluck('name', 'id');
    $status = fn (?string $s) => \App\Filament\Support\EnquiryStatus::label($s);
    $line = function (\App\Models\EnquiryEvent $e) use ($users, $status): string {
        return match ($e->type) {
            'received' => $e->to === \App\Models\Enquiry::STATUS_SPAM ? __('requests.event.received_spam') : __('requests.event.received'),
            'status' => __('requests.event.status', ['from' => $status($e->from), 'to' => $status($e->to)]),
            'assigned' => $e->to === null ? __('requests.event.unassigned') : __('requests.event.assigned', ['to' => $users[$e->to] ?? '#'.$e->to]),
            'outcome' => __('requests.event.outcome', ['to' => \App\Filament\Support\RequestFormat::outcome($e->to) ?? '']),
            default => __('requests.event.'.$e->type),
        };
    };
@endphp
<ol class="slc-timeline" data-panel="timeline">
    @foreach ($events as $event)
        <li class="slc-timeline__item" wire:key="event-{{ $event->id }}">
            <span class="slc-timeline__what">{{ $line($event) }}</span>
            <span class="slc-timeline__meta">
                <time datetime="{{ $event->created_at?->toIso8601String() }}" title="{{ \App\Filament\Support\RequestFormat::exact($event->created_at) }}">{{ \App\Filament\Support\RequestFormat::dateTime($event->created_at) }}</time>
                @if ($event->user)
                    <span>{{ __('requests.event.by', ['name' => $event->user->name]) }}</span>
                @endif
            </span>
        </li>
    @endforeach
</ol>
