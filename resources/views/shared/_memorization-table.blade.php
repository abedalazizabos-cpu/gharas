{{-- $records (paginator), optional $editRoute, $deleteRoute, $showStudent, $showShared --}}
@if ($records->isEmpty())
    <x-empty icon="book" message="لا توجد سجلات تسميع." />
@else
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr>
                <th>التاريخ</th>
                @if (!empty($showStudent))<th>الطالب</th>@endif
                <th>النوع</th><th>السورة والآيات</th><th>الدرجة</th><th>الأخطاء</th><th>ملاحظات المحفظ</th>
                @if (!empty($showShared))<th>مشاركة</th>@endif
                @if (!empty($editRoute) || !empty($deleteRoute))<th class="no-print"></th>@endif
            </tr></thead>
            <tbody>
            @foreach ($records as $r)
                <tr>
                    <td class="text-nowrap">{{ $r->recorded_on->format('Y/m/d') }}</td>
                    @if (!empty($showStudent))<td>{{ $r->student->full_name }}<br><small class="text-muted">{{ $r->circle?->name }}</small></td>@endif
                    <td><span class="badge bg-{{ \App\Models\MemorizationRecord::TYPE_BADGES[$r->type] }}-subtle text-{{ \App\Models\MemorizationRecord::TYPE_BADGES[$r->type] }}-emphasis">{{ $r->typeLabel() }}</span></td>
                    <td class="text-nowrap">{{ $r->surah->name }} <small class="text-muted">({{ $r->from_ayah }}-{{ $r->to_ayah }})</small>@if ($r->pages)<br><small class="text-muted">{{ rtrim(rtrim($r->pages, '0'), '.') }} صفحة</small>@endif</td>
                    <td class="text-nowrap"><strong>{{ $r->score }}</strong> <small class="text-muted">{{ $r->gradeLabel() }}</small></td>
                    <td>{{ $r->mistakes_count }}</td>
                    <td class="small" style="min-width:180px">{{ $r->notes }}@if ($r->recommendations)<div class="text-success"><i class="bi bi-lightbulb"></i> {{ $r->recommendations }}</div>@endif</td>
                    @if (!empty($showShared))<td>{!! $r->shared_with_parent ? '<i class="bi bi-eye text-success" title="ظاهر لولي الأمر"></i>' : '<i class="bi bi-eye-slash text-muted" title="داخلي"></i>' !!}</td>@endif
                    @if (!empty($editRoute) || !empty($deleteRoute))
                        <td class="table-actions no-print">
                            @if (!empty($editRoute) && auth()->user()->can('update', $r))
                                <a href="{{ route($editRoute, $r) }}" class="btn btn-sm btn-outline-primary" title="تعديل"><i class="bi bi-pencil"></i></a>
                            @endif
                            @if (!empty($deleteRoute) && auth()->user()->can('delete', $r))
                                <x-delete-form :action="route($deleteRoute, $r)" message="سيتم حذف سجل التسميع وتوثيق العملية في سجل العمليات." label="" />
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if (method_exists($records, 'links'))<div class="p-2">{{ $records->links() }}</div>@endif
@endif
