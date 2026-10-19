@extends('layouts.app')
@section('title', $record->exists ? 'تعديل سجل تسميع' : 'تسجيل تسميع جديد')
@section('content')
<x-page-header :title="$record->exists ? 'تعديل سجل تسميع' : 'تسجيل تسميع جديد'" :crumbs="['سجل الحفظ' => $back, $record->exists ? 'تعديل' : 'جديد']">
    <a href="{{ $back }}" class="btn btn-light"><i class="bi bi-arrow-right"></i> رجوع</a>
</x-page-header>

@if ($students->isEmpty())
    <div class="card"><x-empty icon="people" message="لا يوجد طلاب نشطون في حلقاتك." /></div>
@else
<form method="POST" action="{{ $action }}" class="card" novalidate>
    @csrf
    @if ($record->exists) @method('PUT') @endif
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="student_id">الطالب <span class="text-danger">*</span></label>
                @if ($record->exists)
                    <input class="form-control" value="{{ $record->student->full_name }}" disabled>
                @else
                    <select id="student_id" name="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
                        <option value="">— اختر الطالب —</option>
                        @foreach ($students->groupBy(fn ($s) => $s->circle?->name ?? 'بدون حلقة') as $circleName => $group)
                            <optgroup label="{{ $circleName }}">
                                @foreach ($group as $s)
                                    <option value="{{ $s->id }}" @selected(old('student_id', $record->student_id) == $s->id)>{{ $s->full_name }} ({{ $s->student_number }})</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('student_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @endif
            </div>
            <x-field name="recorded_on" label="تاريخ التسميع" type="date" :value="optional($record->recorded_on)->format('Y-m-d')" required col="col-md-3" :max="now()->toDateString()" />
            <div class="col-md-3">
                <label class="form-label" for="type">نوع النشاط <span class="text-danger">*</span></label>
                <select id="type" name="type" class="form-select @error('type') is-invalid @enderror">
                    @foreach (\App\Models\MemorizationRecord::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $record->type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label" for="surah_id">السورة <span class="text-danger">*</span></label>
                <select id="surah_id" name="surah_id" class="form-select @error('surah_id') is-invalid @enderror" required>
                    <option value="">— اختر السورة —</option>
                    @foreach ($surahs as $surah)
                        <option value="{{ $surah->id }}" data-ayahs="{{ $surah->ayah_count }}" @selected(old('surah_id', $record->surah_id) == $surah->id)>{{ $surah->id }}. {{ $surah->name }}</option>
                    @endforeach
                </select>
                @error('surah_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text" id="ayah-hint"></div>
            </div>
            <x-field name="from_ayah" label="من آية" type="number" :value="$record->from_ayah" required col="col-6 col-md-2" min="1" />
            <x-field name="to_ayah" label="إلى آية" type="number" :value="$record->to_ayah" required col="col-6 col-md-2" min="1" />
            <div class="col-md-2">
                <label class="form-label" for="pages">المقدار (صفحات) <span class="text-danger">*</span></label>
                <input id="pages" type="number" name="pages" step="0.25" min="0.25" max="604" value="{{ old('pages', $record->pages !== null ? \App\Support\Pages::num($record->pages) : '') }}" class="form-control @error('pages') is-invalid @enderror" required>
                @error('pages')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2 align-items-center small">
                    <span class="text-muted">اختيار سريع للمقدار:</span>
                    @foreach (['0.25' => 'ربع صفحة', '0.5' => 'نصف صفحة', '0.75' => 'ثلاثة أرباع', '1' => 'صفحة', '2' => 'صفحتان', '3' => '3 صفحات', '5' => '5 صفحات'] as $v => $label)
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-pages="{{ $v }}">{{ $label }}</button>
                    @endforeach
                    <span class="text-muted">أو اكتب أي عدد من الصفحات (بالأرباع).</span>
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label" for="score">الدرجة (من 100) <span class="text-danger">*</span></label>
                <div class="input-group has-validation">
                    <input id="score" type="number" name="score" min="0" max="100" value="{{ old('score', $record->score) }}" class="form-control @error('score') is-invalid @enderror" required>
                    <span class="input-group-text"><span id="grade-preview"></span></span>
                    @error('score')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <x-field name="mistakes_count" label="عدد الأخطاء" type="number" :value="$record->mistakes_count ?? 0" col="col-md-3" min="0" />
            <div class="col-md-6 d-flex align-items-end">
                <div class="form-check form-switch mb-2">
                    <input type="hidden" name="shared_with_parent" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="shared_with_parent" name="shared_with_parent" value="1" @checked(old('shared_with_parent', $record->shared_with_parent ?? true))>
                    <label class="form-check-label" for="shared_with_parent">إظهار السجل والملاحظات لولي الأمر</label>
                </div>
            </div>
            <x-field name="notes" label="الأخطاء والملاحظات" type="textarea" :value="$record->notes" col="col-md-6" />
            <x-field name="recommendations" label="توصيات المحفظ" type="textarea" :value="$record->recommendations" col="col-md-6" />
        </div>
    </div>
    <div class="card-footer bg-transparent d-flex flex-wrap gap-2 justify-content-end">
        @unless ($record->exists)
            <button name="add_another" value="1" class="btn btn-outline-primary"><i class="bi bi-plus-lg"></i> حفظ وإضافة سجل آخر</button>
        @endunless
        <button class="btn btn-primary px-4"><i class="bi bi-save ms-1"></i> حفظ</button>
    </div>
</form>
@endif
@endsection
