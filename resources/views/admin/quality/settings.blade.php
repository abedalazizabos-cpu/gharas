@extends('layouts.app')
@section('title', 'أعضاء لجنة الجودة والضوابط')
@section('content')
<x-page-header title="أعضاء لجنة الجودة والضوابط" :crumbs="['لجنة الجودة' => route('admin.quality.index'), 'الأعضاء والضوابط']" />

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-people text-success ms-1"></i> مشايخ لجنة الجودة</div>
            <div class="card-body small text-muted pb-0">اختر المحفظين الذين يختبرون الطلاب. يظهر لكل منهم قسم «اختبارات الجودة» يرصد فيه الدرجات.</div>
            <ul class="list-group list-group-flush">
                @forelse ($teachers as $t)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>{{ $t->name }} <small class="text-muted">· {{ $t->circles_count }} حلقة</small> @if ($t->is_quality_examiner)<span class="badge text-bg-success">عضو اللجنة</span>@endif</div>
                        <form method="POST" action="{{ route('admin.quality.examiner', $t) }}">@csrf
                            <input type="hidden" name="on" value="{{ $t->is_quality_examiner ? 0 : 1 }}">
                            <button class="btn btn-sm {{ $t->is_quality_examiner ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $t->is_quality_examiner ? 'إخراج من اللجنة' : 'إضافة إلى اللجنة' }}</button>
                        </form>
                    </li>
                @empty
                    <li class="list-group-item"><x-empty icon="person-badge" message="لا يوجد محفظون بعد." /></li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-sliders text-success ms-1"></i> ضوابط تقييم الاختبار</div>
            <div class="card-body small text-muted pb-0">يرصد شيخ الجودة درجة لكل ضابط مفعّل. تُحفظ الدرجة العظمى مع كل اختبار، فلا تتغير النتائج السابقة إذا عدّلت الضوابط.</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>الضابط</th><th style="width:110px">الدرجة العظمى</th><th style="width:90px">الترتيب</th><th style="width:70px">مفعّل</th><th style="width:120px"></th></tr></thead>
                    <tbody>
                    @foreach ($criteria as $c)
                        <tr>
                            <td><input form="qc-{{ $c->id }}" name="name" value="{{ $c->name }}" class="form-control form-control-sm" required></td>
                            <td><input form="qc-{{ $c->id }}" type="number" name="max_score" value="{{ $c->max_score }}" min="1" max="100" class="form-control form-control-sm" required></td>
                            <td><input form="qc-{{ $c->id }}" type="number" name="sort_order" value="{{ $c->sort_order }}" min="0" class="form-control form-control-sm"></td>
                            <td><div class="form-check form-switch"><input form="qc-{{ $c->id }}" class="form-check-input" type="checkbox" name="is_active" value="1" @checked($c->is_active)></div></td>
                            <td class="table-actions text-nowrap">
                                <button form="qc-{{ $c->id }}" class="btn btn-sm btn-primary"><i class="bi bi-save"></i></button>
                                <x-delete-form :action="route('admin.quality.criteria.destroy', $c)" label="" message="إن كان الضابط مستخدماً في اختبارات سابقة فسيُوقف بدلاً من حذفه." />
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @foreach ($criteria as $c)<form method="POST" action="{{ route('admin.quality.criteria.update', $c) }}" id="qc-{{ $c->id }}">@csrf @method('PUT')</form>@endforeach
            <div class="card-footer bg-transparent">
                <div class="small text-muted mb-2">مجموع الدرجات العظمى للضوابط المفعّلة: {{ $criteria->where('is_active', true)->sum('max_score') }}</div>
                <form method="POST" action="{{ route('admin.quality.criteria.store') }}" class="row g-2 align-items-end">@csrf
                    <x-field name="name" label="ضابط جديد" required col="col-md-5" />
                    <x-field name="max_score" label="الدرجة العظمى" type="number" :value="10" required col="col-md-3" min="1" max="100" />
                    <input type="hidden" name="sort_order" value="{{ $criteria->count() + 1 }}"><input type="hidden" name="is_active" value="1">
                    <div class="col-md-4"><button class="btn btn-outline-primary w-100"><i class="bi bi-plus-lg"></i> إضافة</button></div>
                </form>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.quality.pass-score') }}" class="card card-body mb-3">@csrf @method('PUT')
            <div class="row g-2 align-items-end">
                <x-field name="quality_pass_score" label="درجة النجاح في الاختبار (%)" type="number" :value="$passScore" required col="col-md-6" min="1" max="100" help="من يحصل على أقل منها يظهر «لم يجتز» ويبقى في التنبيهات حتى يُعاد اختباره." />
                <div class="col-md-6"><button class="btn btn-primary w-100"><i class="bi bi-save"></i> حفظ</button></div>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3" id="stages">
    <div class="card-header"><i class="bi bi-collection text-success ms-1"></i> مراحل الاختبارات المجتمعة (بالترتيب)</div>
    <div class="card-body small text-muted pb-0">يبدأ الطلاب من الجزء 30 نزولاً. يستحق الطالب المرحلة حين ينهي كل أجزائها. الترتيب المقترح: 30-28، 30-26، 25-23، 25-21، 30-21 (العشرة الأولى)، ثم بالطريقة نفسها في العشرة الثانية والثالثة، ثم المصحف كاملاً. يمكنك التعديل أو الإضافة.</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th style="width:90px">الترتيب</th><th style="width:110px">من الجزء</th><th style="width:110px">إلى الجزء</th><th>الاسم (اختياري)</th><th style="width:70px">مفعّل</th><th style="width:120px"></th></tr></thead>
            <tbody>
            @foreach ($stages as $st)
                <tr class="{{ $st->is_active ? '' : 'text-muted' }}">
                    <td><input form="st-{{ $st->id }}" type="number" name="sort_order" value="{{ $st->sort_order }}" min="0" class="form-control form-control-sm"></td>
                    <td><input form="st-{{ $st->id }}" type="number" name="from_juz" value="{{ $st->from_juz }}" min="1" max="30" class="form-control form-control-sm" required></td>
                    <td><input form="st-{{ $st->id }}" type="number" name="to_juz" value="{{ $st->to_juz }}" min="1" max="30" class="form-control form-control-sm" required></td>
                    <td><input form="st-{{ $st->id }}" name="name" value="{{ $st->name }}" class="form-control form-control-sm" placeholder="مثال: العشرة الأولى"></td>
                    <td><div class="form-check form-switch"><input form="st-{{ $st->id }}" class="form-check-input" type="checkbox" name="is_active" value="1" @checked($st->is_active)></div></td>
                    <td class="table-actions text-nowrap">
                        <button form="st-{{ $st->id }}" class="btn btn-sm btn-primary"><i class="bi bi-save"></i></button>
                        <x-delete-form :action="route('admin.quality.stages.destroy', $st)" label="" message="إن كانت للمرحلة اختبارات سابقة فستُوقف بدلاً من حذفها." />
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @foreach ($stages as $st)<form method="POST" action="{{ route('admin.quality.stages.update', $st) }}" id="st-{{ $st->id }}">@csrf @method('PUT')</form>@endforeach
    <div class="card-footer bg-transparent">
        <form method="POST" action="{{ route('admin.quality.stages.store') }}" class="row g-2 align-items-end">@csrf
            <x-field name="from_juz" label="من الجزء" type="number" required col="col-md-2" min="1" max="30" />
            <x-field name="to_juz" label="إلى الجزء" type="number" required col="col-md-2" min="1" max="30" />
            <x-field name="name" label="الاسم (اختياري)" col="col-md-4" />
            <input type="hidden" name="sort_order" value="{{ ($stages->max('sort_order') ?? 0) + 1 }}"><input type="hidden" name="is_active" value="1">
            <div class="col-md-4"><button class="btn btn-outline-primary w-100"><i class="bi bi-plus-lg"></i> إضافة مرحلة</button></div>
        </form>
    </div>
</div>
@endsection
