@extends('layouts.app')
@section('title', 'حذف شامل: '.$circle->name)
@section('content')
<x-page-header :title="'حذف شامل للحلقة: '.$circle->name" :crumbs="['الحلقات' => route('admin.circles.index'), $circle->name => route('admin.circles.show', $circle), 'حذف شامل']" />
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white"><i class="bi bi-exclamation-triangle-fill"></i> تحذير: سيُحذف كل ما يخص هذه الحلقة</div>
            <div class="card-body">
                <p class="mb-3">عند الموافقة تختفي هذه البيانات من النظام عند جميع المستخدمين:</p>
                <ul class="list-group mb-3">
                    <li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-bullseye"></i> الحلقة نفسها</span><strong>{{ $circle->name }}</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-people"></i> طلاب الحلقة</span><strong>{{ $impact['students'] }}</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-house-heart"></i> أولياء أمور ليس لهم أبناء آخرون</span><strong>{{ $impact['parents'] }}</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-person-badge"></i> المحفظ</span>
                        <strong>@if (! $impact['teacher']) لا يوجد @elseif ($impact['teacher_removed']) {{ $impact['teacher']->name }} (يُحذف حسابه) @else {{ $impact['teacher']->name }} (يبقى، لأن له حلقات أخرى) @endif</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-book"></i> سجلات الحفظ والمراجعة</span><strong>{{ $impact['records'] }}</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-award"></i> التقييمات</span><strong>{{ $impact['assessments'] }}</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-calendar-check"></i> أيام الحضور والغياب</span><strong>{{ $impact['sessions'] }}</strong></li>
                </ul>
                <div class="alert alert-info small mb-3"><i class="bi bi-info-circle"></i> الحذف هنا «حذف مؤقت»: تنتقل البيانات إلى سلة المحذوفات، وتستطيع استرجاعها كاملة، أو حذفها نهائياً من هناك.</div>
                <form method="POST" action="{{ route('admin.trash.circle.remove', $circle) }}">
                    @csrf @method('DELETE')
                    <div class="form-check mb-3">
                        <input class="form-check-input @error('confirm') is-invalid @enderror" type="checkbox" name="confirm" value="1" id="confirm" required>
                        <label class="form-check-label fw-semibold" for="confirm">أوافق على حذف الحلقة وكل ما سبق</label>
                        @error('confirm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash3"></i> نعم، احذف الحلقة بكل بياناتها</button>
                        <a href="{{ route('admin.circles.show', $circle) }}" class="btn btn-outline-secondary">تراجع</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
