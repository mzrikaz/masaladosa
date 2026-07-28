<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('language');
            $table->string('status')->default('pending');
            $table->string('external_url')->unique();
            $table->string('image_url')->nullable();
            $table->string('original_title');
            $table->text('original_body');
            $table->timestamp('original_published_at')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->string('rephrased_title')->nullable();
            $table->text('rephrased_summary')->nullable();
            $table->json('rephrased_paragraphs')->nullable();
            $table->timestamp('rephrased_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['language', 'status', 'original_published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
