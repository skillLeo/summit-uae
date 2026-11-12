<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The requests inbox (docs/briefs/design-2.1-and-admin-cms.md §10.1, §10.2, Phase 5).
 *
 * Every new enquiry column is nullable or has a default, so the public form keeps writing what it writes today; the
 * intake (App\Requests\Intake) adds the context it can see (source page, referrer, UTM values, device class, design,
 * the Privacy Policy revision) and the spam score. Times are UTC.
 *
 * - received_at: when the request arrived (the KPI clock, §11.3). Back-filled from created_at; demo data sets it.
 * - first_reviewed_at is written once (App\Requests\Workflow), closed_at on every close (cleared on reopen).
 * - Notes move to enquiry_notes (several per request, by user, encrypted); the old single internal_note is moved there
 *   and dropped. enquiry_events is the status history and timeline (no personal data in it).
 * - users.is_demo marks the sample case handlers of `summit:demo-requests` (removed with --purge).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $t) {
            $t->timestamp('received_at')->nullable()->after('status');
            $t->foreignId('assignee_id')->nullable()->after('received_at')->constrained('users')->nullOnDelete();
            $t->string('outcome', 16)->nullable()->after('assignee_id');        // engaged | not_engaged | referred | no_reply
            $t->timestamp('first_reviewed_at')->nullable()->after('outcome');
            $t->timestamp('closed_at')->nullable()->after('first_reviewed_at');
            $t->unsignedSmallInteger('spam_score')->default(0);
            $t->text('spam_reasons')->nullable();                               // JSON list of rule codes
            $t->string('source_page', 255)->nullable();                         // path of the page with the form
            $t->string('referrer', 255)->nullable();                            // external host the visitor came from
            $t->string('utm_source', 100)->nullable();
            $t->string('utm_medium', 100)->nullable();
            $t->string('utm_campaign', 100)->nullable();
            $t->string('utm_term', 100)->nullable();
            $t->string('utm_content', 100)->nullable();
            $t->string('device_class', 8)->nullable();                          // mobile | tablet | desktop
            $t->unsignedTinyInteger('design')->nullable();                      // 1 | 2
            $t->unsignedBigInteger('privacy_revision_id')->nullable();          // revisions.id of the Privacy Policy consented to
            $t->text('extras')->nullable();                                     // JSON: pending-approval extras, only while switched on
            $t->boolean('is_demo')->default(false)->index();
            $t->timestamp('overdue_notified_at')->nullable();
            $t->timestamp('anonymized_at')->nullable();
            $t->softDeletes();

            $t->index('received_at');
            $t->index('first_reviewed_at');
            $t->index('closed_at');
        });

        DB::table('enquiries')->whereNull('received_at')->update(['received_at' => DB::raw('created_at')]);

        Schema::create('enquiry_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('enquiry_id')->constrained('enquiries')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('body');                                                   // encrypted (model cast)
            $t->text('mentions')->nullable();                                   // JSON list of user ids
            $t->timestamp('edited_at')->nullable();
            $t->timestamps();
        });

        Schema::create('enquiry_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('enquiry_id')->constrained('enquiries')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type', 24);                                             // received | status | assigned | outcome | note | spam | export | anonymised | …
            $t->string('from', 32)->nullable();
            $t->string('to', 32)->nullable();
            $t->text('meta')->nullable();                                       // JSON, never personal data
            $t->timestamp('created_at')->nullable()->index();

            $t->index(['enquiry_id', 'created_at']);
        });

        // the single internal note of the old screen becomes the first note of its request
        if (Schema::hasColumn('enquiries', 'internal_note')) {
            foreach (DB::table('enquiries')->whereNotNull('internal_note')->get(['id', 'internal_note', 'updated_at']) as $row) {
                DB::table('enquiry_notes')->insert([
                    'enquiry_id' => $row->id,
                    'user_id' => null,
                    'body' => $row->internal_note,                              // already encrypted with the same cast
                    'created_at' => $row->updated_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
            Schema::table('enquiries', fn (Blueprint $t) => $t->dropColumn('internal_note'));
        }

        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_demo')->default(false)->after('admin_locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_demo'));
        Schema::table('enquiries', fn (Blueprint $t) => $t->text('internal_note')->nullable());
        Schema::dropIfExists('enquiry_events');
        Schema::dropIfExists('enquiry_notes');
        Schema::table('enquiries', function (Blueprint $t) {
            $t->dropConstrainedForeignId('assignee_id');
            $t->dropIndex(['received_at']);
            $t->dropIndex(['first_reviewed_at']);
            $t->dropIndex(['closed_at']);
            $t->dropSoftDeletes();
            $t->dropColumn([
                'received_at', 'outcome', 'first_reviewed_at', 'closed_at', 'spam_score', 'spam_reasons', 'source_page',
                'referrer', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'device_class', 'design',
                'privacy_revision_id', 'extras', 'is_demo', 'overdue_notified_at', 'anonymized_at',
            ]);
        });
    }
};
