<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extra data for teacher accounts (one-to-one with users).
        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('qualification')->nullable();
            $table->string('ijazah')->nullable();
            $table->date('hire_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Extra data for parent accounts (one-to-one with users).
        Schema::create('parent_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('national_id', 30)->nullable();
            $table->string('alt_phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_profiles');
        Schema::dropIfExists('teacher_profiles');
    }
};
