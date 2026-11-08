@extends('layouts.app')
@section('title', 'قسم السنة')
@section('content')
<x-page-header title="قسم السنة والدورات" :crumbs="['قسم السنة']">
    <a href="{{ route('admin.sunnah.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> دورة جديدة</a>
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><x-stat-card icon="journal-bookmark" label="كل الدورات" :value="$counts->sum()" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="play-circle" label="دورات قائمة" :value="$counts['running'] ?? 0" color="blue" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="calendar-plus" label="لم تبدأ بعد" :value="$counts['planned'] ?? 0" color="gold" /></div>
    <div class="col-6 col-xl-3"><x-stat-card icon="check2-circle" label="دورات منتهية" :value="$counts['finished'] ?? 0" /></div>
</div>

<div class="alert alert-light border small"><i class="bi bi-info-circle text-success"></i> أنشئ الدورة وسمّها واكتب أهدافها، واختر الشيخ الذي يعطيها والطلاب المشاركين من كل طلاب المركز (لا يشترط أن يكونوا من حلقته)، ثم ضع ضوابط تقييمها. يرصد الشيخ الدرجات من حسابه.</div>

<form method="GET" class="card card-body mb-3" data-no-loading>
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small">بحث</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="اسم الدورة"></div>
        <div class="col-md-3"><label class="form-label small">المجال</label><select name="category" class="form-select"><option value="">الكل</option>@foreach (\App\Models\SunnahCourse::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label small">الحالة</label><select name="status" class="form-select"><option value="">الكل</option>@foreach (\App\Models\SunnahCourse::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label small">الشيخ</label><select name="teacher" class="form-select"><option value="">الكل</option>@foreach ($teachers as $t)<option value="{{ $t->id }}" @selected((string) request('teacher') === (string) $t->id)>{{ $t->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="bi bi-funnel"></i> تصفية</button></div>
    </div>
</form>

<div class="card"><div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead><tr><th>الدورة</th><th>المجال</th><th>الشيخ</th><th>المدة والموعد</th><th>الطلاب</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        @forelse ($courses as $c)
            <tr>
                <td><a href="{{ route('admin.sunnah.show', $c) }}" class="fw-semibold"><i class="bi bi-{{ $c->categoryIcon() }} text-success ms-1"></i>{{ $c->title }}</a></td>
                <td class="small">{{ $c->categoryLabel() }}</td>
                <td>{{ $c->teacher?->name ?? '—' }}</td>
                <td class="small">{{ $c->periodLabel() }}@if ($c->schedule)<div class="text-muted">{{ $c->schedule }}</div>@endif</td>
                <td>{{ $c->enrollments_count }} <small class="text-muted d-block">قُيّم {{ $c->graded_count }}</small></td>
                <td><span class="badge text-bg-{{ $c->statusColor() }}">{{ $c->statusLabel() }}</span></td>
                <td class="table-actions text-nowrap">
                    <a href="{{ route('admin.sunnah.grade', $c) }}" class="btn btn-sm btn-outline-success" title="رصد الدرجات"><i class="bi bi-pencil-square"></i></a>
                    <a href="{{ route('admin.sunnah.edit', $c) }}" class="btn btn-sm btn-outline-secondary" title="تعديل"><i class="bi bi-pencil"></i></a>
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty icon="journal-bookmark" message="لا توجد دورات بعد. أنشئ أول دورة لقسم السنة." /></td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $courses->links() }}</div>
@endsection
