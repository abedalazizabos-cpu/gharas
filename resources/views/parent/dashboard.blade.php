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
                        <div class="col-4"><div class="bg-light rounded p-2"><div class="fw-bold fs-5">{{ $card['memorized_ayahs'] }}</div><small class="text-muted">آية محفوظة</small></div></div>
                        <div class="col-4"><div class="bg-light rounded p-2"><div class="fw-bold fs-5 {{ $card['absences'] ? 'text-danger' : '' }}">{{ $card['absences'] }}</div><small class="text-muted">غياب (30 يوماً)</small></div></div>
                    </div>
                    <div class="small mb-1 d-flex justify-content-between"><span>التقدم في حفظ القرآن</span><span>{{ $card['quran_percent'] }}%</span></div>
                    <div class="progress mb-3"><div class="progress-bar" style="width: {{ max($card['quran_percent'], 0.5) }}%"></div></div>
                    @if (isset($follow['children'][$s->id]) && $follow['children'][$s->id]['percent'] !== null)
                        <div class="small mb-2 d-flex justify-content-between"><span>متابعتك لسجلاته اليومية</span>@include('shared._follow-rate', ['rate' => $follow['children'][$s->id]])</div>
                    @endif
                    @if ($card['last_record'])
                        <div class="small bg-green-soft rounded p-2"><strong>آخر تسميع:</strong> {{ $card['last_record']->typeLabel() }} - {{ $card['last_record']->rangeLabel() }} · <span class="text-success">{{ $card['last_record']->gradeLabel() }}</span> <span class="text-muted">({{ $card['last_record']->recorded_on->format('Y/m/d') }})</span></div>
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
