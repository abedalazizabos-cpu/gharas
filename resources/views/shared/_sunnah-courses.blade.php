{{-- Courses of قسم السنة for one student. $sunnahCourses: enrollments; $forParent hides results the admin chose not to show. --}}
@php($forParent = $forParent ?? false)
@if (isset($sunnahCourses) && $sunnahCourses->isNotEmpty())
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-journal-bookmark-fill ms-1" style="color:#b8892b"></i> دورات قسم السنة</span>
        @if ($forParent && ! request()->routeIs('parent.children.courses') && isset($student))<a href="{{ route('parent.children.courses', $student) }}" class="small">التعليمات والواجبات</a>@endif</div>
    <ul class="list-group list-group-flush">
        @foreach ($sunnahCourses as $e)
            @php($c = $e->course)
            @php($showResult = $e->isGraded() && (! $forParent || $c->results_to_parents))
            <li class="list-group-item">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold"><i class="bi bi-{{ $c->categoryIcon() }} text-success ms-1"></i>{{ $c->title }} <span class="badge text-bg-{{ $c->statusColor() }}">{{ $c->statusLabel() }}</span></div>
                        <div class="small text-muted">{{ $c->categoryLabel() }} · {{ $c->teacher?->name ?? '—' }} · {{ $c->periodLabel() }}@if ($c->schedule) · {{ $c->schedule }}@endif</div>
                    </div>
                    <div class="text-nowrap">
                        @if ($showResult)
                            <span class="badge text-bg-{{ $e->statusColor() }}">{{ $e->statusLabel() }}</span> <strong>{{ $e->percentLabel() }}</strong> <small class="text-muted">{{ $e->gradeLabel() }}</small>
                        @else
                            <span class="badge text-bg-light border">{{ $e->isGraded() ? 'رُصدت النتيجة' : 'لم يُقيَّم بعد' }}</span>
                        @endif
                    </div>
                </div>
                @if ($c->objectiveList())
                    <details class="small mt-1"><summary class="text-success">أهداف الدورة</summary>
                        <ul class="mb-0 mt-1">@foreach ($c->objectiveList() as $o)<li>{{ $o }}</li>@endforeach</ul>
                    </details>
                @endif
                @if ($showResult && $e->scores->isNotEmpty())
                    @php($names = $c->criteria->keyBy('id'))
                    <div class="small mt-1">@foreach ($e->scores as $sc)<span class="badge bg-green-soft text-success border me-1">{{ $names[$sc->sunnah_criterion_id]->name ?? 'ضابط' }}: {{ $sc->score }} / {{ $sc->max_score }}</span>@endforeach</div>
                    @if ($e->notes)<div class="small text-muted mt-1"><i class="bi bi-chat-left-text"></i> {{ $e->notes }}</div>@endif
                @endif
            </li>
        @endforeach
    </ul>
</div>
@endif
