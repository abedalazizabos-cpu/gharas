@extends('layouts.app')
@section('title', $announcement->exists ? 'تعديل إعلان' : 'إعلان جديد')
@section('content')
<x-page-header :title="$announcement->exists ? 'تعديل إعلان' : 'إعلان جديد'" :crumbs="['الإعلانات' => route('admin.announcements.index'), $announcement->exists ? 'تعديل' : 'جديد']" />
<form method="POST" action="{{ $announcement->exists ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}" class="card" novalidate>
    @csrf
    @if ($announcement->exists) @method('PUT') @endif
    <div class="card-body"><div class="row g-3">
        <x-field name="title" label="العنوان" :value="$announcement->title" required col="col-md-8" />
        <div class="col-md-4">
            <label class="form-label" for="audience">الفئة المستهدفة <span class="text-danger">*</span></label>
            <select id="audience" name="audience" class="form-select">@foreach (\App\Models\Announcement::AUDIENCES as $k => $v)<option value="{{ $k }}" @selected(old('audience', $announcement->audience) === $k)>{{ $v }}</option>@endforeach</select>
        </div>
        <x-field name="body" label="نص الإعلان" type="textarea" :value="$announcement->body" required col="col-12" rows="6" />
        <x-field name="publish_at" label="تاريخ النشر" type="datetime-local" :value="optional($announcement->publish_at)->format('Y-m-d\TH:i')" col="col-md-4" help="اتركه فارغاً للنشر فوراً." />
        <x-field name="expires_at" label="تاريخ الانتهاء" type="datetime-local" :value="optional($announcement->expires_at)->format('Y-m-d\TH:i')" col="col-md-4" help="اختياري." />
        @unless ($announcement->exists)
            <div class="col-md-4 d-flex align-items-center"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="notify" value="1" id="notify" checked><label class="form-check-label" for="notify">إرسال إشعار للفئة المستهدفة</label></div></div>
        @endunless
    </div></div>
    <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
        <a href="{{ route('admin.announcements.index') }}" class="btn btn-light">إلغاء</a>
        <button class="btn btn-primary px-4"><i class="bi bi-save ms-1"></i> حفظ</button>
    </div>
</form>
@endsection
