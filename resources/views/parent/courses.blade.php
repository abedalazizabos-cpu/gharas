@extends('layouts.app')
@section('title', 'دورات '.$student->full_name)
@section('content')
<x-page-header :title="'دورات قسم السنة: '.$student->full_name" :crumbs="['أبنائي' => route('parent.dashboard'), $student->full_name => route('parent.children.show', $student), 'الدورات']" />

@if ($follow['total'])
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-stat-card icon="journal-bookmark" label="دورات ابنك" :value="$sunnahCourses->count()" /></div>
        <div class="col-6 col-xl-3"><x-stat-card icon="megaphone" label="تعليمات وواجبات الشيخ" :value="$follow['total']" color="blue" /></div>
        <div class="col-6 col-xl-3"><x-stat-card icon="hourglass-split" label="واجبات لم يُؤكَّد إنجازها" :value="$follow['pending']" color="gold" /></div>
        <div class="col-6 col-xl-3"><x-stat-card icon="eye" label="نسبة متابعتك" :value="$follow['percent'] !== null ? $follow['percent'].'%' : '—'" /></div>
    </div>
@endif

@forelse ($sunnahCourses as $e)
    @php($c = $e->course)
    @php($posts = $follow['posts']->where('sunnah_course_id', $c->id))
    @php($showResult = $e->isGraded() && $c->results_to_parents)
    <div class="card mb-3" id="course-{{ $c->id }}">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-semibold"><i class="bi bi-{{ $c->categoryIcon() }} ms-1" style="color:#b8892b"></i>{{ $c->title }} <span class="badge text-bg-{{ $c->statusColor() }}">{{ $c->statusLabel() }}</span></span>
            @if ($showResult)
                <span><span class="badge text-bg-{{ $e->statusColor() }}">{{ $e->statusLabel() }}</span> <strong>{{ $e->percentLabel() }}</strong> <small class="text-muted">{{ $e->gradeLabel() }}</small></span>
            @else
                <span class="badge text-bg-light border">{{ $e->isGraded() ? 'رُصدت النتيجة' : 'لم يُقيَّم بعد' }}</span>
            @endif
            @if ($e->certificate)<a href="{{ route('certificates.download', $e->certificate) }}" class="btn btn-sm btn-success" data-no-loading><i class="bi bi-award-fill"></i> شهادة الدورة PDF</a>@endif
        </div>
        <div class="card-body small">
            <div class="text-muted mb-2">{{ $c->categoryLabel() }} · {{ $c->teacher?->name ?? '—' }} · {{ $c->periodLabel() }}@if ($c->schedule) · {{ $c->schedule }}@endif @if ($c->location) · {{ $c->location }}@endif
                @if ($c->teacher && auth()->user()->hasPermission('messages.send'))<a href="{{ route('messages.create', ['to' => $c->teacher_id, 'student' => $student->id, 'subject' => 'دورة «'.$c->title.'»']) }}" class="btn btn-sm btn-outline-secondary me-2 py-0"><i class="bi bi-chat-dots"></i> مراسلة شيخ الدورة</a>@endif
            </div>
            @php($att = $attendance[$c->id] ?? null)
            @if ($att && $att['total'])
                <div class="mb-2">
                    <span class="fw-bold"><i class="bi bi-calendar-check text-success"></i> الحضور:</span> {{ $att['rate'] }}% من {{ $att['total'] }} لقاء
                    · <span class="text-success">حاضر {{ $att['present'] }}</span> · <span class="text-warning-emphasis">متأخر {{ $att['late'] }}</span> · <span class="text-danger">غائب {{ $att['absent'] }}</span> · <span class="text-info-emphasis">بعذر {{ $att['excused'] }}</span>
                    <details class="mt-1"><summary class="text-success">تفاصيل اللقاءات</summary>
                        <ul class="list-unstyled mt-1 mb-0">@foreach ($att['rows'] as $r)<li><span class="badge text-bg-{{ $r->badge() }}">{{ $r->statusLabel() }}</span> {{ $r->session->held_on->format('Y/m/d') }}@if ($r->session->topic) · {{ $r->session->topic }}@endif @if ($r->note)<span class="text-muted">({{ $r->note }})</span>@endif</li>@endforeach</ul>
                    </details>
                </div>
            @endif
            @if ($c->objectiveList())
                <div class="fw-bold mb-1">أهداف الدورة</div>
                <ul class="mb-2">@foreach ($c->objectiveList() as $o)<li>{{ $o }}</li>@endforeach</ul>
            @endif
            @if ($showResult && $e->scores->isNotEmpty())
                @php($names = $c->criteria->keyBy('id'))
                <div>@foreach ($e->scores as $sc)<span class="badge bg-green-soft text-success border me-1">{{ $names[$sc->sunnah_criterion_id]->name ?? 'ضابط' }}: {{ $sc->score }} / {{ $sc->max_score }}</span>@endforeach</div>
                @if ($e->notes)<div class="text-muted mt-1"><i class="bi bi-chat-left-text"></i> ملاحظة الشيخ: {{ $e->notes }}</div>@endif
            @endif
        </div>
        <div class="card-header border-top bg-transparent small fw-bold"><i class="bi bi-megaphone text-success ms-1"></i> تعليمات الشيخ وواجباته ({{ $posts->count() }})</div>
        <ul class="list-group list-group-flush">
            @forelse ($posts as $post)
                @php($done = (bool) $post->follow?->done_at)
                <li class="list-group-item" id="post-{{ $post->id }}">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <span class="badge {{ $post->isHomework() ? 'text-bg-warning' : 'text-bg-info' }}"><i class="bi bi-{{ $post->typeIcon() }}"></i> {{ $post->typeLabel() }}</span>
                            <strong>{{ $post->title }}</strong>
                            <div class="small text-muted">{{ $post->created_at->format('Y/m/d') }}@if ($post->due_on) · <span class="{{ $post->isOverdue() && ! $done ? 'text-danger fw-semibold' : '' }}">التسليم {{ $post->due_on->format('Y/m/d') }}</span>@endif</div>
                            @if ($post->body)<div class="mt-1">{{ $post->body }}</div>@endif
                            @if ($post->isHomework() && $post->follow?->score !== null)
                                <div class="mt-1"><span class="badge text-bg-success"><i class="bi bi-clipboard-check"></i> تقييم الشيخ: {{ $post->follow->score }} / {{ $post->max_score ?: 10 }}</span>@if ($post->follow->feedback) <span class="small text-muted">{{ $post->follow->feedback }}</span>@endif</div>
                            @endif
                        </div>
                        @if ($post->isHomework())
                            <form method="POST" action="{{ route('parent.children.homework', [$student, $post]) }}" class="align-self-center">@csrf
                                <input type="hidden" name="done" value="{{ $done ? 0 : 1 }}">
                                @if ($done)
                                    <button class="btn btn-sm btn-success" title="اضغط لإلغاء التأكيد"><i class="bi bi-check2-square"></i> أنجز ابني الواجب</button>
                                @else
                                    <button class="btn btn-sm btn-outline-success"><i class="bi bi-square"></i> تأكيد إنجاز الواجب</button>
                                @endif
                            </form>
                        @endif
                    </div>
                </li>
            @empty
                <li class="list-group-item small text-muted">لم يضع الشيخ تعليمات أو واجبات بعد.</li>
            @endforelse
        </ul>
    </div>
@empty
    <div class="card"><x-empty icon="journal-bookmark" message="ابنك غير مسجل في أي دورة من دورات قسم السنة حالياً." /></div>
@endforelse
@endsection
