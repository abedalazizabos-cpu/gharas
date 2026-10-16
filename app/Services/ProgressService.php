<?php

namespace App\Services;

use App\Models\MemorizationRecord;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Surah;
use Illuminate\Support\Collection;

/**
 * Computes memorization progress. An ayah counts as memorized only when a
 * "new" record covering it was graded at or above the pass score.
 */
class ProgressService
{
    public function passScore(): int
    {
        return (int) Setting::get('pass_score', '70');
    }

    /** @return Collection<int, array{surah: Surah, memorized: int, revised: int, percent: float}> */
    public function surahProgress(Student $student): Collection
    {
        $records = $student->memorizationRecords()->get(['surah_id', 'type', 'from_ayah', 'to_ayah', 'score']);
        $pass = $this->passScore();
        $surahs = Surah::whereIn('id', $records->pluck('surah_id')->unique())->get()->keyBy('id');

        return $records->groupBy('surah_id')->map(function (Collection $rows, $surahId) use ($surahs, $pass) {
            $surah = $surahs[$surahId];
            $memorized = $this->uniqueAyahs($rows->where('type', 'new')->where('score', '>=', $pass), $surah->ayah_count);
            $revised = $this->uniqueAyahs($rows->whereIn('type', ['revision', 'consolidation']), $surah->ayah_count);

            return [
                'surah' => $surah,
                'memorized' => $memorized,
                'revised' => $revised,
                'percent' => round($memorized / $surah->ayah_count * 100, 1),
            ];
        })->sortBy(fn ($row) => $row['surah']->id)->values();
    }

    public function totalMemorizedAyahs(Student $student): int
    {
        return (int) $this->surahProgress($student)->sum('memorized');
    }

    /** Percentage of the whole Quran (6236 ayahs). */
    public function quranPercent(Student $student): float
    {
        return round($this->totalMemorizedAyahs($student) / 6236 * 100, 2);
    }

    private function uniqueAyahs(Collection $rows, int $max): int
    {
        $covered = [];
        foreach ($rows as $row) {
            for ($a = max(1, $row->from_ayah); $a <= min($max, $row->to_ayah); $a++) {
                $covered[$a] = true;
            }
        }

        return count($covered);
    }

    /** Ayahs memorized (new, passing) per student for a list of records in a period. */
    public static function newAyahsIn(Collection $records, int $pass): int
    {
        return (int) $records->where('type', 'new')->where('score', '>=', $pass)
            ->sum(fn (MemorizationRecord $r) => $r->ayahCount());
    }
}
