<?php

namespace App\Http\Controllers;

use App\Models\Circle;
use App\Models\User;
use App\Models\WeeklyArchive;
use App\Services\WeeklyArchiveService;
use App\Support\PdfExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/** Weekly view and the archive of past weeks: admins and visitors see all circles (visitors with shortened names), teachers their own. */
class ArchiveController extends Controller
{
    public function __construct(private WeeklyArchiveService $weeks) {}

    public function week(Request $request)
    {
        $circles = $this->circles($request->user());
        $circle = $circles->firstWhere('id', (int) $request->query('circle')) ?? $circles->first();
        $start = WeeklyArchiveService::weekStart($request->query('week'));

        return view('shared.week', [
            'circles' => $circles,
            'circle' => $circle,
            'w' => $circle ? $this->weekFor($request->user(), $start, $circle) : null,
            'start' => $start,
            'prefix' => $this->prefix($request->user()),
            'archive' => $circle ? WeeklyArchive::where('circle_id', $circle->id)->where('week_start', $start->toDateString())->first() : null,
        ]);
    }

    public function index(Request $request)
    {
        $this->weeks->archiveMissing();
        $circles = $this->circles($request->user());
        $archives = WeeklyArchive::with('circle.teacher')
            ->whereIn('circle_id', $circles->pluck('id'))
            ->when($request->query('circle'), fn ($q, $c) => $q->where('circle_id', $c))
            ->orderByDesc('week_start')->get();

        return view('shared.archive', [
            'circles' => $circles,
            'months' => $archives->groupBy(fn ($a) => $a->week_start->format('Y-m'))
                ->map(fn ($m) => $m->groupBy(fn ($a) => $a->week_start->toDateString())),
            'prefix' => $this->prefix($request->user()),
        ]);
    }

    public function download(Request $request, WeeklyArchive $archive)
    {
        abort_unless($this->circles($request->user())->contains('id', $archive->circle_id), 403);
        if ($request->user()->isVisitor()) {
            $circle = $archive->circle()->withTrashed()->first();

            return PdfExport::response($this->weeks->maskedPdf($circle, Carbon::parse($archive->week_start)), $archive->downloadName());
        }
        // Rebuild the file if it was removed from disk or was saved in the older CSV format.
        if (! str_ends_with($archive->path, '.pdf') || ! Storage::disk('local')->exists($archive->path)) {
            $archive = $this->weeks->archive($archive->circle, Carbon::parse($archive->week_start));
        }

        return PdfExport::response(Storage::disk('local')->get($archive->path), $archive->downloadName());
    }

    private function circles(User $user)
    {
        return ($user->isAdmin() || $user->isVisitor() ? Circle::query() : $user->circles())->with('teacher')->orderBy('name')->get();
    }

    private function prefix(User $user): string
    {
        return $user->isAdmin() ? 'admin' : ($user->isVisitor() ? 'visitor' : 'teacher');
    }

    private function weekFor(User $user, Carbon $start, Circle $circle): array
    {
        $w = $this->weeks->week($start, $circle);

        return $user->isVisitor() ? $this->weeks->mask($w) : $w;
    }
}
