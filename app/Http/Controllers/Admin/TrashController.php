<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Services\AuditLogger;
use App\Services\DataRemoval;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Super admin only: remove a circle with everything in it, clear the trial data, and the trash. */
class TrashController extends Controller
{
    /** Phrase typed to confirm clearing all the trial data. */
    public const WIPE_PHRASE = 'حذف بيانات التجربة';

    public function __construct(private DataRemoval $removal) {}

    public function index()
    {
        return view('admin.trash.index', ['batches' => $this->removal->batches(), 'phrase' => self::WIPE_PHRASE]);
    }

    public function confirmCircle(Circle $circle)
    {
        return view('admin.trash.circle', ['circle' => $circle, 'impact' => $this->removal->circleImpact($circle)]);
    }

    public function removeCircle(Request $request, Circle $circle)
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'ضع علامة الموافقة على الحذف الشامل أولاً.']);
        $counts = $this->removal->removeCircle($circle);
        AuditLogger::log('circle.remove_all', "حذف شامل للحلقة {$circle->name} مع طلابها وسجلاتها", $circle, [], $counts);

        return redirect()->route('admin.trash.index')
            ->with('success', "تم حذف حلقة «{$circle->name}» مع {$counts['students']} طالب وسجلاتهم. يمكنك استرجاعها من هنا.");
    }

    public function wipe(Request $request)
    {
        $request->validate(['phrase' => ['required', 'in:'.self::WIPE_PHRASE]], [
            'phrase.required' => 'اكتب عبارة التأكيد.',
            'phrase.in' => 'عبارة التأكيد غير مطابقة. اكتب: '.self::WIPE_PHRASE,
        ]);
        $counts = $this->removal->removeEverything();
        AuditLogger::log('system.wipe', 'حذف جميع بيانات التجربة (الحلقات والطلاب والحسابات)', null, [], $counts);

        return redirect()->route('admin.trash.index')->with('success',
            "تم حذف {$counts['circles']} حلقة و{$counts['students']} طالب و{$counts['accounts']} حساب. حسابك وحسابات المدير الأعلى باقية.");
    }

    public function restore(Request $request)
    {
        $stamp = $this->stamp($request);
        $count = $this->removal->restore($stamp);
        AuditLogger::log('trash.restore', "استرجاع ما حُذف بتاريخ {$stamp}", null, [], ['count' => $count]);

        return back()->with('success', 'تم استرجاع المحذوفات بكل سجلاتها.');
    }

    public function purge(Request $request)
    {
        $stamp = $this->stamp($request);
        $count = $this->removal->purge($stamp);
        AuditLogger::log('trash.purge', "حذف نهائي لما حُذف بتاريخ {$stamp}", null, [], ['count' => $count]);

        return back()->with('success', 'تم الحذف النهائي. لا يمكن استرجاع هذه البيانات بعد الآن.');
    }

    public function purgeAll()
    {
        $count = $this->removal->batches()->sum(fn ($batch) => $this->removal->purge($batch['at']));
        AuditLogger::log('trash.purge', 'إفراغ سلة المحذوفات كاملة', null, [], ['count' => $count]);

        return back()->with('success', 'تم إفراغ سلة المحذوفات نهائياً.');
    }

    private function stamp(Request $request): string
    {
        $data = $request->validate(['at' => ['required', 'date_format:Y-m-d H:i:s']]);

        return Carbon::parse($data['at'])->toDateTimeString();
    }
}
