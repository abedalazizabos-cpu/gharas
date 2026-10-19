@extends('layouts.app')
@section('title', 'ولي الأمر: '.$parent->name)
@section('content')
<x-page-header :title="$parent->name" :crumbs="['أولياء الأمور' => route('admin.parents.index'), 'ملف ولي الأمر']">
    <a href="{{ route('admin.parents.edit', $parent) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> تعديل</a>
    @if ($parent->status === 'active')
        <x-delete-form :action="route('admin.parents.status', $parent)" method="PATCH" label="إيقاف الحساب" icon="pause-circle" class="btn btn-outline-warning" title="إيقاف الحساب" message="لن يتمكن ولي الأمر من الدخول إلى النظام."><input type="hidden" name="status" value="suspended"></x-delete-form>
    @else
        <x-delete-form :action="route('admin.parents.status', $parent)" method="PATCH" label="تفعيل الحساب" icon="check-circle" class="btn btn-outline-success" title="تفعيل الحساب" message="سيتمكن ولي الأمر من الدخول إلى النظام."><input type="hidden" name="status" value="active"></x-delete-form>
    @endif
    <x-delete-form :action="route('admin.parents.destroy', $parent)" class="btn btn-outline-danger" message="سيتم حذف حساب ولي الأمر وفك ارتباطه بالأبناء." />
</x-page-header>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card"><div class="card-body small">
            <dl class="row mb-0">
                <dt class="col-5">الجوال (اسم الدخول)</dt><dd class="col-7" dir="ltr">{{ $parent->phone ?? '—' }}</dd>
                <dt class="col-5">البريد</dt><dd class="col-7" dir="ltr">{{ $parent->email ?? '—' }}</dd>
                <dt class="col-5">جوال بديل</dt><dd class="col-7" dir="ltr">{{ $parent->parentProfile?->alt_phone ?? '—' }}</dd>
                <dt class="col-5">العنوان</dt><dd class="col-7">{{ $parent->parentProfile?->address ?? '—' }}</dd>
                <dt class="col-5">الحالة</dt><dd class="col-7"><x-status-badge :status="$parent->status" :label="$parent->statusLabel()" /></dd>
                <dt class="col-5">آخر دخول</dt><dd class="col-7">{{ $parent->last_login_at?->diffForHumans() ?? 'لم يدخل بعد' }}</dd>
            </dl>
        </div></div>
        <div class="card mt-3"><div class="card-body">
            <h6 class="fw-bold mb-2"><i class="bi bi-eye ms-1 text-success"></i> نسبة متابعة الأبناء</h6>
            @include('shared._follow-rate', ['rate' => $follow['overall'], 'bar' => true])
            <p class="small text-muted mt-2 mb-0">نسبة السجلات اليومية التي اطّلع عليها خلال آخر {{ \App\Services\ParentFollowService::WINDOW_DAYS }} يوماً. يُحسب السجل مُطّلعاً عليه عندما يفتح ولي الأمر صفحة ابنه.</p>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">الأبناء المرتبطون ({{ $parent->children->count() }})</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>الطالب</th><th>الصلة</th><th>الحلقة</th><th>المحفظ</th><th>المتابعة</th><th>الحالة</th></tr></thead>
                    <tbody>
                    @forelse ($parent->children as $c)
                        <tr>
                            <td><a href="{{ route('admin.students.show', $c) }}">{{ $c->full_name }}</a></td>
                            <td>{{ ['father' => 'الأب', 'mother' => 'الأم', 'guardian' => 'ولي آخر'][$c->pivot->relationship] ?? '' }}</td>
                            <td>{{ $c->circle?->name ?? '—' }}</td>
                            <td>{{ $c->circle?->teacher?->name ?? '—' }}</td>
                            <td>@include('shared._follow-rate', ['rate' => $follow['children'][$c->id] ?? ['seen' => 0, 'total' => 0, 'percent' => null]])</td>
                            <td><x-status-badge :status="$c->status" :label="$c->statusLabel()" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty message="لا يوجد أبناء مرتبطون بهذا الحساب." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
