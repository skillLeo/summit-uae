<?php

// The Media Library's own records (docs/briefs/design-2.1-and-admin-cms.md §8.9): one row per image or PDF download of
// the library (App\Models\MediaAsset), owning exactly one file in the `media` table (collection "file"). The file's alt
// text per language, focal point and copy_source live in that media row's custom_properties. Not drafted: saving is
// immediate; soft deletes give the Trash and restore. No JSON column here (custom_properties is the package's longText).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('collection', 32)->default('library')->index(); // library | brand | social
            $table->string('copy_source', 32)->default('pending');
            $table->string('import_key', 191)->nullable()->unique(); // summit:media-import: the public/ path it copied
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
