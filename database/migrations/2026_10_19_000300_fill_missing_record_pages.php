<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Memorization is now measured in pages, so every record needs one. Older
     * records without pages get an estimate from their ayah range, rounded to a
     * quarter page: about 24 ayahs a page in Juz Amma (surah 78 on), 10 elsewhere.
     */
    public function up(): void
    {
        DB::table('memorization_records')->whereNull('pages')->orderBy('id')
            ->chunkById(500, function ($records) {
                foreach ($records as $r) {
                    $perPage = $r->surah_id >= 78 ? 24 : 10;
                    $pages = max(0.25, round(($r->to_ayah - $r->from_ayah + 1) / $perPage * 4) / 4);
                    DB::table('memorization_records')->where('id', $r->id)->update(['pages' => $pages]);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
