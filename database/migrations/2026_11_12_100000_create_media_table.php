<?php

// The media library (spatie/laravel-medialibrary 11, docs/briefs/design-2.1-and-admin-cms.md §8.9): the package's own
// table, with its JSON payloads as JSON text in longText columns like every other CMS table (MySQL's JSON type re-sorts
// object keys; see 2026_11_09_000100_create_content_tables). custom_properties holds the per-locale alt text
// ({alt: {en, ar, ru}}), the focal point ({focal_point: [x, y]} in %) and copy_source.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->string('model_id', 64);
            $table->index(['model_type', 'model_id']);
            $table->uuid()->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->longText('manipulations');
            $table->longText('custom_properties');
            $table->longText('generated_conversions');
            $table->longText('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->nullableTimestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
