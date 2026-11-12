<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Requests to enroll a new student, sent by parents from the website and accepted or declined by the admin. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_requests', function (Blueprint $table) {
            $table->id();
            $table->string('student_name', 150);
            $table->string('gender', 10);
            $table->date('birth_date')->nullable();
            $table->unsignedTinyInteger('memorized_juz')->default(0);
            $table->string('guardian_name', 150);
            $table->string('guardian_phone', 20)->index();
            $table->string('relationship', 20)->default('father');
            $table->string('notes', 500)->nullable();
            // pending | accepted | rejected
            $table->string('status', 10)->default('pending')->index();
            $table->string('reject_reason', 255)->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_requests');
    }
};
