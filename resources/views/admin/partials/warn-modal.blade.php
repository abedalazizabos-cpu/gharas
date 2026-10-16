{{-- Issue a warning to $teacher; $circles lists their circles, $circleId preselects one. --}}
<div class="modal fade" id="warnModal" tabindex="-1" aria-labelledby="warnModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.teachers.warn', $teacher) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="warnModalTitle"><i class="bi bi-exclamation-triangle text-warning"></i> إنذار المحفظ {{ $teacher->name }}</h5>
                <button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">النوع</label>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach (\App\Models\TeacherWarning::LEVELS as $k => [$label, $color])
                        <input type="radio" class="btn-check" name="level" id="lvl-{{ $k }}" value="{{ $k }}" @checked(old('level', 'notice') === $k)>
                        <label class="btn btn-sm btn-outline-{{ $color === 'orange' ? 'danger' : $color }}" for="lvl-{{ $k }}">{{ $label }}</label>
                    @endforeach
                </div>
                @if ($circles->isNotEmpty())
                    <label for="warn-circle" class="form-label">الحلقة (اختياري)</label>
                    <select name="circle_id" id="warn-circle" class="form-select mb-3">
                        <option value="">عام، لا يخص حلقة بعينها</option>
                        @foreach ($circles as $c)<option value="{{ $c->id }}" @selected(old('circle_id', $circleId ?? null) == $c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                @endif
                <label for="warn-reason" class="form-label">السبب <span class="text-danger">*</span></label>
                <textarea name="reason" id="warn-reason" rows="4" class="form-control" required maxlength="1000" placeholder="مثال: تكرر التأخر عن موعد الحلقة ثلاث مرات هذا الشهر">{{ old('reason') }}</textarea>
                <div class="form-text">يصل للمحفظ إشعار ويظهر له الإنذار في لوحته حتى يؤكد اطلاعه عليه.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button class="btn btn-warning"><i class="bi bi-send"></i> إرسال الإنذار</button>
            </div>
        </form>
    </div>
</div>
@if ($errors->has('reason') || $errors->has('level'))
    <script>document.addEventListener('DOMContentLoaded', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('warnModal')).show());</script>
@endif
