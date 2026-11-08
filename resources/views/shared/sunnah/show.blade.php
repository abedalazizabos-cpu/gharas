@extends('layouts.app')
@section('title', $course->title)
@section('content')
@php($isAdmin = $area === 'admin')
<x-page-header :title="$course->title" :crumbs="[($isAdmin ? 'قسم السنة' : 'دوراتي في قسم السنة') => route($area.'.sunnah.index'), $course->title]">
    <a href="{{ route($area.'.sunnah.grade', $course) }}" class="btn btn-primary"><i class="bi bi-pencil-square"></i> رصد الدرجات</a>
    <a href="{{ route($area.'.sunnah.show', [$course, 'export' => 'pdf']) }}" class="btn btn-outline-primary"><i class="bi bi-filetype-pdf"></i> تنزيل PDF</a>
    @if ($isAdmin)
        <a href="{{ route('admin.sunnah.edit', $course) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> تعديل الدورة والطلاب</a>
        <x-delete-form :action="route('admin.sunnah.destroy', $course)" label="حذف" class="btn btn-outline-danger" message="ستُحذف الدورة ونتائجها من القوائم." />
    @endif
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="feature-icon mb-0"><i class="bi bi-{{ $course->categoryIcon() }}"></i></span>
                <div><div class="fw-bold">{{ $course->categoryLabel() }}</div><span class="badge text-bg-{{ $course->statusColor() }}">{{ $course->statusLabel() }}</span></div>
            </div>
            <dl class="row small mb-0">
                <dt class="col-5">الشيخ</dt><dd class="col-7">{{ $course->teacher?->name ?? '—' }}</dd>
                <dt class="col-5">المدة</dt><dd class="col-7">{{ $course->periodLabel() }}</dd>
                <dt class="col-5">الموعد</dt><dd class="col-7">{{ $course->schedule ?: '—' }}</dd>
                <dt class="col-5">المكان</dt><dd class="col-7">{{ $course->location ?: '—' }}</dd>
                <dt class="col-5">درجة النجاح</dt><dd class="col-7">{{ $course->pass_score }}%</dd>
                <dt class="col-5">لأولياء الأمور</dt><dd class="col-7">{{ $course->results_to_parents ? 'النتيجة ظاهرة لهم' : 'النتيجة مخفية عنهم' }}</dd>
            </dl>
            @if ($course->description)<p class="small text-muted mt-3 mb-0">{{ $course->description }}</p>@endif
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bullseye text-success ms-1"></i> أهداف الدورة</div>
            <div class="card-body">
                @forelse ($course->objectiveList() as $o)
                    <div class="d-flex gap-2 mb-2"><i class="bi bi-check2-circle text-success"></i><span>{{ $o }}</span></div>
                @empty
                    <span class="text-muted small">لم تُكتب أهداف بعد.</span>
                @endforelse
                <hr>
                <div class="small fw-bold mb-2">ضوابط التقييم (المجموع {{ $course->maxTotal() }})</div>
                @foreach ($course->criteria as $c)<span class="badge bg-green-soft text-success border me-1 mb-1">{{ $c->name }} · {{ $c->max_score }}</span>@endforeach
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><x-stat-card icon="people" label="الطلاب المشاركون" :value="$summary['students']" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="clipboard-check" label="قُيّم منهم" :value="$summary['graded']" color="blue" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="trophy" label="نسبة النجاح" :value="$summary['pass_rate'] !== null ? $summary['pass_rate'].'%' : '—'" color="gold" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="graph-up" label="متوسط النتيجة" :value="$summary['average'] !== null ? $summary['average'].'%' : '—'" /></div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-list-check text-success ms-1"></i> الطلاب ونتائجهم</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>الطالب</th><th>الحلقة</th>@foreach ($course->criteria as $c)<th class="text-center small">{{ $c->name }}<div class="text-muted fw-normal">من {{ $c->max_score }}</div></th>@endforeach<th>النتيجة</th></tr></thead>
            <tbody>
            @forelse ($course->enrollments as $e)
                @php($scores = $e->scores->keyBy('sunnah_criterion_id'))
                <tr>
                    <td>
                        @if ($isAdmin)<a href="{{ route('admin.students.show', $e->student) }}" class="fw-semibold">{{ $e->student->full_name }}</a>@else<span class="fw-semibold">{{ $e->student->full_name }}</span>@endif
                        @if ($e->notes)<div class="small text-muted">{{ $e->notes }}</div>@endif
                    </td>
                    <td class="small">{{ $e->student->circle?->name ?? '—' }}</td>
                    @foreach ($course->criteria as $c)<td class="text-center">{{ isset($scores[$c->id]) ? $scores[$c->id]->score : '—' }}</td>@endforeach
                    <td class="text-nowrap"><span class="badge text-bg-{{ $e->statusColor() }}">{{ $e->statusLabel() }}</span>
                        @if ($e->isGraded()) {{ $e->percentLabel() }} <small class="text-muted">{{ $e->gradeLabel() }}</small>@endif</td>
                </tr>
            @empty
                <tr><td colspan="{{ 3 + $course->criteria->count() }}"><x-empty icon="people" message="لم يُضف طلاب إلى الدورة بعد." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
