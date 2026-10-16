<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            // Teacher accounts are never hard-deleted while they own circles.
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('level', 50)->nullable();
            $table->string('age_group', 50)->nullable();
            // Weekday numbers (0 = Sunday ... 6 = Saturday) stored as JSON array.
            $table->json('days');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('capacity')->default(15);
            $table->string('location')->nullable();
            // active | inactive
            $table->string('status', 20)->default('active')->index();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circles');
    }
};
