@extends('layouts.app')
@php($editing = $course->exists)
@section('title', $editing ? 'تعديل دورة' : 'دورة جديدة')
@section('content')
<x-page-header :title="$editing ? 'تعديل دورة: '.$course->title : 'دورة جديدة في قسم السنة'" :crumbs="['قسم السنة' => route('admin.sunnah.index'), $editing ? 'تعديل' : 'دورة جديدة']" />

<form method="POST" action="{{ $editing ? route('admin.sunnah.update', $course) : route('admin.sunnah.store') }}" novalidate>
    @csrf @if ($editing) @method('PUT') @endif

    @if ($errors->any())
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> راجع الحقول المظللة.
            @foreach (['criteria', 'student_ids'] as $k)@error($k)<div>{{ $message }}</div>@enderror @endforeach
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-journal-bookmark text-success ms-1"></i> بيانات الدورة</div>
        <div class="card-body"><div class="row g-3">
            <x-field name="title" label="اسم الدورة" :value="$course->title" required col="col-md-6" placeholder="مثال: الأربعون النووية (1 - 20)" />
            <div class="col-md-6">
                <label class="form-label" for="category">المجال <span class="text-danger">*</span></label>
                <select id="category" name="category" class="form-select @error('category') is-invalid @enderror" required>
                    @foreach (\App\Models\SunnahCourse::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(old('category', $course->category) === $k)>{{ $v }}</option>@endforeach
                </select>
                @unless ($editing)<div class="form-text">عند تغيير المجال قبل الحفظ تُقترح ضوابط تقييم مناسبة له (<a href="#" id="reset-criteria">استخدام الضوابط المقترحة</a>).</div>@endunless
            </div>
            <div class="col-md-6">
                <label class="form-label" for="teacher_id">الشيخ الذي يعطي الدورة <span class="text-danger">*</span></label>
                <select id="teacher_id" name="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror" required>
                    <option value="">— اختر الشيخ —</option>
                    @foreach ($teachers as $t)<option value="{{ $t->id }}" @selected((string) old('teacher_id', $course->teacher_id) === (string) $t->id)>{{ $t->name }}</option>@endforeach
                </select>
                @error('teacher_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="status">حالة الدورة <span class="text-danger">*</span></label>
                <select id="status" name="status" class="form-select">@foreach (\App\Models\SunnahCourse::STATUSES as $k => $v)<option value="{{ $k }}" @selected(old('status', $course->status) === $k)>{{ $v }}</option>@endforeach</select>
            </div>
            <x-field name="starts_on" label="تاريخ البداية" type="date" :value="$course->starts_on?->format('Y-m-d')" col="col-md-3" />
            <x-field name="ends_on" label="تاريخ النهاية" type="date" :value="$course->ends_on?->format('Y-m-d')" col="col-md-3" />
            <x-field name="schedule" label="الموعد" :value="$course->schedule" col="col-md-3" placeholder="مثال: الأحد والثلاثاء بعد العصر" />
            <x-field name="location" label="المكان" :value="$course->location" col="col-md-3" placeholder="مثال: المصلى" />
            <x-field name="objectives" label="أهداف الدورة (هدف في كل سطر)" type="textarea" :value="$course->objectives" col="col-md-6" rows="6" placeholder="مثال: أن يحفظ الطالب الأحاديث المقررة بإتقان" />
            <x-field name="description" label="وصف مختصر (اختياري)" type="textarea" :value="$course->description" col="col-md-6" rows="6" />
        </div></div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-sliders text-success ms-1"></i> ضوابط التقييم</div>
        <div class="card-body pb-0">
            <p class="small text-muted">يرصد الشيخ لكل طالب درجة في كل ضابط، وتُحسب النسبة من مجموع الدرجات العظمى. الضابط الذي رُصدت عليه درجات لا يُحذف، ويمكن تعديل اسمه ودرجته.</p>
            <div class="row g-3 mb-3">
                <x-field name="pass_score" label="درجة النجاح (%)" type="number" :value="$course->pass_score" required col="col-md-3" min="1" max="100" />
                <div class="col-md-9 d-flex align-items-end">
                    <input type="hidden" name="results_to_parents" value="0">
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="results_to_parents" name="results_to_parents" value="1" @checked(old('results_to_parents', $course->results_to_parents))>
                        <label class="form-check-label" for="results_to_parents">إظهار النتيجة لولي الأمر في حسابه (مع إشعار عند الرصد)</label></div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="criteria-table">
                <thead><tr><th>الضابط</th><th style="width:150px">الدرجة العظمى</th><th style="width:60px"></th></tr></thead>
                <tbody>
                @foreach ($criteria as $i => $c)
                    <tr>
                        <td><input type="hidden" name="criteria[{{ $i }}][id]" value="{{ $c['id'] ?? '' }}">
                            <input name="criteria[{{ $i }}][name]" value="{{ $c['name'] }}" class="form-control form-control-sm @error("criteria.$i.name") is-invalid @enderror" required></td>
                        <td><input type="number" name="criteria[{{ $i }}][max_score]" value="{{ $c['max_score'] }}" min="1" max="100" class="form-control form-control-sm criterion-max @error("criteria.$i.max_score") is-invalid @enderror" required></td>
                        <td>@if (in_array((int) ($c['id'] ?? 0), $usedCriteria, true))<i class="bi bi-lock text-muted" title="رُصدت عليه درجات"></i>@else<button type="button" class="btn btn-sm btn-outline-danger remove-criterion" title="حذف"><i class="bi bi-x-lg"></i></button>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" id="add-criterion"><i class="bi bi-plus-lg"></i> إضافة ضابط</button>
            <span class="small text-muted">مجموع الدرجات العظمى: <strong id="criteria-total">0</strong></span>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span><i class="bi bi-people text-success ms-1"></i> الطلاب المشاركون <span class="badge text-bg-success" id="picked-count">0</span></span>
            <small class="text-muted">من كل طلاب المركز، لا يشترط أن يكونوا من حلقة الشيخ</small>
        </div>
        <div class="card-body">
            <div class="row g-2 mb-2">
                <div class="col-md-4"><input type="search" id="pick-search" class="form-control" placeholder="ابحث بالاسم أو رقم الطالب"></div>
                <div class="col-md-3"><select id="pick-circle" class="form-select"><option value="">كل الحلقات</option><option value="none">بلا حلقة</option>@foreach ($circles as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><select id="pick-gender" class="form-select"><option value="">الكل</option>@foreach (\App\Models\Student::GENDERS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="button" class="btn btn-outline-success flex-grow-1" id="pick-all">تحديد الظاهرين</button>
                    <button type="button" class="btn btn-outline-secondary" id="pick-none" title="إلغاء تحديد الظاهرين"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="pick-only"><label class="form-check-label small" for="pick-only">عرض المحددين فقط</label></div></div>
            </div>
            <div class="border rounded" style="max-height:420px;overflow:auto">
                <ul class="list-group list-group-flush" id="pick-list">
                    @foreach ($students as $s)
                        @php($isLocked = in_array($s->id, $locked, true))
                        <li class="list-group-item py-1" data-name="{{ $s->full_name }} {{ $s->student_number }}" data-circle="{{ $s->current_circle_id ?? 'none' }}" data-gender="{{ $s->gender }}">
                            <div class="form-check mb-0">
                                <input class="form-check-input pick" type="checkbox" name="student_ids[]" value="{{ $s->id }}" id="st-{{ $s->id }}" @checked(in_array($s->id, $selected, true) || $isLocked) @if ($isLocked) onclick="return false" @endif>
                                <label class="form-check-label w-100 d-flex justify-content-between" for="st-{{ $s->id }}">
                                    <span>{{ $s->full_name }} @if ($isLocked)<i class="bi bi-lock text-muted small" title="رُصدت نتيجته فلا يُزال من الدورة"></i>@endif</span>
                                    <small class="text-muted">{{ $s->circle?->name ?? 'بلا حلقة' }}</small>
                                </label>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="form-text">يُرسل إشعار لولي أمر كل طالب يُضاف إلى الدورة.</div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="{{ $editing ? route('admin.sunnah.show', $course) : route('admin.sunnah.index') }}" class="btn btn-light">إلغاء</a>
        <button class="btn btn-primary px-4"><i class="bi bi-save ms-1"></i> حفظ الدورة</button>
    </div>
</form>

<template id="criterion-row">
    <tr>
        <td><input type="hidden" data-field="id" value=""><input data-field="name" class="form-control form-control-sm" required></td>
        <td><input type="number" data-field="max_score" value="10" min="1" max="100" class="form-control form-control-sm criterion-max" required></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-criterion" title="حذف"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>
@endsection

@push('scripts')
<script id="sunnah-defaults" type="application/json">@json(collect(\App\Models\SunnahCourse::CATEGORIES)->keys()->mapWithKeys(fn ($k) => [$k => \App\Models\SunnahCourse::defaultCriteria($k)]))</script>
<script>
(function () {
    const tbody = document.querySelector('#criteria-table tbody');
    const tpl = document.getElementById('criterion-row');
    let next = tbody.rows.length + 100;

    function renumber() {
        let total = 0;
        tbody.querySelectorAll('.criterion-max').forEach(i => total += parseInt(i.value || 0, 10));
        document.getElementById('criteria-total').textContent = total;
    }
    function addRow(name, max) {
        const row = tpl.content.firstElementChild.cloneNode(true);
        const i = next++;
        row.querySelectorAll('[data-field]').forEach(el => { el.name = 'criteria[' + i + '][' + el.dataset.field + ']'; });
        if (name) row.querySelector('[data-field=name]').value = name;
        if (max) row.querySelector('[data-field=max_score]').value = max;
        tbody.appendChild(row);
        renumber();
        return row;
    }
    document.getElementById('add-criterion').addEventListener('click', () => addRow().querySelector('[data-field=name]').focus());
    tbody.addEventListener('click', e => { const b = e.target.closest('.remove-criterion'); if (b) { b.closest('tr').remove(); renumber(); } });
    tbody.addEventListener('input', renumber);
    renumber();

    const reset = document.getElementById('reset-criteria');
    if (reset) {
        const defaults = JSON.parse(document.getElementById('sunnah-defaults').textContent);
        const apply = () => { tbody.innerHTML = ''; (defaults[document.getElementById('category').value] || []).forEach(c => addRow(c[0], c[1])); };
        reset.addEventListener('click', e => { e.preventDefault(); apply(); });
        document.getElementById('category').addEventListener('change', apply);
    }

    // Student picker: search, circle and gender filters, select the visible ones.
    const items = Array.from(document.querySelectorAll('#pick-list li'));
    const q = document.getElementById('pick-search'), circle = document.getElementById('pick-circle'), gender = document.getElementById('pick-gender'), only = document.getElementById('pick-only');
    const count = () => document.getElementById('picked-count').textContent = document.querySelectorAll('#pick-list .pick:checked').length;
    function filter() {
        const term = q.value.trim();
        items.forEach(li => {
            const cb = li.querySelector('.pick');
            const show = (!term || li.dataset.name.includes(term)) && (!circle.value || li.dataset.circle === circle.value)
                && (!gender.value || li.dataset.gender === gender.value) && (!only.checked || cb.checked);
            li.classList.toggle('d-none', !show);
        });
    }
    [q, circle, gender, only].forEach(el => el.addEventListener('input', filter));
    document.getElementById('pick-all').addEventListener('click', () => { items.filter(li => !li.classList.contains('d-none')).forEach(li => li.querySelector('.pick').checked = true); count(); });
    document.getElementById('pick-none').addEventListener('click', () => { items.filter(li => !li.classList.contains('d-none')).forEach(li => { const cb = li.querySelector('.pick'); if (!cb.hasAttribute('onclick')) cb.checked = false; }); count(); filter(); });
    document.getElementById('pick-list').addEventListener('change', count);
    count();
})();
</script>
@endpush
