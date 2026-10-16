<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PdfExport;
use Tests\TestCase;

class PdfReportsTest extends TestCase
{
    public function test_reports_download_as_arabic_pdf_and_visitors_get_short_names(): void
    {
        $circle = $this->circleFor($this->teacher(), ['name' => 'حلقة التقرير']);
        $this->studentIn($circle, ['full_name' => 'يوسف أحمد السبيعي', 'student_number' => 'G-555']);

        $res = $this->actingAs($this->admin())->get(route('admin.reports.center', ['export' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertStringContainsString('حلقة التقرير', PdfExport::lastText());

        $this->actingAs($this->admin())->get(route('admin.reports.circle', [$circle, 'export' => 'pdf']))->assertOk();
        $this->assertStringContainsString('يوسف أحمد السبيعي', PdfExport::lastText());
        $this->assertStringContainsString('G-555', PdfExport::lastText());

        $visitor = User::factory()->visitor()->create();
        $this->actingAs($visitor)->get(route('visitor.reports.circle', [$circle, 'export' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('يوسف أ.', PdfExport::lastText());
        $this->assertStringNotContainsString('السبيعي', PdfExport::lastText());
        $this->assertStringNotContainsString('G-555', PdfExport::lastText());

        $this->actingAs($this->admin())->get(route('admin.reports.circle', $circle))->assertSee('تنزيل PDF')->assertDontSee('CSV');
    }
}
