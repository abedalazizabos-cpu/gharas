<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Services\AuditLogger;
use App\Services\CertificateService;
use App\Support\PdfExport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The admin writes the certificate wording and issues each certificate; nothing is issued without him. */
class CertificateController extends Controller
{
    public function __construct(private CertificateService $certificates) {}

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['eligible', 'issued', 'templates'], true) ? $request->query('tab') : 'eligible';
        $kind = array_key_exists((string) $request->query('kind'), CertificateTemplate::KINDS) ? $request->query('kind') : null;
        $q = trim((string) $request->query('q')) ?: null;

        $issued = Certificate::query()->with(['student.circle', 'issuer'])
            ->when($kind, fn ($w) => $w->where('kind', $kind))
            ->when($q, fn ($w) => $w->where(fn ($s) => $s->where('number', 'like', "%{$q}%")->orWhereHas('student', fn ($st) => $st->where('full_name', 'like', "%{$q}%"))))
            ->latest('issued_on')->latest('id')->paginate(25)->withQueryString();

        return view('admin.certificates.index', [
            'tab' => $tab,
            'kind' => $kind,
            'eligible' => $tab === 'eligible' ? $this->certificates->eligible($kind, $q) : collect(),
            'eligibleCount' => $this->certificates->eligible()->count(),
            'issued' => $issued,
            'issuedCount' => Certificate::count(),
            'templates' => CertificateTemplate::all()->sortBy(fn ($t) => array_search($t->kind, array_keys(CertificateTemplate::KINDS)))->values(),
        ]);
    }

    public function issue(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['string', 'regex:/^(course|test):\d+$/'],
            'issued_on' => ['required', 'date'],
        ], ['items.required' => 'اختر نتيجة واحدة على الأقل لإصدار شهادتها.']);

        $issued = $this->certificates->issue($data['items'], $request->user(), $data['issued_on']);

        return redirect()->route('admin.certificates.index', ['tab' => $issued->isNotEmpty() ? 'issued' : 'eligible'])
            ->with($issued->isNotEmpty() ? 'success' : 'error', $issued->isNotEmpty() ? "صدرت {$issued->count()} ".($issued->count() > 2 && $issued->count() < 11 ? 'شهادات' : 'شهادة').'، وأُشعر أولياء الأمور.' : 'لم تصدر أي شهادة: النتائج المختارة لها شهادات من قبل أو لم تعد ناجحة.');
    }

    /** Withdraws an issued certificate; the result returns to the eligible list. */
    public function destroy(Certificate $certificate)
    {
        AuditLogger::log('certificate.delete', "سحب {$certificate->kindLabel()} رقم {$certificate->number} لـ{$certificate->content['student']} ({$certificate->subject()})", $certificate->student, $certificate->only(['number', 'kind', 'issued_on']));
        $certificate->delete();

        return back()->with('success', 'سُحبت الشهادة، وعادت النتيجة إلى قائمة المستحقين.');
    }

    public function updateTemplate(Request $request, CertificateTemplate $template)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'intro' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:1500'],
            'closing' => ['nullable', 'string', 'max:255'],
            'signer1_title' => ['nullable', 'string', 'max:100'],
            'signer1_name' => ['nullable', 'string', 'max:100'],
            'signer2_title' => ['nullable', 'string', 'max:100'],
            'signer2_name' => ['nullable', 'string', 'max:100'],
            'footer' => ['nullable', 'string', 'max:255'],
            'color' => ['required', Rule::in(array_keys(CertificateTemplate::COLORS))],
        ]);
        $old = $template->only(array_keys($data));
        $template->update($data);
        $changed = array_keys(array_diff_assoc(array_map('strval', $data), array_map('strval', $old)));
        if ($changed) {
            AuditLogger::log('certificate.template', "تعديل نصوص {$template->label()}", $template, array_intersect_key($old, array_flip($changed)), array_intersect_key($data, array_flip($changed)));
        }

        return redirect()->to(route('admin.certificates.index', ['tab' => 'templates']).'#template-'.$template->kind)
            ->with('success', 'حُفظت نصوص '.$template->label().'. الشهادات الصادرة سابقاً تبقى كما صدرت.');
    }

    public function preview(CertificateTemplate $template)
    {
        return PdfExport::response($this->certificates->previewPdf($template), 'معاينة-'.$template->label().'.pdf');
    }
}
