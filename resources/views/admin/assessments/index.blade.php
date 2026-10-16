@extends('layouts.app')
@section('title', 'التقييمات')
@section('content')
<x-page-header title="التقييمات" :crumbs="['التقييمات']">
    <a href="{{ route('admin.criteria.index') }}" class="btn btn-outline-primary"><i class="bi bi-sliders"></i> معايير التقييم</a>
</x-page-header>
<form method="GET" class="filter-bar row g-2" data-no-loading>
    <div class="col-md-5"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="اسم أو رقم الطالب"></div>
    <div class="col-md-4"><select name="circle" class="form-select"><option value="">كل الحلقات</option>@foreach ($circles as $c)<option value="{{ $c->id }}" @selected(request('circle') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> بحث</button></div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>التاريخ</th><th>الطالب</th><th>الحلقة</th><th>المحفظ</th><th>التفاصيل</th><th>النتيجة</th><th></th></tr></thead>
            <tbody>
            @forelse ($assessments as $a)
                <tr>
                    <td>{{ $a->assessed_on->format('Y/m/d') }}</td>
                    <td><a href="{{ route('admin.students.show', $a->student) }}">{{ $a->student->full_name }}</a></td>
                    <td>{{ $a->circle?->name }}</td>
                    <td>{{ $a->teacher?->name }}</td>
                    <td class="small">@foreach ($a->scores as $s)<span class="d-inline-block me-2">{{ $s->criterion?->name }}: {{ $s->score }}/{{ $s->max_score }}</span>@endforeach</td>
                    <td><strong>{{ round($a->percentage, 1) }}%</strong> <small class="text-muted">{{ $a->gradeLabel() }}</small></td>
                    <td><x-delete-form :action="route('admin.assessments.destroy', $a)" label="" message="سيتم حذف التقييم وتوثيق العملية." /></td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty icon="award" message="لا توجد تقييمات." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $assessments->links() }}</div>
@endsection
