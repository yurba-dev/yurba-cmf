<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('public');
            $table->string('path', 2048);
            $table->string('thumb_path', 2048)->nullable();
            $table->string('name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
        });

        Schema::create('yurba_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('revisionable_type');
            $table->unsignedBigInteger('revisionable_id');
            $table->json('data');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['revisionable_type', 'revisionable_id']);
        });

        Schema::create('yurba_seo', function (Blueprint $table) {
            $table->id();
            $table->string('seoable_type');
            $table->unsignedBigInteger('seoable_id');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_image', 2048)->nullable();
            $table->boolean('noindex')->default(false);
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id']);
        });

        Schema::create('yurba_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from', 2048);
            $table->string('to', 2048);
            $table->unsignedSmallInteger('status')->default(301);
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamps();

            $table->index(['enabled']);
        });

        Schema::create('yurba_content', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yurba_content');
        Schema::dropIfExists('yurba_redirects');
        Schema::dropIfExists('yurba_seo');
        Schema::dropIfExists('yurba_revisions');
        Schema::dropIfExists('media');
    }
};
