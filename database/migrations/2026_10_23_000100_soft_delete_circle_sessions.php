<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessions (and so their attendance) are hidden with their circle when the super admin
 * removes a circle with everything in it, and come back if it is restored from the trash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('circle_sessions', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('circle_sessions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
