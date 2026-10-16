@extends('layouts.app')
@section('title', 'تقرير تقدم: '.$student->full_name)
@section('content')
<x-page-header :title="'تقرير تقدم: '.$student->full_name" :crumbs="['التقارير' => route('admin.reports.index'), 'تقرير طالب']">
    <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> طباعة</button>
</x-page-header>
@include('shared._period-filter')
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-4 small">
    <span><strong>رقم الطالب:</strong> {{ $student->student_number }}</span>
    <span><strong>الحلقة:</strong> {{ $student->circle?->name ?? '—' }}</span>
    <span><strong>المحفظ:</strong> {{ $student->circle?->teacher?->name ?? '—' }}</span>
    <span><strong>الحالة:</strong> {{ $student->statusLabel() }}</span>
</div></div>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat-card icon="calendar-check" label="نسبة الحضور" :value="$row['attendance_rate'] !== null ? $row['attendance_rate'].'%' : '—'" :hint="'غياب: '.$row['attendance']['absent'].' · بعذر: '.$row['attendance']['excused']" /></div>
    <div class="col-6 col-lg-3"><x-stat-card icon="book" label="آيات حفظ جديد" :value="$row['new_ayahs']" color="gold" /></div>
    <div class="col-6 col-lg-3"><x-stat-card icon="arrow-repeat" label="آيات مراجعة" :value="$row['revision_ayahs']" color="blue" /></div>
    <div class="col-6 col-lg-3"><x-stat-card icon="award" label="متوسط الدرجة" :value="$row['avg_score'] ?? '—'" :hint="$row['avg_assessment'] !== null ? 'متوسط التقييم: '.round($row['avg_assessment'], 1).'%' : null" /></div>
</div>
<div class="row g-3">
    <div class="col-lg-4">@include('shared._progress')</div>
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">سجلات التسميع في الفترة ({{ $records->count() }})</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>التاريخ</th><th>النوع</th><th>السورة</th><th>الآيات</th><th>الدرجة</th><th>ملاحظات</th></tr></thead>
                <tbody>
                @forelse ($records as $r)
                    <tr><td>{{ $r->recorded_on->format('Y/m/d') }}</td><td>{{ $r->typeLabel() }}</td><td>{{ $r->surah->name }}</td><td>{{ $r->from_ayah }}-{{ $r->to_ayah }}</td><td>{{ $r->score }} ({{ $r->gradeLabel() }})</td><td class="small">{{ $r->notes }}</td></tr>
                @empty
                    <tr><td colspan="6"><x-empty icon="book" message="لا توجد سجلات في هذه الفترة." /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="card">
            <div class="card-header">التقييمات في الفترة</div>
            <div class="card-body">
                @forelse ($assessments as $a)
                    <div class="d-flex justify-content-between border-bottom py-2 small"><span>{{ $a->assessed_on->format('Y/m/d') }} · @foreach ($a->scores as $s){{ $s->criterion?->name }} {{ $s->score }}/{{ $s->max_score }}@if (! $loop->last)، @endif @endforeach</span><strong>{{ round($a->percentage, 1) }}%</strong></div>
                @empty
                    <x-empty icon="award" message="لا توجد تقييمات في هذه الفترة." />
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
