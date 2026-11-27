@extends('layouts.app')
@section('title', 'رسائل SMS للأهالي')
@section('content')
<x-page-header title="رسائل SMS لأولياء أمور طلابي" :crumbs="['رسائل SMS']" />

<div class="row g-3">
    <div class="col-lg-5">
        <form method="POST" action="{{ route('teacher.sms.send') }}" class="card" data-confirm="ستصل الرسالة إلى جوالات أولياء الأمور المختارين، وتُحسب من رصيد رسائل المركز." data-confirm-title="إرسال الرسالة" data-confirm-button="نعم، أرسل">
            @csrf
            <div class="card-header"><i class="bi bi-send"></i> رسالة جديدة</div>
            <div class="card-body">
                <label class="form-label">إلى</label>
                <select name="audience" class="form-select mb-2" id="sms-audience">
                    <option value="circle" @selected(old('audience', 'circle') === 'circle')>أولياء أمور حلقة</option>
                    <option value="student" @selected(old('audience', request('student') ? 'student' : null) === 'student')>ولي أمر طالب واحد</option>
                </select>
                <select name="circle_id" class="form-select mb-2 sms-for sms-for-circle @error('circle_id') is-invalid @enderror">
                    @if ($circles->count() > 1)<option value="">اختر الحلقة…</option>@endif
                    @foreach ($circles as $c)<option value="{{ $c->id }}" @selected((string) old('circle_id') === (string) $c->id)>{{ $c->name }} ({{ $counts[$c->id] ?? 0 }} ولي أمر)</option>@endforeach
                </select>
                <select name="student_id" class="form-select mb-2 sms-for sms-for-student @error('student_id') is-invalid @enderror">
                    <option value="">اختر الطالب…</option>
                    @foreach ($students as $st)<option value="{{ $st->id }}" @selected((string) old('student_id', request('student')) === (string) $st->id)>{{ $st->full_name }} · {{ $st->circle?->name }}</option>@endforeach
                </select>
                <label class="form-label mt-1">نص الرسالة</label>
                <textarea name="text" rows="5" maxlength="600" class="form-control @error('text') is-invalid @enderror" id="sms-text" required>{{ old('text') }}</textarea>
                @error('text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text"><span id="sms-count">0</span> حرفاً · <span id="sms-parts">1</span> رسالة لكل رقم (الرسالة العربية 70 حرفاً). يُكتب اسمك في أولها ليعرف ولي الأمر من أرسلها.</div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary"><i class="bi bi-send"></i> إرسال</button></div>
        </form>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history"></i> رسائلي المرسلة</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>الوقت</th><th>النص</th><th>الأرقام</th><th>الحالة</th></tr></thead>
                    <tbody>
                    @forelse ($history as $m)
                        <tr>
                            <td class="small text-nowrap">{{ $m->created_at->format('Y/m/d H:i') }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($m->text, 90) }}</td>
                            <td>{{ $m->phones_count }}</td>
                            <td>@if ($m->status === 'sent')<span class="badge text-bg-success">أُرسلت</span>@else<span class="badge text-bg-danger">لم تُرسل</span><div class="small text-danger">{{ $m->error }}</div>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted small p-3">لم ترسل رسائل بعد.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $history->links() }}</div>
    </div>
</div>
<script>
(function () {
    var a = document.getElementById('sms-audience'), t = document.getElementById('sms-text');
    function show() { document.querySelectorAll('.sms-for').forEach(function (el) { el.classList.toggle('d-none', !el.classList.contains('sms-for-' + a.value)); }); }
    function count() { var n = t.value.length, ar = /[^\x00-\x7F]/.test(t.value); document.getElementById('sms-count').textContent = n;
        document.getElementById('sms-parts').textContent = n <= (ar ? 70 : 160) ? 1 : Math.ceil(n / (ar ? 67 : 153)); }
    a.addEventListener('change', show); t.addEventListener('input', count); show(); count();
})();
</script>
@endsection
