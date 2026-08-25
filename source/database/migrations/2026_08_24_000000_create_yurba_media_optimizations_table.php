<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// log of image-optimization outcomes, surfaced in the "Optimization log" screen.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yurba_media_optimizations', function (Blueprint $table) {
            $table->id();
            $table->string('path', 2048);
            $table->string('source', 32)->default('upload'); // upload | command
            $table->string('status', 32);                    // optimized | skipped | unchanged
            $table->string('reason', 512)->nullable();        // why it was skipped
            $table->boolean('optimized')->default(false);     // original re-encoded/resized
            $table->boolean('thumbnailed')->default(false);   // thumbnail generated
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('orig_size')->default(0);
            $table->unsignedBigInteger('new_size')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yurba_media_optimizations');
    }
};
