@extends('layouts.app')
@section('title', 'سجل العمليات')
@section('content')
<x-page-header title="سجل العمليات الإدارية" :crumbs="['سجل العمليات']" />
<form method="GET" class="filter-bar row g-2 align-items-end" data-no-loading>
    <div class="col-6 col-md-3"><label class="form-label small">المستخدم</label><select name="user" class="form-select"><option value="">الكل</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3"><label class="form-label small">نوع العملية</label><select name="action" class="form-select"><option value="">الكل</option>@foreach ($actions as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label small">من</label><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
    <div class="col-6 col-md-2"><label class="form-label small">إلى</label><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> تصفية</button></div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 small">
            <thead><tr><th>الوقت</th><th>المستخدم</th><th>العملية</th><th>الوصف</th><th>التغييرات</th><th>IP</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="text-nowrap">{{ $log->created_at->format('Y/m/d H:i') }}</td>
                    <td>{{ $log->user?->name ?? 'النظام' }}</td>
                    <td><code>{{ $log->action }}</code></td>
                    <td>{{ $log->description }}</td>
                    <td style="max-width:320px">
                        @if ($log->old_values || $log->new_values)
                            <details><summary class="text-primary">عرض</summary>
                                @if ($log->old_values)<div class="text-danger" dir="ltr"><small>{{ json_encode($log->old_values, JSON_UNESCAPED_UNICODE) }}</small></div>@endif
                                @if ($log->new_values)<div class="text-success" dir="ltr"><small>{{ json_encode($log->new_values, JSON_UNESCAPED_UNICODE) }}</small></div>@endif
                            </details>
                        @else — @endif
                    </td>
                    <td dir="ltr" class="text-muted">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="journal-text" message="لا توجد عمليات مسجلة." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
