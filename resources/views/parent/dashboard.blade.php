@extends('layouts.app')
@section('title', 'الرئيسية')
@section('content')
<x-page-header :title="'أهلاً بك، '.auth()->user()->name" />

@if ($notifications->isNotEmpty())
    <div class="card mb-3 border-warning">
        <div class="card-header bg-gold-soft"><i class="bi bi-bell-fill text-gold ms-1"></i> تنبيهات جديدة</div>
        <ul class="list-group list-group-flush small">
            @foreach ($notifications as $n)
                <li class="list-group-item">
                    <form method="POST" action="{{ route('notifications.read', $n->id) }}" data-no-loading>@csrf
                        <button class="btn btn-link p-0 text-reset text-decoration-none text-start"><strong>{{ $n->data['title'] }}:</strong> {{ $n->data['body'] }} <small class="text-muted">· {{ $n->created_at->diffForHumans() }}</small></button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
@endif

@if ($follow['overall']['percent'] !== null)
    <div class="card mb-3"><div class="card-body">
        <h6 class="fw-bold mb-2"><i class="bi bi-eye ms-1 text-success"></i> نسبة متابعتك لأبنائك</h6>
        @include('shared._follow-rate', ['rate' => $follow['overall'], 'bar' => true])
        <p class="small text-muted mt-2 mb-0">افتح صفحة كل ابن لتطّلع على تسجيله اليومي، فيظهر للمحفظ أنك اطّلعت عليه.</p>
    </div></div>
@endif

<h2 class="h5 fw-bold mb-3">أبنائي</h2>
<div class="row g-3 mb-4">
    @forelse ($cards as $card)
        @php($s = $card['student'])
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="avatar bg-green-soft rounded-circle d-grid fs-4" style="width:54px;height:54px;place-items:center"><i class="bi bi-person-fill text-success"></i></span>
                        <div>
                            <h5 class="fw-bold mb-0">{{ $s->full_name }}</h5>
                            <small class="text-muted">{{ $s->circle?->name ?? 'غير مسجل في حلقة' }} @if ($s->circle?->teacher)· {{ $s->circle->teacher->name }}@endif</small>
                        </div>
                    </div>
                    @if ($s->status !== 'active')<div class="alert alert-warning py-1 small">حالة الطالب: {{ $s->statusLabel() }}</div>@endif
                    <div class="row text-center g-2 mb-3">
                        <div class="col-4"><div class="bg-light rounded p-2"><div class="fw-bold fs-5">{{ $card['attendance_rate'] !== null ? round($card['attendance_rate']).'%' : '—' }}</div><small class="text-muted">الحضور</small></div></div>
                        <div class="col-4"><div class="bg-light rounded p-2"><div class="fw-bold fs-5">{{ \App\Support\Pages::num($card['memorized_pages']) }}</div><small class="text-muted">صفحة محفوظة</small></div></div>
                        <div class="col-4"><div class="bg-light rounded p-2"><div class="fw-bold fs-5 {{ $card['absences'] ? 'text-danger' : '' }}">{{ $card['absences'] }}</div><small class="text-muted">غياب (30 يوماً)</small></div></div>
                    </div>
                    <div class="small mb-1 d-flex justify-content-between"><span>التقدم في حفظ القرآن</span><span>{{ $card['quran_percent'] }}%</span></div>
                    <div class="progress mb-3"><div class="progress-bar" style="width: {{ max($card['quran_percent'], 0.5) }}%"></div></div>
                    @if (isset($follow['children'][$s->id]) && $follow['children'][$s->id]['percent'] !== null)
                        <div class="small mb-2 d-flex justify-content-between"><span>متابعتك لسجلاته اليومية</span>@include('shared._follow-rate', ['rate' => $follow['children'][$s->id]])</div>
                    @endif
                    @php($q = $card['quality'])
                    @php($courses = $card['sunnah'])
                    @php($completed = $courses->filter(fn ($e) => $e->status === 'passed' && $e->course->results_to_parents))
                    <div class="row g-2 text-center mb-2 parent-tiles">
                        <div class="col-4"><a href="{{ route('parent.children.show', $s) }}#quality-individual" class="d-block rounded p-2 text-decoration-none h-100 border tile-individual">
                            <i class="bi bi-patch-check-fill fs-3 d-block" style="color:#1b6b50"></i>
                            <div class="fw-bold fs-5 text-body">{{ $q ? $q['passed_individual']->count() : 0 }}</div>
                            <small class="text-muted">الاختبارات المنفردة</small></a></div>
                        <div class="col-4"><a href="{{ route('parent.children.show', $s) }}#quality-combined" class="d-block rounded p-2 text-decoration-none h-100 border tile-combined">
                            <i class="bi bi-stack fs-3 d-block" style="color:#2f5fb3"></i>
                            <div class="fw-bold fs-5 text-body">{{ $q ? $q['passed_combined']->count() : 0 }}</div>
                            <small class="text-muted">الاختبارات المجتمعة</small></a></div>
                        <div class="col-4"><a href="{{ route('parent.children.courses', $s) }}" class="d-block rounded p-2 text-decoration-none h-100 border position-relative tile-courses">
                            <i class="bi bi-journal-bookmark-fill fs-3 d-block" style="color:#b8892b"></i>
                            <div class="fw-bold fs-5 text-body">{{ $courses->count() }}</div>
                            <small class="text-muted">الدورات</small>
                            @if ($card['follow']['unseen'] + $card['follow']['pending'])<span class="position-absolute top-0 start-0 translate-middle badge rounded-pill text-bg-danger" title="تعليمات جديدة أو واجبات لم يُؤكَّد إنجازها">{{ $card['follow']['unseen'] + $card['follow']['pending'] }}</span>@endif
                        </a></div>
                    </div>
                    @if ($card['certificates'])
                        <a href="{{ route('parent.children.show', $s) }}#certificates" class="d-flex justify-content-between align-items-center border rounded p-2 mb-2 small text-decoration-none parent-certificates">
                            <span class="text-body"><i class="bi bi-award-fill text-warning fs-5 align-middle"></i> <strong>{{ $card['certificates'] }}</strong> {{ $card['certificates'] > 2 && $card['certificates'] < 11 ? 'شهادات صادرة' : 'شهادة صادرة' }}</span>
                            <span>عرض وتحميل PDF <i class="bi bi-chevron-left"></i></span>
                        </a>
                    @endif
                    @if ($q)
                        <div class="border rounded p-2 mb-2 small">
                            <div class="fw-bold mb-1"><i class="bi bi-award text-success"></i> الاختبارات والدورات المنجزة <span class="text-muted fw-normal">· محفوظه {{ \App\Support\Juz::count($q['memorized_count']) }}</span></div>
                            <div class="mb-1"><i class="bi bi-patch-check-fill" style="color:#1b6b50"></i> <span class="text-muted">المنفردة:</span>
                                @forelse ($q['passed_individual'] as $t)
                                    <span class="badge text-bg-success" title="{{ $t->tested_on?->format('Y/m/d') }}">{{ \App\Support\Juz::label($t->juz) }} · {{ $t->gradeLabel() }}</span>
                                @empty <span class="text-muted">لا توجد بعد</span> @endforelse
                            </div>
                            <div class="mb-1"><i class="bi bi-stack" style="color:#2f5fb3"></i> <span class="text-muted">المجتمعة:</span>
                                @forelse ($q['passed_combined'] as $t)
                                    <span class="badge text-bg-primary" title="{{ $t->tested_on?->format('Y/m/d') }}">{{ $t->from_juz }} - {{ $t->to_juz }} · {{ $t->gradeLabel() }}</span>
                                @empty <span class="text-muted">لا توجد بعد</span> @endforelse
                            </div>
                            <div><i class="bi bi-journal-bookmark-fill" style="color:#b8892b"></i> <span class="text-muted">الدورات:</span>
                                @forelse ($completed as $e)
                                    <span class="badge text-bg-warning" title="{{ $e->evaluated_on?->format('Y/m/d') }}">{{ $e->course->title }} · {{ $e->gradeLabel() }}</span>
                                @empty <span class="text-muted">لا توجد بعد</span> @endforelse
                            </div>
                        </div>
                    @endif
                    @php($current = $courses->filter(fn ($e) => $e->course->status !== 'finished'))
                    @if ($current->isNotEmpty())
                        <div class="border rounded p-2 mb-2 small">
                            <div class="fw-bold mb-1"><i class="bi bi-journal-bookmark text-success"></i> دورات يدرسها الآن</div>
                            @foreach ($current as $e)
                                <div class="d-flex justify-content-between gap-2 {{ $loop->last ? '' : 'mb-1' }}">
                                    <span>{{ $e->course->title }} <span class="text-muted">· {{ $e->course->teacher?->name }}</span></span>
                                    <span class="badge text-bg-{{ $e->course->statusColor() }} align-self-start">{{ $e->course->statusLabel() }}</span>
                                </div>
                            @endforeach
                            @if ($card['follow']['pending'])<div class="text-danger mt-1"><i class="bi bi-pencil-square"></i> {{ $card['follow']['pending'] }} واجب لم تؤكد إنجازه · <a href="{{ route('parent.children.courses', $s) }}">عرض</a></div>@endif
                        </div>
                    @endif
                    @if ($card['last_record'])
                        <div class="small bg-green-soft rounded p-2"><strong>آخر تسميع:</strong> {{ $card['last_record']->typeLabel() }} - {{ $card['last_record']->rangeLabel() }} · {{ $card['last_record']->pagesLabel() }} · <span class="text-success">{{ $card['last_record']->gradeLabel() }}</span> <span class="text-muted">({{ $card['last_record']->recorded_on->format('Y/m/d') }})</span></div>
                    @endif
                </div>
                <div class="card-footer bg-transparent d-flex gap-2">
                    <a href="{{ route('parent.children.show', $s) }}" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-eye"></i> عرض التفاصيل</a>
                    @if ($s->circle?->teacher)
                        @if (auth()->user()->hasPermission('messages.send'))<a href="{{ route('messages.create', ['to' => $s->circle->teacher_id, 'student' => $s->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-chat-dots"></i> مراسلة المحفظ</a>@endif
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="card"><x-empty icon="people" message="لا يوجد أبناء مرتبطون بحسابك حالياً. يرجى التواصل مع إدارة المركز." /></div></div>
    @endforelse
</div>
<div class="row"><div class="col-lg-8">@include('shared._announcements-card', ['moreRoute' => 'parent.announcements'])</div></div>
@endsection
