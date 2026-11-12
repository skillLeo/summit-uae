<?php

/*
| Local QA only (tests/e2e-admin/requests.spec.mjs): a request for the keyboard triage flow.
|   php scripts/qa/requests-fixture.php create   creates "QA Keyboard Flow" (status New, received now) and prints its id
|   php scripts/qa/requests-fixture.php state <id>   prints status, assignee, notes and first_reviewed_at as JSON
|   php scripts/qa/requests-fixture.php remove   deletes every QA flow request (and its notes and timeline)
| Refuses to run in production.
*/

use App\Models\Enquiry;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (app()->isProduction()) {
    fwrite(STDERR, "Refusing to run in production.\n");
    exit(1);
}

$mode = $argv[1] ?? '';
if ($mode === 'create') {
    $enquiry = new Enquiry;
    $enquiry->forceFill([
        'reference' => Enquiry::newReference(),
        'idempotency_key' => (string) Str::uuid(),
        'locale' => 'en',
        'status' => Enquiry::STATUS_NEW,
        'received_at' => now()->addSeconds(5),
        'name' => 'QA Keyboard Flow',
        'email' => 'qa-keyboard-flow@example.invalid',
        'matter_type' => 'S06',
        'summary' => 'QA request for the keyboard triage flow.',
        'consent_at' => now(),
        'consent_text' => 'QA',
        'ip_hash' => hash('sha256', 'qa-flow'),
    ])->save();
    echo $enquiry->id;
    exit(0);
}
if ($mode === 'state') {
    $e = Enquiry::withTrashed()->with('notes')->findOrFail((int) ($argv[2] ?? 0));
    echo json_encode(['status' => $e->status, 'assignee_id' => $e->assignee_id, 'notes' => $e->notes->pluck('body'), 'first_reviewed_at' => $e->first_reviewed_at?->toIso8601String()]);
    exit(0);
}
if ($mode === 'remove') {
    $count = 0;
    foreach (Enquiry::withTrashed()->where('name', 'QA Keyboard Flow')->get() as $e) {
        $e->forceDelete();
        $count++;
    }
    echo "removed {$count}\n";
    exit(0);
}
fwrite(STDERR, "usage: create | state <id> | remove\n");
exit(1);
