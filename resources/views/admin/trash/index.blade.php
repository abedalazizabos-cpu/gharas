@extends('layouts.app')
@section('title', 'سلة المحذوفات')
@section('content')
<x-page-header title="سلة المحذوفات" :crumbs="['سلة المحذوفات']" />
<p class="text-muted">كل ما يُحذف من الحلقات والطلاب والحسابات يبقى هنا حتى تسترجعه أو تحذفه نهائياً. كل صف عملية حذف واحدة بتاريخها.</p>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-trash3"></i> عمليات الحذف</span>
        @if ($batches->isNotEmpty())
            <x-delete-form :action="route('admin.trash.purge-all')" label="إفراغ السلة نهائياً" icon="x-octagon" class="btn btn-sm btn-outline-danger" title="إفراغ السلة" message="سيُحذف كل ما في السلة نهائياً، ولا يمكن استرجاعه بعد ذلك." />
        @endif
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>تاريخ الحذف</th><th>ما يحتويه</th><th></th></tr></thead>
            <tbody>
            @forelse ($batches as $b)
                <tr>
                    <td class="text-nowrap small">{{ \Illuminate\Support\Carbon::parse($b['at'])->format('Y/m/d h:i a') }}</td>
                    <td>
                        @if ($b['circles']->isNotEmpty())<div><strong>الحلقات ({{ $b['circles']->count() }}):</strong> {{ $b['circles']->take(5)->implode('، ') }}@if ($b['circles']->count() > 5) …@endif</div>@endif
                        <div class="small text-muted">
                            @if ($b['students']) {{ $b['students'] }} طالب · @endif
                            @foreach ($b['roles'] as $role => $n){{ $n }} {{ \App\Models\User::ROLES[$role] ?? $role }} · @endforeach
                            @if ($b['records']) {{ $b['records'] }} سجل وتقييم @endif
                        </div>
                        @if ($b['circles']->isEmpty() && $b['names']->isNotEmpty())<div class="small">{{ $b['names']->take(5)->implode('، ') }}</div>@endif
                    </td>
                    <td class="text-nowrap text-end">
                        <x-delete-form :action="route('admin.trash.restore')" method="POST" label="استرجاع" icon="arrow-counterclockwise" class="btn btn-sm btn-outline-success" title="استرجاع" message="سيعود كل ما في هذه العملية كما كان، بطلابه وسجلاته."><input type="hidden" name="at" value="{{ $b['at'] }}"></x-delete-form>
                        <x-delete-form :action="route('admin.trash.purge')" label="حذف نهائي" icon="x-octagon" title="حذف نهائي" message="سيُحذف كل ما في هذه العملية نهائياً، ولا يمكن استرجاعه."><input type="hidden" name="at" value="{{ $b['at'] }}"></x-delete-form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3"><x-empty icon="trash3" message="السلة فارغة." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card border-danger">
    <div class="card-header bg-danger text-white"><i class="bi bi-exclamation-octagon-fill"></i> حذف جميع بيانات التجربة</div>
    <div class="card-body">
        <p>لبدء البيانات الرسمية: يحذف <strong>كل الحلقات والطلاب وسجلاتهم، وكل حسابات المديرين والمحفظين وأولياء الأمور والزوار والداعمين، والإعلانات</strong>.</p>
        <p class="small text-muted mb-3">يبقى حسابك وحسابات المدير الأعلى، والسور، والإعدادات والشعار، والصلاحيات. تنتقل البيانات إلى السلة أعلاه. بعد أن تتأكد، اضغط «حذف نهائي» عليها، حتى تستطيع استخدام نفس أرقام الجوال ورموز الحلقات في البيانات الرسمية.</p>
        <form method="POST" action="{{ route('admin.trash.wipe') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-8">
                <label class="form-label" for="phrase">للتأكيد اكتب: <strong class="text-danger">{{ $phrase }}</strong></label>
                <input id="phrase" name="phrase" class="form-control @error('phrase') is-invalid @enderror" autocomplete="off" required data-phrase="{{ $phrase }}">
                @error('phrase')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4"><button type="submit" id="wipe-btn" class="btn btn-danger w-100" disabled><i class="bi bi-trash3"></i> احذف بيانات التجربة</button></div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
document.getElementById('phrase')?.addEventListener('input', e => {
    document.getElementById('wipe-btn').disabled = e.target.value.trim() !== e.target.dataset.phrase;
});
</script>
@endpush
