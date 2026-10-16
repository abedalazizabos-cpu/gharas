<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 20)->unique();
            $table->string('full_name')->index();
            // male | female
            $table->string('gender', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('phone', 30)->nullable();
            $table->date('enrollment_date');
            // active | suspended | withdrawn
            $table->string('status', 20)->default('active')->index();
            $table->date('withdrawn_at')->nullable();
            $table->foreignId('current_circle_id')->nullable()->constrained('circles')->nullOnDelete();
            // Internal admin notes, never shown to parents.
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('parent_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            // father | mother | guardian ...
            $table->string('relationship', 20)->default('father');
            $table->timestamps();
            $table->unique(['parent_id', 'student_id']);
        });

        // Current and historical circle memberships.
        Schema::create('circle_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('circle_id')->constrained()->cascadeOnDelete();
            $table->date('joined_at');
            $table->date('left_at')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['circle_id', 'left_at']);
            $table->index(['student_id', 'left_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_enrollments');
        Schema::dropIfExists('parent_student');
        Schema::dropIfExists('students');
    }
};
