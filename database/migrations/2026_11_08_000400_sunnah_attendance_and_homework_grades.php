<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قسم السنة: attendance taken by the course's sheikh at every meeting, and a grade for each
 * student's homework.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunnah_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sunnah_course_id')->constrained()->cascadeOnDelete();
            $table->date('held_on');
            $table->string('topic')->nullable();
            $table->foreignId('taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['sunnah_course_id', 'held_on']);
        });

        Schema::create('sunnah_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sunnah_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            // present | late | absent | excused
            $table->string('status', 20);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['sunnah_session_id', 'student_id']);
        });

        Schema::table('sunnah_posts', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_score')->nullable()->after('due_on');
        });

        Schema::table('sunnah_post_follows', function (Blueprint $table) {
            $table->unsignedTinyInteger('score')->nullable()->after('done_at');
            $table->string('feedback')->nullable()->after('score');
            $table->timestamp('graded_at')->nullable()->after('feedback');
            $table->foreignId('graded_by')->nullable()->after('graded_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sunnah_post_follows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('graded_by');
            $table->dropColumn(['score', 'feedback', 'graded_at']);
        });
        Schema::table('sunnah_posts', fn (Blueprint $table) => $table->dropColumn('max_score'));
        Schema::dropIfExists('sunnah_attendance');
        Schema::dropIfExists('sunnah_sessions');
    }
};
