<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The study week now runs Saturday to Thursday (it used to run Sunday to Saturday). Saved week
 * files were cut on the old days, so they are removed and rebuilt on the new days: the archive
 * page fills itself again the next time it opens, and each PDF is built on first download.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('weekly_archives')->delete();
        Storage::disk('local')->deleteDirectory('archives');
    }

    public function down(): void
    {
        // Nothing to restore: the files are rebuilt from the records.
    }
};
