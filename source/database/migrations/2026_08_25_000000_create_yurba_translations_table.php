<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yurba_translations', function (Blueprint $table) {
            $table->id();
            $table->morphs('translatable');
            $table->string('locale', 12);
            $table->string('field');
            $table->longText('value')->nullable();

            $table->unique(['translatable_type', 'translatable_id', 'locale', 'field'], 'yurba_translations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yurba_translations');
    }
};
