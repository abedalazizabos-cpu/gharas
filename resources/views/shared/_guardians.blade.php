{{-- Guardian accounts of a student: edit name/mobile and set a new password. Needs $student (with parents) and $prefix (admin|teacher). --}}
@if (session('guardian_notice'))
    <div class="alert alert-{{ str_contains(session('guardian_notice'), 'كلمة') ? 'warning' : 'success' }} d-flex gap-2 align-items-start" role="alert">
        <i class="bi bi-person-check fs-5"></i><div>{{ session('guardian_notice') }}</div>
    </div>
@endif
@php($canEdit = $prefix === 'admin' || auth()->user()->hasPermission('teacher.students.edit'))
@php($canPassword = $prefix === 'admin' || auth()->user()->hasPermission('teacher.guardian.password'))
@php($followRates = app(\App\Services\ParentFollowService::class)->rates($student->parents->pluck('id')))
<div class="card mb-3" id="guardians">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-people ms-1"></i> ولي الأمر</span>
        @if ($canEdit)<a href="{{ route($prefix.'.students.edit', $student) }}#guardian-box" class="btn btn-sm btn-outline-primary"><i class="bi bi-person-plus"></i> ربط ولي أمر</a>@endif
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>الاسم</th><th>الجوال (اسم الدخول)</th><th>صلة القرابة</th><th>نسبة المتابعة</th><th class="text-end">إجراءات</th></tr></thead>
            <tbody>
            @forelse ($student->parents as $p)
                <tr>
                    <td class="fw-semibold">{{ $p->name }}</td>
                    <td dir="ltr" class="text-end">{{ $p->phone }}</td>
                    <td>{{ ['father' => 'الأب', 'mother' => 'الأم', 'guardian' => 'ولي أمر آخر'][$p->pivot->relationship ?? ''] ?? '—' }}</td>
                    <td>@include('shared._follow-rate', ['rate' => $followRates[$p->id] ?? ['seen' => 0, 'total' => 0, 'percent' => null]])</td>
                    <td class="text-end">@if ($canEdit)<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#guardian-{{ $p->id }}"><i class="bi bi-pencil"></i> {{ $canPassword ? 'البيانات وكلمة السر' : 'تعديل البيانات' }}</button>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-danger small">لا يوجد ولي أمر مرتبط. استخدم «ربط ولي أمر».</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@foreach ($canEdit ? $student->parents : [] as $p)
    @php($mine = (string) old('guardian_id') === (string) $p->id)
    <div class="modal fade" id="guardian-{{ $p->id }}" tabindex="-1">
        <div class="modal-dialog"><form method="POST" action="{{ route($prefix.'.students.guardians.update', [$student, $p]) }}" class="modal-content" novalidate>
            @csrf @method('PUT')
            <input type="hidden" name="guardian_id" value="{{ $p->id }}">
            <div class="modal-header"><h5 class="modal-title">ولي الأمر: {{ $p->name }}</h5><button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="gn-{{ $p->id }}">الاسم</label>
                    <input id="gn-{{ $p->id }}" name="name" class="form-control @if ($mine) @error('name') is-invalid @enderror @endif" value="{{ $mine ? old('name') : $p->name }}" required>
                    @if ($mine) @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
                </div>
                <div class="mb-3">
                    <label class="form-label" for="gp-{{ $p->id }}">رقم الجوال (يدخل به إلى النظام)</label>
                    <input id="gp-{{ $p->id }}" name="phone" dir="ltr" inputmode="numeric" class="form-control @if ($mine) @error('phone') is-invalid @enderror @endif" value="{{ $mine ? old('phone') : $p->phone }}" required>
                    @if ($mine) @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
                </div>
                @if ($canPassword)
                <label class="form-label" for="gw-{{ $p->id }}">كلمة سر جديدة</label>
                <div class="input-group">
                    <input id="gw-{{ $p->id }}" name="password" type="text" dir="ltr" autocomplete="off" class="form-control @if ($mine) @error('password') is-invalid @enderror @endif" placeholder="اتركها فارغة لعدم التغيير">
                    <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('gw-{{ $p->id }}').value=String(Math.floor(10000000+Math.random()*90000000))">توليد</button>
                    @if ($mine) @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
                </div>
                <div class="form-text">لا يمكن عرض كلمة السر الحالية لأنها محفوظة مشفّرة، لكن يمكنك تعيين كلمة جديدة (6 أحرف أو أرقام على الأقل) وتسليمها لولي الأمر.</div>
                @endif
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button><button class="btn btn-primary">حفظ</button></div>
        </form></div>
    </div>
    @if ($mine && $errors->any())
        <script>document.addEventListener('DOMContentLoaded', function () { bootstrap.Modal.getOrCreateInstance(document.getElementById('guardian-{{ $p->id }}')).show(); });</script>
    @endif
@endforeach
