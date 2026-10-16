<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surahs', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('name', 50);
            $table->unsignedSmallInteger('ayah_count');
            $table->unsignedTinyInteger('juz_start')->nullable();
        });

        Schema::create('memorization_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('circle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('recorded_on')->index();
            // new | revision | consolidation
            $table->string('type', 20)->index();
            $table->unsignedSmallInteger('surah_id');
            $table->foreign('surah_id')->references('id')->on('surahs');
            $table->unsignedSmallInteger('from_ayah');
            $table->unsignedSmallInteger('to_ayah');
            $table->decimal('pages', 5, 2)->nullable();
            $table->unsignedTinyInteger('score');
            // excellent | very_good | good | acceptable | weak (derived from score)
            $table->string('grade', 20);
            $table->unsignedSmallInteger('mistakes_count')->default(0);
            $table->text('notes')->nullable();
            $table->text('recommendations')->nullable();
            $table->boolean('shared_with_parent')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_id', 'surah_id', 'type']);
        });

        // Admin-editable assessment criteria (e.g. memorization quality, tajweed).
        Schema::create('assessment_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('max_score')->default(10);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('circle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('assessed_on')->index();
            // Stored as a percentage of the max possible score.
            $table->decimal('percentage', 5, 2);
            $table->text('notes')->nullable();
            $table->boolean('shared_with_parent')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('assessment_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_criterion_id')->constrained('assessment_criteria')->cascadeOnDelete();
            // Snapshot of the max score at the time of grading.
            $table->unsignedTinyInteger('max_score');
            $table->unsignedTinyInteger('score');
            $table->unique(['assessment_id', 'assessment_criterion_id'], 'assessment_scores_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_scores');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('assessment_criteria');
        Schema::dropIfExists('memorization_records');
        Schema::dropIfExists('surahs');
    }
};
