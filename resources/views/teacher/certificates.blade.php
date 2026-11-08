@extends('layouts.app')
@section('title', 'شهادات طلابي')
@section('content')
<x-page-header title="شهادات طلابي" :crumbs="['شهادات طلابي']" />

<div class="alert alert-light border small"><i class="bi bi-info-circle text-success"></i> الشهادات التي أصدرتها إدارة المركز لطلاب حلقاتك ولطلاب الدورات التي تعطيها في قسم السنة. يمكنك تحميل أي شهادة PDF.</div>

<form method="GET" class="card card-body mb-3" data-no-loading>
    <div class="row g-2 align-items-end">
        <div class="col-md-8"><label class="form-label small">بحث</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="اسم الطالب"></div>
        <div class="col-md-4"><button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> بحث</button></div>
    </div>
</form>

<div class="card"><div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead><tr><th>الطالب</th><th>الشهادة</th><th>عن</th><th>صدرت</th><th></th></tr></thead>
        <tbody>
        @forelse ($certificates as $c)
            <tr>
                <td class="fw-semibold">{{ $c->content['student'] }}<small class="text-muted d-block">{{ $c->student?->circle?->name }}</small></td>
                <td class="small"><i class="bi bi-{{ $c->icon() }} text-success"></i> {{ $c->kindLabel() }}</td>
                <td>{{ $c->subject() }}</td>
                <td class="small text-nowrap">{{ $c->issued_on?->format('Y/m/d') }}<span class="text-muted d-block">{{ $c->number }}</span></td>
                <td><a href="{{ route('certificates.download', $c) }}" class="btn btn-sm btn-outline-success" data-no-loading><i class="bi bi-file-earmark-pdf"></i> PDF</a></td>
            </tr>
        @empty
            <tr><td colspan="5"><x-empty icon="award" message="لم تصدر شهادات لطلابك بعد." /></td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $certificates->links() }}</div>
@endsection
