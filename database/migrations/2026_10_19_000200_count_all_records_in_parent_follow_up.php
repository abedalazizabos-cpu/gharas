<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Follow-up now covers every shared record, including those saved before view tracking existed. */
    public function up(): void
    {
        DB::table('settings')->where('key', 'parent_views_since')->delete();
        cache()->forget('settings.all');
    }

    public function down(): void
    {
        //
    }
};
