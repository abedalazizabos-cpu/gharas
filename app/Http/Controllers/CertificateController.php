<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\CertificateService;
use App\Support\PdfExport;
use Illuminate\Http\Request;

/** Certificate PDF for the admin, the student's parent, his circle teacher, the course sheikh or the examiner. */
class CertificateController extends Controller
{
    public function download(Request $request, Certificate $certificate, CertificateService $certificates)
    {
        abort_unless($certificates->canView($request->user(), $certificate), 403);

        return PdfExport::response($certificates->pdf($certificate), CertificateService::filename($certificate));
    }

    /** The teacher's list: certificates of his circle students and of his course students. */
    public function teacher(Request $request, CertificateService $certificates)
    {
        $q = trim((string) $request->query('q')) ?: null;
        $list = $certificates->forTeacher($request->user())->with(['student.circle'])
            ->when($q, fn ($w) => $w->whereHas('student', fn ($s) => $s->where('full_name', 'like', "%{$q}%")))
            ->latest('issued_on')->latest('id')->paginate(25)->withQueryString();

        return view('teacher.certificates', ['certificates' => $list]);
    }
}
