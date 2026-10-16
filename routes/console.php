<?php

use App\Models\User;
use App\Services\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

// Create the first (or any) admin account interactively; the password is never echoed.
Artisan::command('gharas:create-admin', function () {
    $name = $this->ask('اسم المدير');
    $email = $this->ask('البريد الإلكتروني');
    $password = $this->secret('كلمة المرور (8 أحرف على الأقل، حروف وأرقام)');

    $validator = Validator::make(compact('name', 'email', 'password'), [
        'name' => ['required', 'string', 'min:3'],
        'email' => ['required', 'email', 'unique:users,email'],
        'password' => ['required', Password::min(8)->letters()->numbers()],
    ]);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
    // The first account becomes the super admin, who controls roles and permissions.
    $super = ! User::where('role', User::ROLE_SUPERADMIN)->exists();
    $user->role = $super ? User::ROLE_SUPERADMIN : User::ROLE_ADMIN;
    $user->status = 'active';
    $user->email_verified_at = now();
    $user->save();

    $this->info(($super ? 'تم إنشاء حساب المدير الأعلى: ' : 'تم إنشاء حساب المدير: ').$email);
})->purpose('إنشاء حساب مدير نظام بشكل آمن');

Artisan::command('gharas:backup', function (BackupService $backup) {
    $this->info('تم إنشاء النسخة الاحتياطية: '.$backup->create());
})->purpose('إنشاء نسخة احتياطية من قاعدة البيانات في storage/app/backups');

Artisan::command('gharas:archive-weeks', function (\App\Services\WeeklyArchiveService $weeks) {
    $this->info('تم حفظ ملفات '.$weeks->archiveMissing().' أسبوع/حلقة في storage/app/private/archives');
})->purpose('حفظ ملف لكل حلقة عن كل أسبوع منتهٍ (من الأحد إلى السبت)');

// Saves last week's files every Sunday; the archive page also catches up on its own.
Schedule::command('gharas:archive-weeks')->weeklyOn(0, '00:30');

// Daily backup when the Laravel scheduler is running (php artisan schedule:work / cron).
Schedule::command('gharas:backup')->dailyAt('02:00');
