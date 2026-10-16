<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A circle's time can be a clock range, "after <prayer>", or free text
 * (e.g. «الضحى»). Clock times are therefore optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->string('time_type', 10)->default('clock')->after('days');
            $table->string('prayer', 10)->nullable()->after('time_type');
            $table->string('time_note', 100)->nullable()->after('prayer');
            $table->time('start_time')->nullable()->change();
            $table->time('end_time')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->dropColumn(['time_type', 'prayer', 'time_note']);
        });
    }
};
