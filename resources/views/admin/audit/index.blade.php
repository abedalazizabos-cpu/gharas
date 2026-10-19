@extends('layouts.app')
@section('title', 'سجل العمليات')
@section('content')
<x-page-header title="سجل العمليات الإدارية" :crumbs="['سجل العمليات']" />
<form method="GET" class="filter-bar row g-2 align-items-end" data-no-loading>
    <div class="col-6 col-md-3"><label class="form-label small">المستخدم</label><select name="user" class="form-select"><option value="">الكل</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3"><label class="form-label small">نوع العملية</label><select name="action" class="form-select"><option value="">الكل</option>@foreach ($actions as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ \App\Support\AuditPresenter::group($a) }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label small">من</label><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
    <div class="col-6 col-md-2"><label class="form-label small">إلى</label><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> تصفية</button></div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 small">
            <thead><tr><th>الوقت</th><th>المستخدم</th><th>العملية</th><th>الوصف</th><th>التغييرات</th><th>الجهاز (IP)</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="text-nowrap">{{ $log->created_at->format('Y/m/d H:i') }}</td>
                    <td>{{ $log->user?->nameFor(auth()->user()) ?? 'النظام' }}</td>
                    <td class="text-nowrap"><span class="badge bg-{{ \App\Support\AuditPresenter::color($log->action) }}-subtle text-{{ \App\Support\AuditPresenter::color($log->action) }}-emphasis">{{ \App\Support\AuditPresenter::action($log->action) }}</span></td>
                    <td>{{ $log->description }}</td>
                    <td style="min-width:240px;max-width:360px">
                        @php($changes = \App\Support\AuditPresenter::changes($log))
                        @forelse ($changes as $c)
                            <div class="mb-1">
                                <span class="fw-semibold">{{ $c['label'] }}:</span>
                                @if ($c['old'] !== null && $c['new'] !== null)
                                    <span class="text-muted">من</span> <span class="text-danger">{{ \Illuminate\Support\Str::limit($c['old'], 60) }}</span>
                                    <span class="text-muted">إلى</span> <span class="text-success">{{ \Illuminate\Support\Str::limit($c['new'], 60) }}</span>
                                @else
                                    <span>{{ \Illuminate\Support\Str::limit($c['new'] ?? $c['old'], 60) }}</span>
                                @endif
                            </div>
                        @empty — @endforelse
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
