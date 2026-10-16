<form method="GET" class="filter-bar row g-2 align-items-end" data-no-loading>
    <div class="col-sm-4 col-md-3"><label class="form-label small">من تاريخ</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control"></div>
    <div class="col-sm-4 col-md-3"><label class="form-label small">إلى تاريخ</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control"></div>
    <div class="col-sm-4 col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> عرض التقرير</button></div>
    <div class="col-12 small text-muted">الفترة: {{ $from->format('Y/m/d') }} - {{ $to->format('Y/m/d') }}</div>
</form>
