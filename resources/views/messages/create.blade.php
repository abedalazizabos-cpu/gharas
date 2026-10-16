@extends('layouts.app')
@section('title', 'رسالة جديدة')
@section('content')
<x-page-header title="رسالة جديدة" :crumbs="['الرسائل' => route('messages.index'), 'رسالة جديدة']" />
@if ($contacts->isEmpty())
    <div class="card"><x-empty icon="person-x" :message="auth()->user()->isTeacher() ? 'لا يوجد أولياء أمور مرتبطون بطلاب حلقاتك.' : 'لا يوجد محفظ مرتبط بأبنائك حالياً.'" /></div>
@else
<form method="POST" action="{{ route('messages.store') }}" class="card" novalidate>
    @csrf
    <div class="card-body"><div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="recipient_id">{{ auth()->user()->isTeacher() ? 'ولي الأمر' : 'المحفظ' }} <span class="text-danger">*</span></label>
            <select id="recipient_id" name="recipient_id" class="form-select @error('recipient_id') is-invalid @enderror" required>
                <option value="">— اختر —</option>
                @foreach ($contacts as $c)<option value="{{ $c->id }}" @selected(old('recipient_id', $selectedRecipient) == $c->id)>{{ $c->name }}</option>@endforeach
            </select>
            @error('recipient_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="student_id">بخصوص الطالب</label>
            <select id="student_id" name="student_id" class="form-select @error('student_id') is-invalid @enderror">
                <option value="">— عام —</option>
                @foreach ($students as $s)<option value="{{ $s->id }}" @selected(old('student_id', $selectedStudent) == $s->id)>{{ $s->full_name }}</option>@endforeach
            </select>
            @error('student_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <x-field name="subject" label="الموضوع" :value="$subject" required col="col-12" />
        <x-field name="body" label="نص الرسالة" type="textarea" required col="col-12" rows="6" />
    </div></div>
    <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
        <a href="{{ route('messages.index') }}" class="btn btn-light">إلغاء</a>
        <button class="btn btn-primary px-4"><i class="bi bi-send ms-1"></i> إرسال</button>
    </div>
</form>
@endif
@endsection
