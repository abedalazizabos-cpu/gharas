<?php

namespace App\Services;

use App\Models\MemorizationRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tracks which shared daily records each parent has seen, and how closely
 * each parent follows their children (seen ÷ shared over the last 30 days).
 */
class ParentFollowService
{
    public const WINDOW_DAYS = 30;

    /** Marks every shared record of the student as seen by this parent. Called when the parent opens the child's page. */
    public function markSeen(User $parent, Student $student): void
    {
        if (! $parent->isParent()) {
            return;
        }

        $ids = $student->memorizationRecords()->where('shared_with_parent', true)
            ->whereDoesntHave('parentViews', fn ($q) => $q->where('parent_id', $parent->id))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $now = now();
        DB::table('memorization_record_views')->insertOrIgnore(
            $ids->map(fn ($id) => ['memorization_record_id' => $id, 'parent_id' => $parent->id, 'seen_at' => $now])->all()
        );
    }

    /** Start of the counting window: the last 30 days. */
    public function windowStart(): Carbon
    {
        return now()->subDays(self::WINDOW_DAYS);
    }

    /**
     * Follow-up per parent and child: [parent_id => [student_id => ['seen' => n, 'total' => n]]].
     *
     * @param  iterable<int>  $parentIds
     */
    public function counts(iterable $parentIds): array
    {
        $ids = collect($parentIds)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $rows = DB::table('parent_student as ps')
            ->join('memorization_records as mr', 'mr.student_id', '=', 'ps.student_id')
            ->leftJoin('memorization_record_views as v', fn ($j) => $j->on('v.memorization_record_id', '=', 'mr.id')->on('v.parent_id', '=', 'ps.parent_id'))
            ->whereIn('ps.parent_id', $ids)
            ->where('mr.shared_with_parent', true)
            ->whereNull('mr.deleted_at')
            ->where('mr.created_at', '>=', $this->windowStart())
            ->groupBy('ps.parent_id', 'ps.student_id')
            ->select('ps.parent_id', 'ps.student_id', DB::raw('COUNT(mr.id) as total'), DB::raw('COUNT(v.id) as seen'))
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->parent_id][$r->student_id] = ['seen' => (int) $r->seen, 'total' => (int) $r->total];
        }

        return $out;
    }

    /** Overall follow-up per parent: [parent_id => ['seen', 'total', 'percent' (null when nothing was shared)]]. */
    public function rates(iterable $parentIds): array
    {
        $out = [];
        foreach ($this->counts($parentIds) as $parentId => $children) {
            $out[$parentId] = self::summarize(array_values($children));
        }

        return $out;
    }

    /** Overall and per-child follow-up for one parent. */
    public function forParent(User $parent): array
    {
        $children = $this->counts([$parent->id])[$parent->id] ?? [];

        return [
            'overall' => self::summarize(array_values($children)),
            'children' => array_map(fn ($c) => self::summarize([$c]), $children),
        ];
    }

    /** @param  array<int, array{seen:int,total:int}>  $parts */
    public static function summarize(array $parts): array
    {
        $seen = array_sum(array_column($parts, 'seen'));
        $total = array_sum(array_column($parts, 'total'));

        return ['seen' => $seen, 'total' => $total, 'percent' => $total > 0 ? (int) round($seen * 100 / $total) : null];
    }

    /** Badge colour for a follow-up percentage. */
    public static function color(?int $percent): string
    {
        return match (true) {
            $percent === null => 'secondary',
            $percent >= 80 => 'success',
            $percent >= 50 => 'warning',
            default => 'danger',
        };
    }
}
