<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consultation enquiries (the shared Home / Contact form). See docs/enquiries.md.
 *
 * Privacy: summary and deadline_details are encrypted by the model (Eloquent "encrypted" cast);
 * the visitor's IP is never stored, only an HMAC-SHA256 of it keyed with the app key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $t) {
            $t->id();
            // SLC-yymmdd-XXXXXX (6 random Crockford base32 characters; not sequential).
            $t->string('reference', 17)->unique();
            // Client-generated per enquiry; a replay returns the original reference.
            $t->uuid('idempotency_key')->unique();
            $t->string('locale', 2);
            $t->string('status', 16)->default('new')->index(); // new | spam (admin adds more later)
            $t->string('name', 120);
            $t->string('phone', 16)->nullable();               // canonical "+" and 7–15 digits
            $t->string('email', 254)->nullable();
            $t->string('preferred_language', 2)->nullable();   // ar | en | ru
            $t->string('matter_type', 8)->nullable();          // S01–S11 | other | unsure
            $t->text('summary');                                // encrypted
            $t->date('deadline_date')->nullable();              // past dates allowed
            $t->text('deadline_details')->nullable();           // encrypted
            $t->timestamp('consent_at');
            $t->text('consent_text');                           // exact consent label shown, in the visitor's language
            $t->char('ip_hash', 64)->index();
            // Only written when config('summit.features.attachments_enabled') is true (off in Phase 1).
            $t->string('attachment_path')->nullable();          // private "local" disk, never a public URL
            $t->string('attachment_mime', 64)->nullable();      // sniffed from the content, not the client
            $t->unsignedInteger('attachment_size')->nullable(); // bytes
            $t->timestamps();

            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
