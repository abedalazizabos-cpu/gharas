<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قسم السنة: courses (آداب القرآن، الإعجاز، الأحاديث، الصلاة والطهارة، السيرة، الصحابة …).
 * The admin creates each course, writes its objectives, picks the sheikh who gives it and the
 * students taking part (from any circle), and sets the course's own grading rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunnah_courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category', 30)->index();
            $table->text('description')->nullable();
            // One objective per line.
            $table->text('objectives')->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('schedule')->nullable();
            $table->string('location')->nullable();
            // planned | running | finished
            $table->string('status', 20)->default('planned')->index();
            $table->unsignedTinyInteger('pass_score')->default(70);
            $table->boolean('results_to_parents')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sunnah_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sunnah_course_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('max_score')->default(10);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('sunnah_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sunnah_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            // enrolled | passed | failed
            $table->string('status', 20)->default('enrolled')->index();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->string('grade', 20)->nullable();
            $table->text('notes')->nullable();
            $table->date('evaluated_on')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['sunnah_course_id', 'student_id']);
        });

        Schema::create('sunnah_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sunnah_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sunnah_criterion_id')->constrained('sunnah_criteria')->cascadeOnDelete();
            // Snapshot, so editing the rules later never changes a recorded result.
            $table->unsignedTinyInteger('max_score');
            $table->unsignedTinyInteger('score');
            $table->unique(['sunnah_enrollment_id', 'sunnah_criterion_id'], 'sunnah_scores_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunnah_scores');
        Schema::dropIfExists('sunnah_enrollments');
        Schema::dropIfExists('sunnah_criteria');
        Schema::dropIfExists('sunnah_courses');
    }
};
