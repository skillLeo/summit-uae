<?php

namespace App\Requests;

use App\Models\Enquiry;
use App\Models\EnquiryEvent;
use App\Models\EnquiryNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The request workflow (docs/briefs/design-2.1-and-admin-cms.md §10.2, §10.4). Every change of a request goes through
 * here and writes one timeline event (App\Models\EnquiryEvent, no personal data):
 *
 * - Statuses New, In review, Closed, plus Spam. Closing needs an outcome (Engaged, Not engaged, Referred, No reply) and
 *   stamps closed_at; reopening clears closed_at and the outcome.
 * - first_reviewed_at is set once: the first time the status leaves New, or the first note, whichever comes first.
 * - Notes are encrypted; their author may edit or delete them within EDIT_WINDOW_MINUTES; mentioned staff are told.
 */
final class Workflow
{
    public const EDIT_WINDOW_MINUTES = 10;

    public function __construct(private readonly Notifier $notifier) {}

    public static function make(): self
    {
        return app(self::class);
    }

    /**
     * @return bool whether anything changed
     */
    public function setStatus(Enquiry $enquiry, string $status, ?User $by = null, ?string $outcome = null): bool
    {
        if (! in_array($status, Enquiry::STATUSES, true)) {
            throw new InvalidArgumentException("Unknown request status [{$status}].");
        }
        if ($status === Enquiry::STATUS_CLOSED && ! in_array($outcome, Enquiry::OUTCOMES, true)) {
            throw new InvalidArgumentException('Closing a request needs an outcome.');
        }
        $from = $enquiry->status;
        if ($from === $status && ($status !== Enquiry::STATUS_CLOSED || $outcome === $enquiry->outcome)) {
            return false;
        }

        DB::transaction(function () use ($enquiry, $status, $by, $outcome, $from): void {
            $now = now();
            $updates = ['status' => $status, 'status_changed_at' => $now];
            if ($from === Enquiry::STATUS_NEW && $status !== Enquiry::STATUS_NEW && $enquiry->first_reviewed_at === null) {
                $updates['first_reviewed_at'] = $now;
            }
            if ($status === Enquiry::STATUS_CLOSED) {
                $updates['closed_at'] = $now;
                $updates['outcome'] = $outcome;
            } elseif ($from === Enquiry::STATUS_CLOSED) {
                $updates['closed_at'] = null;
                $updates['outcome'] = null;
            }
            $enquiry->forceFill($updates)->save();

            if ($from !== $status) {
                $this->event($enquiry, 'status', $by, $from, $status);
            }
            if ($status === Enquiry::STATUS_CLOSED) {
                $this->event($enquiry, 'outcome', $by, null, $outcome);
            }
        });

        return true;
    }

    public function assign(Enquiry $enquiry, ?int $userId, ?User $by = null): bool
    {
        if ($userId !== null && ! User::query()->whereKey($userId)->exists()) {
            throw new InvalidArgumentException('Unknown assignee.');
        }
        $from = $enquiry->assignee_id;
        if ($from === $userId) {
            return false;
        }
        $enquiry->forceFill(['assignee_id' => $userId])->save();
        $this->event($enquiry, 'assigned', $by, $from === null ? null : (string) $from, $userId === null ? null : (string) $userId);
        if ($userId !== null && $userId !== $by?->getKey()) {
            $this->notifier->assigned($enquiry, User::query()->find($userId), $by);
        }

        return true;
    }

    /** @param  list<int>  $mentions */
    public function addNote(Enquiry $enquiry, User $by, string $body, array $mentions = []): EnquiryNote
    {
        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('A note needs text.');
        }
        $mentions = $this->validMentions($mentions, $by);

        return DB::transaction(function () use ($enquiry, $by, $body, $mentions): EnquiryNote {
            $note = $enquiry->notes()->create(['user_id' => $by->getKey(), 'body' => $body, 'mentions' => $mentions ?: null]);
            if ($enquiry->first_reviewed_at === null) {
                $enquiry->forceFill(['first_reviewed_at' => $note->created_at])->save();
            }
            $this->event($enquiry, 'note', $by, null, null, ['note' => $note->getKey(), 'mentions' => count($mentions)]);
            foreach ($mentions as $id) {
                $this->notifier->mentioned($enquiry, User::query()->find($id), $by);
            }

            return $note;
        });
    }

    public function canChangeNote(EnquiryNote $note, ?User $user): bool
    {
        return $user !== null
            && $note->user_id === $user->getKey()
            && $note->created_at !== null
            && $note->created_at->greaterThan(now()->subMinutes(self::EDIT_WINDOW_MINUTES));
    }

    public function editNote(EnquiryNote $note, User $by, string $body): void
    {
        $body = trim($body);
        if (! $this->canChangeNote($note, $by) || $body === '') {
            throw new InvalidArgumentException('This note can no longer be edited.');
        }
        $note->forceFill(['body' => $body, 'edited_at' => now()])->save();
        $this->event($note->enquiry, 'note_edited', $by, null, null, ['note' => $note->getKey()]);
    }

    public function deleteNote(EnquiryNote $note, User $by): void
    {
        if (! $this->canChangeNote($note, $by)) {
            throw new InvalidArgumentException('This note can no longer be deleted.');
        }
        $enquiry = $note->enquiry;
        $id = $note->getKey();
        $note->delete();
        $this->event($enquiry, 'note_deleted', $by, null, null, ['note' => $id]);
    }

    /** @param  array<string, mixed>  $meta  never personal data */
    public function event(Enquiry $enquiry, string $type, ?User $by = null, ?string $from = null, ?string $to = null, array $meta = []): EnquiryEvent
    {
        return EnquiryEvent::query()->create([
            'enquiry_id' => $enquiry->getKey(),
            'user_id' => $by?->getKey(),
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    private function validMentions(array $ids, User $by): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, fn (int $id) => $id !== $by->getKey()));
        if ($ids === []) {
            return [];
        }

        return User::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }
}
