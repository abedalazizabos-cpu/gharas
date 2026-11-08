{{-- Quality committee summary on a student's page. Needs $quality (QualityService::forStudent); $examiners when the admin may assign. --}}
@php($canAssign = isset($examiners))
<div class="card mb-3" id="quality">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-patch-check text-success ms-1"></i> لجنة الجودة</span>
        <small class="text-muted">محفوظه: {{ \App\Support\Juz::count($quality['memorized_count']) }} · {{ $quality['current_juz'] ? 'يحفظ الآن '.\App\Support\Juz::label($quality['current_juz']) : 'أتم المصحف' }}</small>
    </div>
    <div class="card-body">
        @if ($quality['missing_individual'])
            <div class="alert alert-danger py-2 small mb-3"><i class="bi bi-exclamation-triangle-fill"></i>
                انتقل إلى الجزء التالي ولم يُختبر في: {{ collect(array_keys($quality['missing_individual']))->map(fn ($j) => \App\Support\Juz::label($j))->join('، ') }}</div>
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <h6 class="fw-bold small">الاختبارات المنفردة</h6>
                @forelse ($quality['passed_individual'] as $t)
                    <span class="badge text-bg-success mb-1" title="{{ $t->tested_on?->format('Y/m/d') }} · {{ $t->examiner?->name }}"><i class="bi bi-check2"></i> {{ \App\Support\Juz::label($t->juz) }} · {{ $t->gradeLabel() }}</span>
                @empty
                    <div class="small text-muted">لا توجد بعد.</div>
                @endforelse
            </div>
            <div class="col-md-6">
                <h6 class="fw-bold small">الاختبارات المجتمعة</h6>
                @forelse ($quality['passed_combined'] as $t)
                    <span class="badge text-bg-primary mb-1" title="{{ $t->tested_on?->format('Y/m/d') }} · {{ $t->examiner?->name }}"><i class="bi bi-check2-all"></i> {{ \App\Support\Juz::rangeLabel($t->from_juz, $t->to_juz) }} · {{ $t->gradeLabel() }}</span>
                @empty
                    <div class="small text-muted">لا توجد بعد.</div>
                @endforelse
                @if ($quality['due_combined'])
                    <div class="small mt-1 text-warning-emphasis">مستحق الآن: {{ collect($quality['due_combined'])->map(fn ($d) => $d['stage']->label())->join('، ') }}</div>
                @endif
            </div>
        </div>

        @php($open = $quality['tests']->whereIn('status', ['pending', 'failed']))
        @if ($open->isNotEmpty())
            <h6 class="fw-bold small mt-3">قيد الاختبار أو لم يجتز</h6>
            <ul class="list-unstyled small mb-0">
                @foreach ($open as $t)
                    <li class="mb-1"><span class="badge text-bg-{{ $t->statusColor() }}">{{ $t->statusLabel() }}</span> {{ $t->kindLabel() }}: {{ $t->subjectLabel() }} · {{ $t->examiner?->name ?? '—' }}
                        @if ($t->percentage !== null) · {{ rtrim(rtrim(number_format($t->percentage, 2), '0'), '.') }}% @endif
                        @if ($t->notes)<div class="text-muted">{{ $t->notes }}</div>@endif</li>
                @endforeach
            </ul>
        @endif

        @if ($canAssign)
            <hr>
            <h6 class="fw-bold small">إرسال الطالب إلى شيخ الجودة</h6>
            @if ($examiners->isEmpty())
                <a href="{{ route('admin.quality.settings') }}" class="small">اختر مشايخ لجنة الجودة أولاً</a>
            @else
            <form method="POST" action="{{ route('admin.quality.assign') }}" class="row g-2 align-items-end">
                @csrf
                <input type="hidden" name="student_id" value="{{ $student->id }}">
                <div class="col-md-3"><label class="form-label small">النوع</label>
                    <select name="kind" class="form-select form-select-sm" onchange="this.form.querySelector('[data-k=individual]').classList.toggle('d-none', this.value!=='individual');this.form.querySelector('[data-k=combined]').classList.toggle('d-none', this.value!=='combined')">
                        <option value="individual">اختبار منفرد (جزء)</option><option value="combined">اختبار مجتمع</option></select></div>
                <div class="col-md-3" data-k="individual"><label class="form-label small">الجزء</label>
                    <select name="juz" class="form-select form-select-sm">@foreach (\App\Support\Juz::options() as $j => $l)<option value="{{ $j }}" @selected($j === (array_key_first($quality['missing_individual']) ?? min(30, $quality['current_juz'] + 1)))>{{ $l }}</option>@endforeach</select></div>
                <div class="col-md-3 d-none" data-k="combined"><label class="form-label small">المرحلة</label>
                    <select name="stage_id" class="form-select form-select-sm">@foreach (\App\Models\CombinedTestStage::active()->get() as $st)<option value="{{ $st->id }}">{{ $st->label() }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label small">شيخ الجودة</label>
                    <select name="examiner_id" class="form-select form-select-sm" required><option value="">— اختر —</option>@foreach ($examiners as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-send"></i> إرسال</button></div>
            </form>
            @endif
        @endif
    </div>
</div>
