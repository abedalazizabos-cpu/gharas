@extends('layouts.app')
@section('title', $student->full_name)
@section('content')
<x-page-header :title="$student->full_name" :crumbs="['حلقاتي' => route('teacher.circles.index'), 'ملف الطالب']">
    @if (auth()->user()->hasPermission('teacher.students.edit'))<a href="{{ route('teacher.students.edit', $student) }}" class="btn btn-outline-primary"><i class="bi bi-pencil-square"></i> تعديل البيانات</a>@endif
    @if (auth()->user()->hasPermission('teacher.memorization'))<a href="{{ route('teacher.memorization.create', ['student' => $student->id]) }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> تسجيل تسميع</a>@endif
    @if (auth()->user()->hasPermission('teacher.assessments'))<a href="{{ route('teacher.assessments.create', ['student' => $student->id]) }}" class="btn btn-outline-primary"><i class="bi bi-award"></i> تقييم</a>@endif
    @foreach ($student->parents as $p)
        @if (auth()->user()->hasPermission('messages.send'))<a href="{{ route('messages.create', ['to' => $p->id, 'student' => $student->id]) }}" class="btn btn-outline-secondary"><i class="bi bi-chat-dots"></i> مراسلة {{ $p->name }}</a>@endif
    @endforeach
</x-page-header>
@include('shared._guardians', ['prefix' => 'teacher'])
<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body small">
            <dl class="row mb-0">
                <dt class="col-5">رقم الطالب</dt><dd class="col-7">{{ $student->student_number }}</dd>
                <dt class="col-5">الحلقة</dt><dd class="col-7">{{ $student->circle?->name }}</dd>
                <dt class="col-5">تاريخ التسجيل</dt><dd class="col-7">{{ $student->enrollment_date->format('Y/m/d') }}</dd>
                <dt class="col-5">العمر</dt><dd class="col-7">{{ $student->age() ? $student->age().' سنة' : '—' }}</dd>
                <dt class="col-5">ولي الأمر</dt><dd class="col-7">{{ $student->parents->pluck('name')->implode('، ') ?: '—' }}</dd>
            </dl>
        </div></div>
    </div>
    <div class="col-lg-8">@include('shared._progress')</div>
</div>
<div class="card">
    <div class="card-header p-0 border-0">
        <ul class="nav nav-tabs px-3 pt-2">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#t-mem">الحفظ والمراجعة</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-att">الحضور</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-ass">التقييمات</button></li>
        </ul>
    </div>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="t-mem">@include('shared._memorization-table', ['editRoute' => 'teacher.memorization.edit', 'showShared' => true])</div>
        <div class="tab-pane fade" id="t-att">@include('shared._attendance-table')</div>
        <div class="tab-pane fade p-3" id="t-ass">@include('shared._assessments')</div>
    </div>
</div>
@endsection
