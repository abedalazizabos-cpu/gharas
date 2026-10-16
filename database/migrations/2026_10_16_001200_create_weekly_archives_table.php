<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circle_id')->constrained('circles')->cascadeOnDelete();
            $table->date('week_start');
            $table->string('path');
            $table->unsignedInteger('records_count')->default(0);
            $table->unsignedInteger('attendance_count')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->unique(['circle_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_archives');
    }
};
