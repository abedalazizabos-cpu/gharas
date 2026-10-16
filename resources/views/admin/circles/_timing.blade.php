{{-- Circle timing fields (clock / after a prayer / free text). Shared by the admin and teacher circle forms. --}}
            @php($timeType = old('time_type', $circle->time_type ?? 'clock'))
            <div class="col-12">
                <label class="form-label">موعد الحلقة <span class="text-danger">*</span></label>
                <div class="d-flex flex-wrap gap-2" id="time-type">
                    @foreach (\App\Models\Circle::TIME_TYPES as $k => $v)
                        <input type="radio" class="btn-check" name="time_type" value="{{ $k }}" id="tt-{{ $k }}" @checked($timeType === $k)>
                        <label class="btn btn-outline-primary btn-sm" for="tt-{{ $k }}">{{ $v }}</label>
                    @endforeach
                </div>
                <div class="form-text">{{ ($timingHelp ?? null) ?: 'يحدد المدير الموعد بحرية، ويمكن أن تتداخل مواعيد حلقات المحفظ الواحد.' }}</div>
            </div>
            <div class="col-12 row g-3 m-0 p-0" data-time="clock">
                <x-field name="start_time" label="وقت البداية" type="time" :value="$circle->start_time ? substr($circle->start_time, 0, 5) : null" required col="col-6 col-md-3" />
                <x-field name="end_time" label="وقت النهاية" type="time" :value="$circle->end_time ? substr($circle->end_time, 0, 5) : null" required col="col-6 col-md-3" />
            </div>
            <div class="col-12 row g-3 m-0 p-0" data-time="prayer">
                <div class="col-6 col-md-3">
                    <label class="form-label" for="prayer">بعد صلاة <span class="text-danger">*</span></label>
                    <select id="prayer" name="prayer" class="form-select @error('prayer') is-invalid @enderror">
                        <option value="">— اختر —</option>
                        @foreach (\App\Models\Circle::PRAYERS as $k => $p)<option value="{{ $k }}" @selected(old('prayer', $circle->prayer) === $k)>{{ $p[0] }}</option>@endforeach
                    </select>
                    @error('prayer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <x-field name="time_note" label="ملاحظة على الموعد (اختياري)" :value="$circle->time_note" col="col-6 col-md-5" placeholder="مثال: لمدة ساعة، أو حتى أذان المغرب" data-note="prayer" />
            </div>
            <div class="col-12 row g-3 m-0 p-0" data-time="custom">
                <x-field name="time_note" label="وصف الموعد" :value="$circle->time_note" required col="col-md-6" placeholder="مثال: الضحى، من 9:00 إلى 10:30" data-note="custom" />
            </div>
<script>
    (function () {
        const sync = () => {
            const type = document.querySelector('input[name="time_type"]:checked')?.value || 'clock';
            document.querySelectorAll('[data-time]').forEach((box) => {
                const on = box.dataset.time === type;
                box.classList.toggle('d-none', !on);
                // Only the visible group's inputs are submitted, so the two note fields never clash.
                box.querySelectorAll('input, select').forEach((el) => { el.disabled = !on; });
            });
        };
        document.querySelectorAll('input[name="time_type"]').forEach((r) => r.addEventListener('change', sync));
        sync();
    })();
</script>
