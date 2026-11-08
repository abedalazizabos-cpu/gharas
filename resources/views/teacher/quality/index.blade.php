@extends('layouts.app')
@section('title', 'اختبارات الجودة')
@section('content')
<x-page-header title="اختبارات لجنة الجودة" :crumbs="['اختبارات الجودة']" />

@unless ($isExaminer)
    <div class="alert alert-light border small">لست حالياً من أعضاء لجنة الجودة. يظهر هنا ما أُسند إليك سابقاً فقط.</div>
@endunless

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-hourglass-split text-warning ms-1"></i> طلاب بانتظار اختبارك ({{ $pending->count() }})</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>الطالب</th><th>الحلقة ومحفظها</th><th>الاختبار</th><th>أُسند في</th><th></th></tr></thead>
            <tbody>
            @forelse ($pending as $t)
                <tr>
                    <td class="fw-semibold">{{ $t->student->full_name }}</td>
                    <td class="small">{{ $t->student->circle?->name ?? '—' }} <span class="text-muted">{{ $t->student->circle?->teacher?->name }}</span></td>
                    <td><span class="small text-muted d-block">{{ $t->kindLabel() }}</span>{{ $t->subjectLabel() }}</td>
                    <td class="small">{{ $t->assigned_on->format('Y/m/d') }}</td>
                    <td><a href="{{ route('teacher.quality.grade', $t) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square"></i> رصد النتيجة</a></td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty icon="patch-check" message="لا يوجد طلاب بانتظار الاختبار." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-check2-all text-success ms-1"></i> اختبارات رصدتُ نتيجتها</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>الطالب</th><th>الاختبار</th><th>التاريخ</th><th>النتيجة</th><th></th></tr></thead>
            <tbody>
            @forelse ($done as $t)
                <tr>
                    <td>{{ $t->student->full_name }}</td>
                    <td><span class="small text-muted d-block">{{ $t->kindLabel() }}</span>{{ $t->subjectLabel() }}</td>
                    <td class="small">{{ $t->tested_on?->format('Y/m/d') }}</td>
                    <td><span class="badge text-bg-{{ $t->statusColor() }}">{{ $t->statusLabel() }}</span> {{ rtrim(rtrim(number_format((float) $t->percentage, 2), '0'), '.') }}% <small class="text-muted">{{ $t->gradeLabel() }}</small></td>
                    <td><a href="{{ route('teacher.quality.grade', $t) }}" class="btn btn-sm btn-outline-secondary" title="تعديل"><i class="bi bi-pencil"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty icon="list-check" message="لم ترصد نتائج بعد." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $done->links() }}</div>
@endsection
