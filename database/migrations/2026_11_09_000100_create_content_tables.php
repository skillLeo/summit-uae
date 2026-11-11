<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Translatable content tables keyed by the stable IDs of the site map.
 *
 * Every table has a translatable `data` JSON column ({"en": {...}, "ar": {...}, "ru": {...}})
 * for structured copy and a non-translatable `meta` JSON column for language-independent
 * attributes, so the import can carry complete DOCX structures without schema churn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $t) {
            $t->string('code', 8)->primary();          // P01–P08, L01, L02, U01
            $t->string('slug')->nullable();
            $t->unsignedSmallInteger('position')->default(0);
            $t->json('h1')->nullable();
            $t->json('seo_title')->nullable();
            $t->json('meta_description')->nullable();
            $t->json('data')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        Schema::create('services', function (Blueprint $t) {
            $t->string('code', 8)->primary();          // S01–S11
            $t->string('slug');
            $t->unsignedSmallInteger('position');
            $t->json('title')->nullable();
            $t->json('data')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        Schema::create('guides', function (Blueprint $t) {
            $t->string('code', 8)->primary();          // G01–G09
            $t->string('anchor');
            $t->string('category')->nullable();
            $t->unsignedSmallInteger('position');
            $t->json('title')->nullable();
            $t->json('data')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        Schema::create('guide_categories', function (Blueprint $t) {
            $t->string('code', 8)->primary();          // gc-01–gc-03
            $t->unsignedSmallInteger('position');
            $t->json('name')->nullable();
            $t->json('data')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        Schema::create('case_studies', function (Blueprint $t) {
            $t->string('code', 12)->primary();         // case-01–case-06
            $t->string('anchor');
            $t->unsignedSmallInteger('position');
            $t->json('title')->nullable();
            $t->json('data')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        Schema::create('faq_categories', function (Blueprint $t) {
            $t->string('code', 8)->primary();          // cat-01–cat-12
            $t->string('anchor');
            $t->unsignedSmallInteger('position');
            $t->json('name')->nullable();
            $t->json('data')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        Schema::create('faqs', function (Blueprint $t) {
            $t->string('code', 8)->primary();          // faq-01–faq-74
            $t->string('anchor');
            $t->string('category', 8)->nullable()->index();
            $t->unsignedSmallInteger('position');
            $t->json('question')->nullable();
            $t->json('data')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        Schema::create('team_members', function (Blueprint $t) {
            $t->string('code', 16)->primary();         // team-ahmed, team-mohamed, team-sarah
            $t->string('anchor');
            $t->unsignedSmallInteger('position');
            $t->string('portrait')->nullable();
            $t->json('name')->nullable();
            $t->json('data')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        Schema::create('specialist_sites', function (Blueprint $t) {
            $t->string('code', 4)->primary();          // X01–X06
            $t->unsignedSmallInteger('position');
            $t->string('domain');
            $t->string('url');
            $t->json('label')->nullable();
            $t->json('data')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        Schema::create('ui_strings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->json('value');
            $t->timestamps();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->json('value');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'ui_strings', 'specialist_sites', 'team_members', 'faqs', 'faq_categories', 'case_studies', 'guide_categories', 'guides', 'services', 'pages'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
