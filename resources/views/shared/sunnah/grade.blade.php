@extends('layouts.app')
@section('title', 'رصد درجات الدورة')
@section('content')
<x-page-header :title="'رصد درجات: '.$course->title" :crumbs="[$course->title => $back, 'رصد الدرجات']" />

@if ($course->criteria->isEmpty() || $course->enrollments->isEmpty())
    <div class="card"><x-empty icon="sliders" :message="$course->criteria->isEmpty() ? 'لم تُحدد ضوابط تقييم لهذه الدورة. يضيفها المدير من صفحة تعديل الدورة.' : 'لا يوجد طلاب في الدورة بعد. يضيفهم المدير من صفحة تعديل الدورة.'" /></div>
@else
<form method="POST" action="{{ $action }}" class="card" novalidate>
    @csrf
    <div class="card-body pb-0">
        <div class="row g-3 align-items-end mb-2">
            <x-field name="evaluated_on" label="تاريخ التقييم" type="date" :value="now()->toDateString()" required col="col-md-3" :max="now()->toDateString()" />
            <div class="col-md-9 small text-muted">أدخل درجات كل ضابط للطالب. يُرصد الطالب حين تكتمل كل درجاته، ويُترك الصف فارغاً لمن لم يُقيَّم بعد. درجة النجاح {{ $course->pass_score }}% من مجموع {{ $course->maxTotal() }}.@if ($course->criteria->contains("from_attendance", true)) الضابط المظلل يُحسب تلقائياً من حضور الطالب في لقاءات الدورة.@endif</div>
        </div>
        @if ($errors->any())<div class="alert alert-danger small">بعض الدرجات أكبر من الدرجة العظمى أو غير صحيحة. راجع الخانات المظللة.</div>@endif
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0" id="sunnah-sheet">
            <thead><tr><th>الطالب</th>@foreach ($course->criteria as $c)<th class="text-center small" style="min-width:95px">{{ $c->name }}<div class="text-muted fw-normal">من {{ $c->max_score }}</div></th>@endforeach<th class="text-center">المجموع</th><th style="min-width:180px">ملاحظة الشيخ</th></tr></thead>
            <tbody>
            @foreach ($course->enrollments as $e)
                @php($scores = $e->scores->keyBy('sunnah_criterion_id'))
                <tr>
                    <td><span class="fw-semibold">{{ $e->student->full_name }}</span><div class="small text-muted">{{ $e->student->circle?->name ?? 'بلا حلقة' }} · <span class="text-{{ $e->statusColor() }}">{{ $e->statusLabel() }}</span></div></td>
                    @foreach ($course->criteria as $c)
                        @if ($c->from_attendance && isset($rates[$e->student_id]))
                            <td title="محسوبة من الحضور: {{ round($rates[$e->student_id]) }}%"><input type="number" value="{{ \App\Services\SunnahService::attendanceScore($c->max_score, $rates[$e->student_id]) }}" class="form-control form-control-sm text-center sheet-score bg-light" readonly tabindex="-1"><div class="text-muted text-center" style="font-size:.7rem"><i class="bi bi-calendar-check"></i> حضور {{ round($rates[$e->student_id]) }}%</div></td>
                        @else
                            <td><input type="number" name="rows[{{ $e->id }}][scores][{{ $c->id }}]" value="{{ old("rows.{$e->id}.scores.{$c->id}", $scores[$c->id]->score ?? null) }}" min="0" max="{{ $c->max_score }}" class="form-control form-control-sm text-center sheet-score @error("rows.{$e->id}.scores.{$c->id}") is-invalid @enderror"></td>
                        @endif
                    @endforeach
                    <td class="text-center fw-bold sheet-total">—</td>
                    <td><input name="rows[{{ $e->id }}][notes]" value="{{ old("rows.{$e->id}.notes", $e->notes) }}" class="form-control form-control-sm" maxlength="1000"></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
        <a href="{{ $back }}" class="btn btn-light">رجوع</a>
        <button class="btn btn-primary px-4"><i class="bi bi-save ms-1"></i> حفظ الدرجات</button>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
document.querySelectorAll('#sunnah-sheet tbody tr').forEach(function (tr) {
    const inputs = tr.querySelectorAll('.sheet-score'), out = tr.querySelector('.sheet-total'), max = {{ $course->maxTotal() ?: 1 }}, pass = {{ $course->pass_score }};
    function sum() {
        let total = 0, filled = 0;
        inputs.forEach(i => { if (i.value !== '') { total += parseInt(i.value, 10) || 0; filled++; } });
        if (!filled) { out.textContent = '—'; out.className = 'text-center fw-bold sheet-total'; return; }
        const pct = Math.round(total / max * 100);
        out.textContent = total + ' (' + pct + '%)';
        out.className = 'text-center fw-bold sheet-total ' + (filled < inputs.length ? 'text-muted' : (pct >= pass ? 'text-success' : 'text-danger'));
    }
    inputs.forEach(i => i.addEventListener('input', sum));
    sum();
});
</script>
@endpush
