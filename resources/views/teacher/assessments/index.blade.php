@extends('layouts.app')
@section('title', 'التقييمات')
@section('content')
<x-page-header title="تقييمات طلابي" :crumbs="['التقييمات']">
    <a href="{{ route('teacher.assessments.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> تقييم جديد</a>
</x-page-header>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>التاريخ</th><th>الطالب</th><th>التفاصيل</th><th>النتيجة</th><th>ملاحظات</th></tr></thead>
            <tbody>
            @forelse ($assessments as $a)
                <tr>
                    <td>{{ $a->assessed_on->format('Y/m/d') }}</td>
                    <td><a href="{{ route('teacher.students.show', $a->student) }}">{{ $a->student->full_name }}</a></td>
                    <td class="small">@foreach ($a->scores as $s)<span class="d-inline-block me-2">{{ $s->criterion?->name }}: {{ $s->score }}/{{ $s->max_score }}</span>@endforeach</td>
                    <td><strong>{{ round($a->percentage, 1) }}%</strong> <small class="text-muted">{{ $a->gradeLabel() }}</small></td>
                    <td class="small">{{ $a->notes }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty icon="award" message="لا توجد تقييمات بعد." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $assessments->links() }}</div>
@endsection
