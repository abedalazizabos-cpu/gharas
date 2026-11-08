@extends('layouts.app')
@section('title', 'قسم السنة')
@section('content')
<x-page-header title="قسم السنة والدورات" :crumbs="['قسم السنة']" />
<p class="small text-muted">دورات المركز في آداب القرآن والإعجاز القرآني والأحاديث النبوية والصلاة والطهارة والسيرة النبوية وسير الصحابة.</p>
<div class="card"><div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead><tr><th>الدورة</th><th>المجال</th><th>الشيخ</th><th>المدة</th><th>الحالة</th><th>المشاركون</th><th>قُيّم</th><th>نسبة النجاح</th><th>متوسط النتيجة</th></tr></thead>
        <tbody>
        @forelse ($rows as $row)
            @php($c = $row['course'])
            @php($s = $row['summary'])
            <tr>
                <td class="fw-semibold"><i class="bi bi-{{ $c->categoryIcon() }} text-success ms-1"></i>{{ $c->title }}
                    @if ($c->objectiveList())<div class="small text-muted fw-normal">{{ \Illuminate\Support\Str::limit(implode('، ', $c->objectiveList()), 120) }}</div>@endif</td>
                <td class="small">{{ $c->categoryLabel() }}</td>
                <td>{{ $c->teacher?->name ?? '—' }}</td>
                <td class="small">{{ $c->periodLabel() }}</td>
                <td><span class="badge text-bg-{{ $c->statusColor() }}">{{ $c->statusLabel() }}</span></td>
                <td>{{ $s['students'] }}</td>
                <td>{{ $s['graded'] }}</td>
                <td>{{ $s['pass_rate'] !== null ? $s['pass_rate'].'%' : '—' }}</td>
                <td>{{ $s['average'] !== null ? $s['average'].'%' : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="9"><x-empty icon="journal-bookmark" message="لا توجد دورات بعد." /></td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
@endsection
