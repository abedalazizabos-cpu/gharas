<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Standard Laravel password broker. Links are emailed using the configured
 * MAIL_* settings (the "log" mailer writes them to storage/logs in development).
 */
class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request)
    {
        $request->validate(['email' => ['required', 'email']], [], ['email' => 'البريد الإلكتروني']);

        Password::sendResetLink($request->only('email'));

        // Same message whether or not the email exists, to avoid account enumeration.
        return back()->with('success', 'إذا كان البريد مسجلاً لدينا فستصلك رسالة تحتوي على رابط إعادة تعيين كلمة المرور.');
    }

    public function reset(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], [], ['email' => 'البريد الإلكتروني', 'password' => 'كلمة المرور']);

        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function ($user, $password) {
            $user->forceFill(['password' => $password])->setRememberToken(str()->random(60));
            $user->save();
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'تم تغيير كلمة المرور بنجاح، يمكنك تسجيل الدخول الآن.')
            : back()->withErrors(['email' => 'رابط إعادة التعيين غير صالح أو منتهي الصلاحية.']);
    }
}
