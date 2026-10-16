<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Response;
use Mpdf\Mpdf;

/** Arabic (RTL) PDF documents for downloads: weekly archives and reports. */
class PdfExport
{
    /** The last document's content as plain data, so tests can check what went into the PDF. */
    public static ?array $last = null;

    /**
     * @param  array<int, array{title: string, headers: array<int, string>, rows: iterable}>  $sections
     */
    public static function render(string $title, ?string $subtitle, array $sections, bool $landscape = true): string
    {
        self::$last = ['title' => $title, 'subtitle' => $subtitle, 'sections' => collect($sections)->map(fn ($s) => ['rows' => collect($s['rows'])->map(fn ($r) => array_values((array) $r))->all()] + $s)->all()];
        $tmp = storage_path('framework/cache/mpdf');
        if (! is_dir($tmp)) {
            mkdir($tmp, 0775, true);
        }
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $landscape ? 'A4-L' : 'A4',
            'default_font' => 'xbriyaz',
            'directionality' => 'rtl',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'margin_top' => 12, 'margin_bottom' => 14, 'margin_left' => 10, 'margin_right' => 10,
            'tempDir' => $tmp,
        ]);
        $mpdf->SetTitle($title);
        $mpdf->SetFooter('{PAGENO} / {nbpg}');
        $sections = self::$last['sections'];
        $mpdf->WriteHTML(view('pdf.table', [
            'center' => Setting::get('center_name', 'مركز غراس لتحفيظ القرآن الكريم'),
            'logo' => self::logoPath(),
            'title' => $title,
            'subtitle' => $subtitle,
            'sections' => $sections,
            'printedAt' => now()->format('Y/m/d H:i'),
        ])->render());

        return $mpdf->Output('', 'S');
    }

    public static function download(string $filename, string $title, ?string $subtitle, array $sections, bool $landscape = true): Response
    {
        return self::response(self::render($title, $subtitle, $sections, $landscape), $filename);
    }

    /** Everything written in the last document, as one string (for tests). */
    public static function lastText(): string
    {
        return collect(self::$last['sections'] ?? [])->flatMap(fn ($s) => array_merge($s['headers'], collect($s['rows'])->flatten()->all()))
            ->prepend(self::$last['subtitle'] ?? '')->prepend(self::$last['title'] ?? '')->implode(' | ');
    }

    public static function response(string $pdf, string $filename): Response
    {
        $ascii = 'gharas-'.ltrim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename), '-');

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$ascii}\"; filename*=UTF-8''".rawurlencode($filename),
        ]);
    }

    private static function logoPath(): ?string
    {
        if (Setting::get('logo_on_pdf') === '0') {
            return null;
        }
        $custom = Setting::get('center_logo');
        foreach ([$custom ? public_path($custom) : null, public_path('images/logo.png')] as $path) {
            if ($path && is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
