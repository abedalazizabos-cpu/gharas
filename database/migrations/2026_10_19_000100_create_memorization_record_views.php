<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** One row per parent per daily record they have opened (their child's page). */
    public function up(): void
    {
        Schema::create('memorization_record_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorization_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('seen_at');
            $table->unique(['memorization_record_id', 'parent_id'], 'record_views_record_parent_unique');
            $table->index('parent_id');
        });

        // Records saved before this point were never tracked, so they are left out of follow-up rates.
        Setting::put('parent_views_since', now()->toDateTimeString());
    }

    public function down(): void
    {
        Schema::dropIfExists('memorization_record_views');
        Setting::query()->where('key', 'parent_views_since')->delete();
    }
};
