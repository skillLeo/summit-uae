{{--
    Internal notes of a request (App\Filament\Resources\Enquiries\Schemas\EnquiryInfolist; brief §10.4). Encrypted at
    rest, decrypted for this page only. The author may edit or delete a note for 10 minutes (App\Requests\Workflow).
--}}
@php
    /** @var \App\Models\Enquiry $record */
    $record = $getRecord();
    $notes = $record->notes()->with('user')->get();
    $workflow = \App\Requests\Workflow::make();
    $me = auth()->user();
    $staff = \App\Models\User::query()->whereIn('id', $notes->pluck('mentions')->flatten()->filter()->unique())->pluck('name', 'id');
@endphp
<div class="slc-notes" data-panel="notes">
    @forelse ($notes as $note)
        <article class="slc-note" wire:key="note-{{ $note->id }}">
            <header class="slc-note__meta">
                <span class="slc-note__author">{{ $note->user?->name ?? __('requests.value.system') }}</span>
                <time datetime="{{ $note->created_at->toIso8601String() }}" title="{{ \App\Filament\Support\RequestFormat::exact($note->created_at) }}">{{ \App\Filament\Support\RequestFormat::received($note->created_at) }}</time>
                @if ($note->edited_at)
                    <span class="slc-note__edited">{{ __('requests.event.note_edited') }}</span>
                @endif
            </header>
            <div class="slc-prose" dir="auto">{!! \App\Filament\Support\AdminFormat::multiline($note->body) !!}</div>
            @if (filled($note->mentions))
                <p class="slc-note__mentions">{{ __('requests.field.mentions') }}: {{ collect($note->mentions)->map(fn ($id) => $staff[$id] ?? null)->filter()->implode(', ') }}</p>
            @endif
            @if ($workflow->canChangeNote($note, $me))
                <div class="slc-note__actions">
                    {{ ($this->editNoteAction)(['note' => $note->id]) }}
                    {{ ($this->deleteNoteAction)(['note' => $note->id]) }}
                </div>
            @endif
        </article>
    @empty
        <p class="slc-muted">{{ __('requests.empty.notes') }}</p>
    @endforelse
</div>
