{{-- $attendance (paginator of AttendanceRecord with session.circle) --}}
@if ($attendance->isEmpty())
    <x-empty icon="calendar-x" message="لا توجد سجلات حضور." />
@else
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>التاريخ</th><th>الحلقة</th><th>الحالة</th><th>ملاحظات</th></tr></thead>
            <tbody>
            @foreach ($attendance as $a)
                <tr>
                    <td>{{ $a->session->session_date->format('Y/m/d') }} <small class="text-muted">{{ $a->session->session_date->translatedFormat('l') }}</small></td>
                    <td>{{ $a->session->circle->name }}</td>
                    <td><span class="badge bg-{{ $a->badge() }}">{{ $a->statusLabel() }}</span></td>
                    <td class="small text-muted">{{ $a->notes ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="p-2">{{ $attendance->links() }}</div>
@endif
