<?php

namespace App\Http\Middleware;

use App\Models\OfflineSubmission;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forms filled in without internet are kept on the teacher's phone (public/js/offline.js)
 * and sent later to their normal address with an X-Offline-Id header. This middleware:
 *  - answers "already saved" when the same id arrives twice (the phone may resend if the
 *    first answer was lost), so nothing is ever saved twice;
 *  - runs the normal controller (same validation, permissions, notifications, audit log);
 *  - turns its redirect + flash message into JSON the phone can read.
 */
class OfflineSync
{
    /** Helper fields the phone adds to the form; never passed to controllers. */
    public const HELPER_FIELDS = ['_offline_user', '_offline_label'];

    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::HELPER_FIELDS as $field) {
            $request->request->remove($field);
        }

        $id = $request->header('X-Offline-Id');
        if ($id === null) {
            return $next($request);
        }

        if (! Str::isUuid($id) || ! $request->isMethod('post')) {
            return response()->json(['message' => 'طلب غير صالح.'], 400);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'انتهت الجلسة. ادخل مرة أخرى لرفع التسجيلات.'], 401);
        }

        $seen = OfflineSubmission::where('uuid', $id)->first();
        if ($seen) {
            return $seen->user_id === $user->id
                ? response()->json(['status' => 'duplicate', 'message' => 'وصل هذا التسجيل من قبل.'])
                : response()->json(['message' => 'طلب غير صالح.'], 400);
        }

        try {
            $receipt = OfflineSubmission::create(['uuid' => $id, 'user_id' => $user->id, 'path' => Str::limit($request->path(), 250, '')]);
        } catch (UniqueConstraintViolationException) {
            // The same form arrived twice at the same moment; the other request saves it.
            return response()->json(['status' => 'duplicate', 'message' => 'وصل هذا التسجيل من قبل.']);
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 400) {
            $receipt->delete();

            return $response;
        }

        $session = $request->session();
        $errors = $session->get('errors');
        $error = $session->get('error') ?: ($errors instanceof ViewErrorBag || $errors instanceof MessageBag ? $errors->first() : null);
        $message = $session->get('success');
        $session->forget(['success', 'error', 'errors', '_old_input']);

        if ($error) {
            $receipt->delete();

            return response()->json(['message' => $error], 422);
        }

        if (! $response instanceof RedirectResponse && ! $response->isSuccessful()) {
            $receipt->delete();

            return response()->json(['message' => 'تعذّر الحفظ.'], 500);
        }

        return response()->json(['status' => 'saved', 'message' => $message ?: 'تم الحفظ.']);
    }
}
