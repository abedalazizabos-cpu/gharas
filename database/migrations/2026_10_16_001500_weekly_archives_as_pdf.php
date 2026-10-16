<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Weekly files are now PDF: drop the old CSV copies; they are rebuilt as PDF the next time the archive page opens. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('weekly_archives')->where('path', 'like', '%.csv')->orderBy('id')->each(function ($row) {
            Storage::disk('local')->delete($row->path);
            DB::table('weekly_archives')->where('id', $row->id)->delete();
        });
    }

    public function down(): void {}
};
