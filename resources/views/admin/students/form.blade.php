@extends('layouts.app')
@section('title', $student->exists ? 'تعديل بيانات طالب' : 'إضافة طالب')
@section('content')
@php($prefix = $prefix ?? 'admin')
@php($isTeacher = $prefix === 'teacher')
<x-page-header :title="$student->exists ? 'تعديل بيانات: '.$student->full_name : 'إضافة طالب جديد'" :crumbs="$isTeacher ? ['حلقاتي' => route('teacher.circles.index'), $student->full_name => route('teacher.students.show', $student), 'تعديل'] : ['الطلاب' => route('admin.students.index'), $student->exists ? 'تعديل' : 'إضافة']" />

<form method="POST" action="{{ $student->exists ? route($prefix.'.students.update', $student) : route('admin.students.store') }}" class="card" novalidate>
    @csrf
    @if ($student->exists) @method('PUT') @endif
    <div class="card-body">
        <h6 class="fw-bold text-success mb-3">البيانات الأساسية</h6>
        <div class="row g-3 mb-4">
            <x-field name="full_name" label="الاسم الكامل" :value="$student->full_name" required col="col-md-6" />
            @if ($isTeacher)
                <div class="col-md-3"><label class="form-label">رقم الطالب</label><input class="form-control" value="{{ $student->student_number }}" disabled></div>
            @else
                <x-field name="student_number" label="رقم الطالب" :value="$student->student_number" col="col-md-3" help="يُولَّد تلقائياً إن تُرك فارغاً." />
            @endif
            <x-field name="enrollment_date" label="تاريخ التسجيل" type="date" :value="optional($student->enrollment_date)->format('Y-m-d')" required col="col-md-3" />
            <div class="col-md-3">
                <label class="form-label" for="gender">الجنس</label>
                <select id="gender" name="gender" class="form-select"><option value="">—</option>
                    @foreach (\App\Models\Student::GENDERS as $k => $v)<option value="{{ $k }}" @selected(old('gender', $student->gender) === $k)>{{ $v }}</option>@endforeach</select>
            </div>
            <x-field name="birth_date" label="تاريخ الميلاد (اختياري)" type="date" :value="optional($student->birth_date)->format('Y-m-d')" col="col-md-3" />
            <x-field name="phone" label="رقم التواصل (اختياري)" :value="$student->phone" col="col-md-3" dir="ltr" />
        </div>

        <h6 class="fw-bold text-success mb-3">الحلقة</h6>
        <div class="row g-3 mb-4">
            @if ($student->exists)
                <div class="col-md-6">
                    <label class="form-label">الحلقة الحالية</label>
                    <input class="form-control" value="{{ $student->circle?->name ?? 'بدون حلقة' }}" disabled>
                    <div class="form-text">{{ $isTeacher ? 'نقل الطالب بين الحلقات من صلاحيات الإدارة.' : 'لنقل الطالب استخدم زر «نقل إلى حلقة» في ملف الطالب، حتى يُحفظ تاريخ الانتقال.' }}</div>
                </div>
            @else
                <div class="col-md-6">
                    <label class="form-label" for="circle_id">الحلقة</label>
                    <select id="circle_id" name="circle_id" class="form-select @error('circle_id') is-invalid @enderror">
                        <option value="">— بدون حلقة —</option>
                        @foreach ($circles as $c)
                            <option value="{{ $c->id }}" @selected(old('circle_id') == $c->id)>{{ $c->name }} ({{ $c->students_count }}/{{ $c->capacity }}){{ $c->students_count >= $c->capacity ? ' - ممتلئة' : '' }}</option>
                        @endforeach
                    </select>
                    @error('circle_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if (\App\Models\Setting::get('allow_capacity_override') === '1')
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="override_capacity" value="1" id="override_capacity" @checked(old('override_capacity'))>
                            <label class="form-check-label small" for="override_capacity">تجاوز السعة (صلاحية إدارية)</label>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <h6 class="fw-bold text-success mb-3">ولي الأمر</h6>
        @if ($student->exists && $student->parents->isNotEmpty())
            <div class="mb-3">
                <div class="small text-muted mb-1">أولياء الأمور المرتبطون (أزل العلامة لفك الارتباط):</div>
                @php($keep = collect(old('parent_ids', $student->parents->pluck('id')->all()))->map(fn ($v) => (int) $v))
                @foreach ($student->parents as $p)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="parent_ids[]" value="{{ $p->id }}" id="par-{{ $p->id }}" @checked($keep->contains($p->id))>
                        <label class="form-check-label" for="par-{{ $p->id }}">{{ $p->name }} <span class="text-muted" dir="ltr">{{ $p->phone }}</span></label>
                    </div>
                @endforeach
            </div>
            <div class="small text-muted mb-2">إضافة ولي أمر آخر (اختياري):</div>
        @endif
        <div class="row g-3 mb-4" id="guardian-box">
            <div class="col-md-4">
                <label class="form-label" for="guardian_phone">رقم جوال ولي الأمر @unless ($student->exists)<span class="text-danger">*</span>@endunless</label>
                <input type="tel" id="guardian_phone" name="guardian_phone" value="{{ old('guardian_phone') }}" dir="ltr" inputmode="numeric" placeholder="05xxxxxxxx"
                    class="form-control @error('guardian_phone') is-invalid @enderror" @unless ($student->exists) required @endunless>
                @error('guardian_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text" id="guardian-hint">يدخل ولي الأمر إلى النظام بهذا الرقم.</div>
            </div>
            <x-field name="guardian_name" label="اسم ولي الأمر" col="col-md-5" placeholder="مثال: أحمد محمد السبيعي" />
            <div class="col-md-3">
                <label class="form-label" for="guardian_relationship">صلة القرابة</label>
                <select id="guardian_relationship" name="guardian_relationship" class="form-select">
                    @foreach (['father' => 'الأب', 'mother' => 'الأم', 'guardian' => 'ولي أمر آخر'] as $k => $v)<option value="{{ $k }}" @selected(old('guardian_relationship', 'father') === $k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div class="col-12"><div class="form-text">إذا كان الرقم مسجلاً يُربط الطالب بولي الأمر نفسه (مفيد للإخوة)، وإلا يُنشأ له حساب تلقائياً بكلمة مرور مؤقتة تظهر بعد الحفظ.</div></div>
        </div>
        <script>
        (function () {
            var input = document.getElementById('guardian_phone'), hint = document.getElementById('guardian-hint'), name = document.getElementById('guardian_name'), timer;
            input.addEventListener('input', function () {
                clearTimeout(timer);
                var v = input.value.replace(/\D+/g, '');
                if (v.length < 10) { hint.className = 'form-text'; hint.textContent = 'يدخل ولي الأمر إلى النظام بهذا الرقم.'; name.disabled = false; return; }
                timer = setTimeout(function () {
                    fetch('{{ route($prefix.'.students.guardian-lookup') }}?phone=' + encodeURIComponent(input.value), { headers: { Accept: 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            if (d.found && d.parent) {
                                hint.className = 'form-text text-success';
                                hint.textContent = 'ولي أمر مسجل: ' + d.name + (d.children.length ? ' (أبناؤه: ' + d.children.join('، ') + ')' : '') + '. سيُربط الطالب به.';
                                name.value = d.name; name.disabled = true;
                            } else if (d.found) {
                                hint.className = 'form-text text-danger'; hint.textContent = 'هذا الرقم مسجل لحساب ليس ولي أمر.'; name.disabled = false;
                            } else {
                                hint.className = 'form-text text-primary'; hint.textContent = 'رقم جديد: سيُنشأ حساب ولي أمر بهذا الاسم والرقم.'; name.disabled = false;
                            }
                        });
                }, 350);
            });
            if (input.value) input.dispatchEvent(new Event('input'));
        })();
        </script>

        @unless ($isTeacher)
        <h6 class="fw-bold text-success mb-3">ملاحظات داخلية</h6>
        <div class="row g-3">
            <x-field name="internal_notes" label="ملاحظات الإدارة (لا تظهر لولي الأمر)" type="textarea" :value="$student->internal_notes" col="col-12" />
        </div>
        @endunless
    </div>
    <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
        <a href="{{ $student->exists ? route($prefix.'.students.show', $student) : route('admin.students.index') }}" class="btn btn-light">إلغاء</a>
        <button class="btn btn-primary px-4"><i class="bi bi-save ms-1"></i> حفظ</button>
    </div>
</form>
@endsection
