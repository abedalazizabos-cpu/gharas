<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Days a circle did not meet (the teacher was ill, a public holiday …). Such a day is not counted
 * as a day the teacher forgot to record attendance and recitation. circle_id null = the whole center.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_day_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circle_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('off_on')->index();
            $table->string('reason', 150);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['circle_id', 'off_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_day_offs');
    }
};
