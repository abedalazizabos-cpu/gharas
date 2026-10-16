@extends('layouts.app')
@section('title', 'التقارير')
@section('content')
<x-page-header title="التقارير" :crumbs="['التقارير']" />
<div class="row g-3">
    <div class="col-md-6 col-xl-3">
        <div class="card h-100"><div class="card-body">
            <div class="feature-icon mb-3"><i class="bi bi-building"></i></div>
            <h5 class="fw-bold">التقرير الإجمالي للمركز</h5>
            <p class="small text-muted">ملخص الحضور والحفظ لكل الحلقات خلال فترة محددة.</p>
            <a href="{{ route('admin.reports.center') }}" class="btn btn-primary w-100">عرض التقرير</a>
        </div></div>
    </div>
    <div class="col-md-6 col-xl-3">
        <form method="GET" action="" class="card h-100" onsubmit="this.action='{{ url('admin/reports/circles') }}/'+this.circle.value; " data-no-loading><div class="card-body">
            <div class="feature-icon mb-3"><i class="bi bi-bullseye"></i></div>
            <h5 class="fw-bold">تقرير حلقة</h5>
            <select name="circle" class="form-select mb-2" required>@foreach ($circles as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
            <button class="btn btn-primary w-100" @disabled($circles->isEmpty())>عرض التقرير</button>
        </div></form>
    </div>
    <div class="col-md-6 col-xl-3">
        <form method="GET" action="" class="card h-100" onsubmit="this.action='{{ url('admin/reports/teachers') }}/'+this.teacher.value;" data-no-loading><div class="card-body">
            <div class="feature-icon mb-3"><i class="bi bi-person-badge"></i></div>
            <h5 class="fw-bold">تقرير محفظ</h5>
            <select name="teacher" class="form-select mb-2" required>@foreach ($teachers as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select>
            <button class="btn btn-primary w-100" @disabled($teachers->isEmpty())>عرض التقرير</button>
        </div></form>
    </div>
    <div class="col-md-6 col-xl-3">
        <form method="GET" action="" class="card h-100" onsubmit="this.action='{{ url('admin/reports/students') }}/'+this.student.value;" data-no-loading><div class="card-body">
            <div class="feature-icon mb-3"><i class="bi bi-person-lines-fill"></i></div>
            <h5 class="fw-bold">تقرير تقدم طالب</h5>
            <select name="student" class="form-select mb-2" required>@foreach ($students as $s)<option value="{{ $s->id }}">{{ $s->full_name }} ({{ $s->student_number }})</option>@endforeach</select>
            <button class="btn btn-primary w-100" @disabled($students->isEmpty())>عرض التقرير</button>
        </div></form>
    </div>
</div>
<p class="small text-muted mt-3"><i class="bi bi-info-circle"></i> جميع التقارير تدعم تحديد الفترة الزمنية، والطباعة، والتنزيل كملف PDF.</p>
@endsection
