@extends('layouts.app')
@section('title', 'الإعلانات')
@section('content')
<x-page-header title="الإعلانات" :crumbs="['الإعلانات']">
    <a href="{{ route('admin.announcements.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> إعلان جديد</a>
</x-page-header>
<form method="GET" class="filter-bar row g-2" data-no-loading>
    <div class="col-md-4"><select name="audience" class="form-select" onchange="this.form.submit()"><option value="">كل الفئات</option>@foreach (\App\Models\Announcement::AUDIENCES as $k => $v)<option value="{{ $k }}" @selected(request('audience') === $k)>{{ $v }}</option>@endforeach</select></div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>العنوان</th><th>الفئة</th><th>النشر</th><th>الانتهاء</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
            @forelse ($announcements as $a)
                <tr>
                    <td><strong>{{ $a->title }}</strong><br><small class="text-muted">{{ \Illuminate\Support\Str::limit($a->body, 80) }}</small></td>
                    <td><span class="badge bg-gold-soft text-dark">{{ $a->audienceLabel() }}</span></td>
                    <td class="small">{{ $a->publish_at?->format('Y/m/d H:i') ?? 'فوري' }}</td>
                    <td class="small">{{ $a->expires_at?->format('Y/m/d H:i') ?? '—' }}</td>
                    <td>@if ($a->isCurrent())<span class="badge bg-success">منشور</span>@elseif ($a->publish_at?->isFuture())<span class="badge bg-info">مجدول</span>@else<span class="badge bg-secondary">منتهي</span>@endif</td>
                    <td class="table-actions">
                        <a href="{{ route('admin.announcements.edit', $a) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <x-delete-form :action="route('admin.announcements.destroy', $a)" label="" message="سيتم حذف الإعلان." />
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="megaphone" message="لا توجد إعلانات." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $announcements->links() }}</div>
@endsection
