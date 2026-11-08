<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قسم السنة: instructions and homework the course's sheikh gives, and the parent's follow-up of each
 * one for each child (seen, and homework done).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunnah_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sunnah_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            // instruction | homework
            $table->string('type', 20)->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->date('due_on')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sunnah_post_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sunnah_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('seen_at')->nullable();
            // Homework only: the parent confirms the child did it.
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->unique(['sunnah_post_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunnah_post_follows');
        Schema::dropIfExists('sunnah_posts');
    }
};
