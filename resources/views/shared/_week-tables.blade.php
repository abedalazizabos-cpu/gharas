{{-- $w from WeeklyArchiveService::week(), optional $showStudent, $showShared --}}
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-calendar-check"></i> الحضور والغياب خلال الأسبوع</div>
    @if ($w['attendance']->isEmpty())
        <x-empty icon="calendar" message="لم يُسجَّل حضور في هذا الأسبوع." />
    @else
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 align-middle text-center">
                <thead><tr><th class="text-start">الطالب</th>
                    @foreach ($w['days'] as $d)<th class="{{ $w['sessionDays']->contains($d->toDateString()) ? '' : 'text-muted fw-normal' }}">{{ \App\Services\WeeklyArchiveService::DAYS[$d->dayOfWeek] }}<br><small>{{ $d->format('m/d') }}</small></th>@endforeach
                </tr></thead>
                <tbody>
                @foreach ($w['students'] as $s)
                    <tr><td class="text-start text-nowrap">{{ $s->full_name }}</td>
                        @foreach ($w['days'] as $d)
                            @php($a = $w['grid'][$s->id][$d->toDateString()] ?? null)
                            <td>@if ($a)<span class="badge bg-{{ $a->badge() }}-subtle text-{{ $a->badge() }}-emphasis" @if ($a->notes) title="{{ $a->notes }}" @endif>{{ $a->statusLabel() }}</span>@endif</td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between"><span><i class="bi bi-book"></i> سجلات الحفظ والمراجعة خلال الأسبوع</span><span class="badge text-bg-light border">{{ $w['records']->count() }} سجل</span></div>
    @include('shared._memorization-table', ['records' => $w['records'], 'showStudent' => $showStudent ?? false, 'showShared' => $showShared ?? false])
</div>
