@extends('layouts.app')
@section('title', $circle->name)
@section('content')
<x-page-header :title="$circle->name" :crumbs="['حلقاتي' => route('teacher.circles.index'), $circle->name]">
    @if (auth()->user()->hasPermission('teacher.attendance'))<a href="{{ route('teacher.attendance.create', $circle) }}" class="btn btn-success"><i class="bi bi-calendar-check"></i> تسجيل الحضور</a>@endif
    <a href="{{ route('teacher.circles.report', $circle) }}" class="btn btn-outline-primary"><i class="bi bi-bar-chart"></i> تقرير الحلقة</a>
    @if (auth()->user()->hasPermission('teacher.circle.edit'))<a href="{{ route('teacher.circles.edit', $circle) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil-square"></i> الاسم والموعد</a>@endif
</x-page-header>
<p class="text-muted small mb-3"><i class="bi bi-clock ms-1"></i> {{ $circle->daysLabel() }} · {{ $circle->timeLabel() }}</p>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">طلاب الحلقة ({{ $students->count() }})</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>الطالب</th><th>رقم الطالب</th><th class="text-end">إجراءات</th></tr></thead>
                    <tbody>
                    @forelse ($students as $s)
                        <tr>
                            <td><a href="{{ route('teacher.students.show', $s) }}" class="fw-semibold">{{ $s->full_name }}</a></td>
                            <td class="text-muted">{{ $s->student_number }}</td>
                            <td class="text-end table-actions">
                                @if (auth()->user()->hasPermission('teacher.memorization'))<a href="{{ route('teacher.memorization.create', ['student' => $s->id]) }}" class="btn btn-sm btn-primary"><i class="bi bi-plus"></i> تسميع</a>@endif
                                @if (auth()->user()->hasPermission('teacher.assessments'))<a href="{{ route('teacher.assessments.create', ['student' => $s->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-award"></i> تقييم</a>@endif
                                @foreach ($s->parents as $p)
                                    @if (auth()->user()->hasPermission('messages.send'))<a href="{{ route('messages.create', ['to' => $p->id, 'student' => $s->id]) }}" class="btn btn-sm btn-outline-success" title="مراسلة {{ $p->name }}"><i class="bi bi-chat-dots"></i> مراسلة ولي الأمر</a>@endif
                                @endforeach
                                <a href="{{ route('teacher.students.show', $s) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                @if (auth()->user()->hasPermission('teacher.students.edit'))<a href="{{ route('teacher.students.edit', $s) }}" class="btn btn-sm btn-outline-secondary" title="تعديل البيانات"><i class="bi bi-pencil"></i></a>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><x-empty icon="people" message="لا يوجد طلاب نشطون في هذه الحلقة." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">آخر الجلسات</div>
            <ul class="list-group list-group-flush small">
                @forelse ($sessions as $session)
                    <li class="list-group-item d-flex justify-content-between">
                        @if (auth()->user()->hasPermission('teacher.attendance'))<a href="{{ route('teacher.attendance.create', [$circle, 'date' => $session->session_date->toDateString()]) }}">{{ $session->session_date->translatedFormat('l Y/m/d') }}</a>@endif
                        <span>{{ $session->present_count }}/{{ $session->attendance_count }}</span>
                    </li>
                @empty
                    <li class="list-group-item"><x-empty icon="calendar" message="لم تُسجل جلسات بعد." /></li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
