<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** لوحة الشرف: one student per circle for each month, shown on the login screen during the next month. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('honor_board', function (Blueprint $table) {
            $table->id();
            // The month the student was chosen for (Y-m); it is shown during the month after it.
            $table->string('month', 7)->index();
            $table->foreignId('circle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('points', 6, 2)->default(0);
            // attendance %, pages of new memorization, average recitation score
            $table->json('details')->nullable();
            // Set when the admin replaced the automatic choice.
            $table->foreignId('picked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['month', 'circle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('honor_board');
    }
};
