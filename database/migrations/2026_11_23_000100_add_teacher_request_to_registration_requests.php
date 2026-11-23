<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A teacher can ask to add a new student to his own circle; the request waits for the admin like a website registration. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->foreignId('circle_id')->nullable()->after('notes')->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->after('circle_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by');
            $table->dropConstrainedForeignId('circle_id');
        });
    }
};
