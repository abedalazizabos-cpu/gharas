<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RecordsMemorization;
use App\Http\Controllers\Controller;
use App\Http\Requests\MemorizationRecordRequest;
use App\Models\Circle;
use App\Models\MemorizationRecord;
use App\Models\Surah;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class MemorizationController extends Controller
{
    use RecordsMemorization;

    public function index(Request $request)
    {
        $records = MemorizationRecord::query()
            ->with(['student', 'circle', 'teacher', 'surah'])
            ->when($request->query('q'), fn ($q, $t) => $q->whereHas('student', fn ($s) => $s->search($t)))
            ->when($request->query('circle'), fn ($q, $c) => $q->where('circle_id', $c))
            ->when($request->query('teacher'), fn ($q, $t) => $q->where('teacher_id', $t))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('recorded_on', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('recorded_on', '<=', $d))
            ->latest('recorded_on')->latest('id')
            ->paginate(20)->withQueryString();

        return view('admin.memorization.index', [
            'records' => $records,
            'circles' => Circle::orderBy('name')->get(['id', 'name']),
            'teachers' => User::role(User::ROLE_TEACHER)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(MemorizationRecord $record)
    {
        return view('shared.memorization-form', [
            'record' => $record->load('student'),
            'students' => collect([$record->student]),
            'surahs' => Surah::orderBy('id')->get(),
            'action' => route('admin.memorization.update', $record),
            'back' => route('admin.memorization.index'),
        ]);
    }

    public function update(MemorizationRecordRequest $request, MemorizationRecord $record)
    {
        $this->updateRecord($record, $request->validated());

        return redirect()->route('admin.memorization.index')->with('success', 'تم تحديث سجل التسميع.');
    }

    public function destroy(MemorizationRecord $record)
    {
        $this->authorize('delete', $record);
        $record->delete();
        AuditLogger::log('memorization.delete', "حذف سجل تسميع للطالب {$record->student->full_name}", $record, $record->only(['surah_id', 'from_ayah', 'to_ayah', 'score']));

        return back()->with('success', 'تم حذف سجل التسميع.');
    }
}
