@extends('layouts.app')
@section('title', 'تقييم الواجب')
@section('content')
<x-page-header :title="'تقييم واجب: '.$post->title" :crumbs="[$course->title => route($area.'.sunnah.show', $course), 'تقييم الواجب']" />

<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-4 small">
    <div><span class="text-muted d-block">الدورة</span><strong>{{ $course->title }}</strong></div>
    <div><span class="text-muted d-block">أُعطي في</span><strong>{{ $post->created_at->format('Y/m/d') }}</strong></div>
    <div><span class="text-muted d-block">التسليم</span><strong>{{ $post->due_on?->format('Y/m/d') ?? '—' }}</strong></div>
    <div><span class="text-muted d-block">الدرجة العظمى</span><strong>{{ $post->max_score ?: 10 }}</strong></div>
    @if ($post->body)<div class="flex-grow-1"><span class="text-muted d-block">المطلوب</span>{{ $post->body }}</div>@endif
</div></div>

<form method="POST" action="{{ route($area.'.sunnah.homework.store', [$course, $post]) }}" class="card" novalidate>
    @csrf
    @if ($errors->any())<div class="alert alert-danger m-3 mb-0 small">بعض الدرجات أكبر من الدرجة العظمى ({{ $post->max_score ?: 10 }}).</div>@endif
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>الطالب</th><th>متابعة ولي الأمر</th><th style="width:130px">الدرجة من {{ $post->max_score ?: 10 }}</th><th style="min-width:220px">ملاحظة الشيخ</th></tr></thead>
            <tbody>
            @foreach ($course->enrollments as $e)
                @php($f = $follows[$e->student_id] ?? null)
                <tr>
                    <td class="fw-semibold">{{ $e->student->full_name }}<div class="small text-muted fw-normal">{{ $e->student->circle?->name ?? 'بلا حلقة' }}</div></td>
                    <td class="small">
                        @if ($f?->done_at)<span class="text-success"><i class="bi bi-check2-square"></i> أكّد الإنجاز</span>@elseif ($f?->seen_at)<span class="text-muted"><i class="bi bi-eye"></i> اطّلع</span>@else<span class="text-muted">لم يطّلع</span>@endif
                    </td>
                    <td><input type="number" name="rows[{{ $e->student_id }}][score]" value="{{ old("rows.{$e->student_id}.score", $f?->score) }}" min="0" max="{{ $post->max_score ?: 10 }}" class="form-control form-control-sm text-center @error("rows.{$e->student_id}.score") is-invalid @enderror"></td>
                    <td><input name="rows[{{ $e->student_id }}][feedback]" value="{{ old("rows.{$e->student_id}.feedback", $f?->feedback) }}" maxlength="255" class="form-control form-control-sm" placeholder="مثال: أحسنت، خط جميل"></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent d-flex justify-content-between align-items-center gap-2">
        <small class="text-muted">يُترك الصف فارغاً لمن لم يسلّم بعد. يصل ولي الأمر إشعار بالدرجة.</small>
        <div class="d-flex gap-2"><a href="{{ route($area.'.sunnah.show', $course) }}#posts" class="btn btn-light">رجوع</a>
        <button class="btn btn-primary px-4"><i class="bi bi-save ms-1"></i> حفظ التقييم</button></div>
    </div>
</form>
@endsection
