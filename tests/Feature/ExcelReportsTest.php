<?php

namespace Tests\Feature;

use App\Models\MemorizationRecord;
use App\Models\User;
use App\Models\WeeklyArchive;
use App\Support\PdfExport;
use Carbon\Carbon;
use Tests\TestCase;

class ExcelReportsTest extends TestCase
{
    private const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private function assertExcel($res, string $name): void
    {
        $res->assertOk()->assertHeader('Content-Type', self::XLSX);
        $this->assertStringStartsWith('PK', $res->getContent());
        $this->assertStringContainsString(rawurlencode($name), $res->headers->get('Content-Disposition'));
    }

    public function test_reports_download_as_excel_with_the_same_content_as_the_pdf(): void
    {
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher, ['name' => 'حلقة التقرير']);
        $student = $this->studentIn($circle, ['full_name' => 'يوسف أحمد السبيعي', 'student_number' => 'G-555']);
        $admin = $this->admin();

        $this->assertExcel($this->actingAs($admin)->get(route('admin.reports.center', ['export' => 'xlsx'])), 'تقرير-المركز.xlsx');
        $this->assertStringContainsString('حلقة التقرير', PdfExport::lastText());
        $this->assertExcel($this->actingAs($admin)->get(route('admin.reports.circle', [$circle, 'export' => 'xlsx'])), '.xlsx');
        $this->assertStringContainsString('G-555', PdfExport::lastText());
        $this->actingAs($admin)->get(route('admin.reports.circle', $circle))->assertSee('تنزيل PDF')->assertSee('تنزيل Excel');

        // The teacher's own circle report now downloads too (PDF and Excel).
        $this->actingAs($teacher)->get(route('teacher.circles.report', $circle))->assertSee('تنزيل Excel');
        $this->assertExcel($this->actingAs($teacher)->get(route('teacher.circles.report', [$circle, 'export' => 'xlsx'])), 'تقرير-حلقة التقرير.xlsx');
        $this->assertStringContainsString('يوسف أحمد السبيعي', PdfExport::lastText());
        $this->actingAs($teacher)->get(route('teacher.circles.report', [$circle, 'export' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Visitors: still short names, no student number.
        $visitor = User::factory()->visitor()->create();
        $this->assertExcel($this->actingAs($visitor)->get(route('visitor.reports.circle', [$circle, 'export' => 'xlsx'])), '.xlsx');
        $this->assertStringContainsString('يوسف أ.', PdfExport::lastText());
        $this->assertStringNotContainsString('السبيعي', PdfExport::lastText());
        $this->assertStringNotContainsString('G-555', PdfExport::lastText());

        // Monthly student report.
        $this->assertExcel($this->actingAs($admin)->get(route('monthly-reports.download', [$student, 'month' => now()->format('Y-m'), 'export' => 'xlsx'])), '.xlsx');

        // Certificates stay PDF whatever is asked.
        $this->actingAs($admin)->get(route('admin.reports.center', ['export' => 'pdf']))->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_weekly_archive_downloads_as_excel_and_visitors_get_no_teacher_notes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-09 10:00'));
        $teacher = $this->teacher();
        $circle = $this->circleFor($teacher);
        $student = $this->studentIn($circle, ['full_name' => 'عمر خالد الدوسري']);
        MemorizationRecord::create(['student_id' => $student->id, 'circle_id' => $circle->id, 'teacher_id' => $teacher->id, 'recorded_on' => '2026-11-08', 'type' => 'new',
            'surah_id' => 114, 'from_ayah' => 1, 'to_ayah' => 6, 'pages' => 0.5, 'score' => 90, 'notes' => 'ملاحظة سرية', 'shared_with_parent' => true]);
        $archive = WeeklyArchive::create(['circle_id' => $circle->id, 'week_start' => '2026-11-07', 'path' => 'archives/x.pdf', 'records_count' => 1, 'attendance_count' => 0, 'generated_at' => now()]);

        $this->assertExcel($this->actingAs($teacher)->get(route('teacher.archive.download', [$archive, 'export' => 'xlsx'])), '.xlsx');
        $this->assertStringContainsString('ملاحظة سرية', PdfExport::lastText());

        $visitor = User::factory()->visitor()->create();
        $this->assertExcel($this->actingAs($visitor)->get(route('visitor.archive.download', [$archive, 'export' => 'xlsx'])), '.xlsx');
        $this->assertStringNotContainsString('ملاحظة سرية', PdfExport::lastText());
        $this->assertStringNotContainsString('الدوسري', PdfExport::lastText());
    }
}
