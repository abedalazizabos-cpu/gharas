<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ], [], ['login' => 'رقم الجوال أو البريد الإلكتروني', 'password' => 'كلمة المرور']);

        // Parents sign in with their mobile number; staff may use email or mobile.
        $credentials = str_contains($data['login'], '@')
            ? ['email' => trim($data['login'])]
            : ['phone' => User::normalizePhone($data['login'])];

        if (! Auth::attempt($credentials + ['password' => $data['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['login' => 'بيانات الدخول غير صحيحة.']);
        }

        $user = Auth::user();
        if (! $user->isActive()) {
            Auth::logout();
            throw ValidationException::withMessages(['login' => 'حسابك موقوف. يرجى التواصل مع إدارة المركز.']);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        AuditLogger::log('auth.login', "تسجيل دخول {$user->name}", $user);

        return redirect()->intended(route($user->dashboardRoute()));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'تم تسجيل الخروج بنجاح.');
    }
}
