{{-- Inline form: send a student to a quality examiner. Needs $student, $examiners and either $juz or $stage. --}}
@if ($examiners->isEmpty())
    <a href="{{ route('admin.quality.settings') }}" class="small">اختر مشايخ لجنة الجودة أولاً</a>
@else
<form method="POST" action="{{ route('admin.quality.assign') }}" class="d-flex gap-1 align-items-center">
    @csrf
    <input type="hidden" name="student_id" value="{{ $student->id }}">
    @if (isset($stage))
        <input type="hidden" name="kind" value="combined"><input type="hidden" name="stage_id" value="{{ $stage->id }}">
    @else
        <input type="hidden" name="kind" value="individual"><input type="hidden" name="juz" value="{{ $juz }}">
    @endif
    <select name="examiner_id" class="form-select form-select-sm" style="min-width:140px" required aria-label="شيخ الجودة">
        <option value="">— شيخ الجودة —</option>
        @foreach ($examiners as $e)<option value="{{ $e->id }}" @selected(($selectedExaminer ?? null) == $e->id)>{{ $e->name }}</option>@endforeach
    </select>
    <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-send"></i> {{ ($selectedExaminer ?? null) ? 'تغيير' : 'إرسال' }}</button>
</form>
@endif
