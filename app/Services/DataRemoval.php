<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\Circle;
use App\Models\CircleSession;
use App\Models\MemorizationRecord;
use App\Models\Student;
use App\Models\User;
use App\Models\WeeklyArchive;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Super admin removal of circles with everything in them, and of the whole trial data.
 *
 * Everything is soft deleted with one shared timestamp. That timestamp is the
 * «deletion batch» shown in the trash: restoring or purging a batch acts on
 * every row that carries it.
 */
class DataRemoval
{
    /** Soft-deletable tables in the order a purge must run (children before parents). */
    private const MODELS = [
        MemorizationRecord::class, Assessment::class, CircleSession::class,
        Student::class, Circle::class, Announcement::class, User::class,
    ];

    /** What removing this circle would take with it. */
    public function circleImpact(Circle $circle): array
    {
        $studentIds = $circle->students()->pluck('id');
        $teacher = $circle->teacher_id ? User::find($circle->teacher_id) : null;

        return [
            'students' => $studentIds->count(),
            'parents' => $this->orphanedParents($studentIds)->count(),
            'teacher' => $teacher,
            'teacher_removed' => $teacher && $this->teacherFreed($teacher, collect([$circle->id])),
            'records' => $this->records(MemorizationRecord::query(), $circle, $studentIds)->count(),
            'assessments' => $this->records(Assessment::query(), $circle, $studentIds)->count(),
            'sessions' => $circle->sessions()->count(),
        ];
    }

    /**
     * Soft deletes the circle, its students and their records and attendance, guardians left with no
     * other children, and the teacher when this was their only circle.
     */
    public function removeCircle(Circle $circle, ?Carbon $at = null): array
    {
        $stamp = ($at ?? now())->toDateTimeString();

        return DB::transaction(function () use ($circle, $stamp) {
            $studentIds = $circle->students()->pluck('id');
            $parentIds = $this->orphanedParents($studentIds)->pluck('id');
            $teacher = $circle->teacher_id ? User::find($circle->teacher_id) : null;
            $removeTeacher = $teacher && $this->teacherFreed($teacher, collect([$circle->id]));

            $counts = [
                'records' => $this->records(MemorizationRecord::query(), $circle, $studentIds)->update(['deleted_at' => $stamp]),
                'assessments' => $this->records(Assessment::query(), $circle, $studentIds)->update(['deleted_at' => $stamp]),
                'sessions' => $circle->sessions()->update(['deleted_at' => $stamp]),
                'students' => Student::whereIn('id', $studentIds)->update(['deleted_at' => $stamp]),
                'parents' => User::whereIn('id', $parentIds)->update(['deleted_at' => $stamp]),
                'teacher' => $removeTeacher ? User::whereKey($teacher->id)->update(['deleted_at' => $stamp]) : 0,
            ];
            Circle::whereKey($circle->id)->update(['deleted_at' => $stamp]);

            return $counts;
        });
    }

    /**
     * Clears the trial data: every circle with its contents, every account except the super admins,
     * students outside circles and announcements. Surahs, settings and permissions stay.
     */
    public function removeEverything(): array
    {
        $stamp = now()->toDateTimeString();

        return DB::transaction(function () use ($stamp) {
            Circle::query()->get()->each(fn (Circle $c) => $this->removeCircle($c, Carbon::parse($stamp)));
            foreach ([MemorizationRecord::class, Assessment::class, CircleSession::class, Student::class, Announcement::class] as $model) {
                $model::query()->update(['deleted_at' => $stamp]);
            }
            User::where('role', '!=', User::ROLE_SUPERADMIN)->update(['deleted_at' => $stamp]);

            return [
                'circles' => Circle::onlyTrashed()->where('deleted_at', $stamp)->count(),
                'students' => Student::onlyTrashed()->where('deleted_at', $stamp)->count(),
                'accounts' => User::onlyTrashed()->where('deleted_at', $stamp)->count(),
            ];
        });
    }

    /**
     * Deletion batches in the trash, newest first, with what each one holds.
     * Lone records deleted from daily work are left out; only circles, students and accounts open a batch.
     */
    public function batches(): Collection
    {
        $stamps = collect([Circle::class, Student::class])
            ->flatMap(fn ($model) => $model::onlyTrashed()->distinct()->pluck('deleted_at'))
            ->merge(User::onlyTrashed()->where('role', '!=', User::ROLE_SUPERADMIN)->distinct()->pluck('deleted_at'))
            ->map(fn ($at) => Carbon::parse($at)->toDateTimeString())
            ->unique()->sortDesc()->values();

        return $stamps->map(function (string $stamp) {
            $users = User::onlyTrashed()->where('deleted_at', $stamp)->get(['id', 'name', 'role']);

            return [
                'at' => $stamp,
                'circles' => Circle::onlyTrashed()->where('deleted_at', $stamp)->orderBy('name')->pluck('name'),
                'students' => Student::onlyTrashed()->where('deleted_at', $stamp)->count(),
                'records' => MemorizationRecord::onlyTrashed()->where('deleted_at', $stamp)->count()
                    + Assessment::onlyTrashed()->where('deleted_at', $stamp)->count(),
                'roles' => $users->countBy('role'),
                'names' => $users->where('role', '!=', User::ROLE_PARENT)->pluck('name'),
            ];
        });
    }

    public function restore(string $stamp): int
    {
        return DB::transaction(fn () => collect(self::MODELS)
            ->sum(fn ($model) => $model::onlyTrashed()->where('deleted_at', $stamp)->update(['deleted_at' => null])));
    }

    /** Deletes a batch for good (database cascades take attendance, links and messages with it). */
    public function purge(string $stamp): int
    {
        $circleIds = Circle::onlyTrashed()->where('deleted_at', $stamp)->pluck('id');
        WeeklyArchive::whereIn('circle_id', $circleIds)->whereNotNull('path')->pluck('path')
            ->each(fn ($path) => Storage::disk('local')->delete($path));

        return DB::transaction(fn () => collect(self::MODELS)
            ->sum(fn ($model) => $model::onlyTrashed()->where('deleted_at', $stamp)->forceDelete()));
    }

    private function records($query, Circle $circle, Collection $studentIds)
    {
        return $query->where(fn ($q) => $q->where('circle_id', $circle->id)->orWhereIn('student_id', $studentIds));
    }

    /** Guardians whose every remaining child is among these students. */
    private function orphanedParents(Collection $studentIds): Collection
    {
        if ($studentIds->isEmpty()) {
            return collect();
        }

        return User::role(User::ROLE_PARENT)
            ->whereHas('children', fn ($q) => $q->whereIn('students.id', $studentIds))
            ->whereDoesntHave('children', fn ($q) => $q->whereNotIn('students.id', $studentIds))
            ->get(['id']);
    }

    private function teacherFreed(User $teacher, Collection $circleIds): bool
    {
        return $teacher->role === User::ROLE_TEACHER
            && ! $teacher->circles()->whereNotIn('id', $circleIds)->exists();
    }
}
