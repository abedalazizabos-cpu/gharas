<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('circle_id')->nullable()->constrained('circles')->nullOnDelete();
            $table->string('level', 10);
            $table->text('reason');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->index(['teacher_id', 'acknowledged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_warnings');
    }
};
