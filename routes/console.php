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

// Quality committee trial data on an existing trial database (the main demo seeder already includes it).
Artisan::command('gharas:demo-quality {--fresh : حذف بيانات الجودة الحالية وإعادة بنائها}', function () {
    if (app()->isProduction()) {
        $this->error('البيانات التجريبية لا تُضاف في بيئة الإنتاج (APP_ENV=production).');

        return 1;
    }
    if (\App\Models\QualityTest::withTrashed()->exists()) {
        if (! $this->option('fresh')) {
            $this->warn('توجد اختبارات جودة مسجلة. لإعادة بناء بيانات التجربة شغّل الأمر مع --fresh (يحذف كل اختبارات الجودة الحالية).');

            return 1;
        }
        \Database\Seeders\QualityDemoSeeder::clear();
    }
    $this->call('db:seed', ['--class' => \Database\Seeders\QualityDemoSeeder::class, '--force' => true]);
    $this->info('تمت إضافة بيانات تجريبية للجنة الجودة: مشايخ اللجنة teacher2 وteacher6 وteacher11 وteacher17، وولي الأمر 0550000001 يرى اختبارات ابنيه.');
})->purpose('إضافة بيانات تجريبية للجنة الجودة والاختبارات');

// قسم السنة trial data on an existing trial database (the main demo seeder already includes it).
Artisan::command('gharas:demo-sunnah {--fresh : حذف كل دورات قسم السنة الحالية وإعادة بنائها}', function () {
    if (app()->isProduction()) {
        $this->error('البيانات التجريبية لا تُضاف في بيئة الإنتاج (APP_ENV=production).');

        return 1;
    }
    if (\App\Models\SunnahCourse::withTrashed()->exists()) {
        if (! $this->option('fresh')) {
            $this->warn('توجد دورات في قسم السنة. لإعادة بناء بيانات التجربة شغّل الأمر مع --fresh (يحذف كل الدورات الحالية ونتائجها).');

            return 1;
        }
        \Database\Seeders\SunnahDemoSeeder::clear();
    }
    $this->call('db:seed', ['--class' => \Database\Seeders\SunnahDemoSeeder::class, '--force' => true]);
    $this->info('تمت إضافة 18 دورة تجريبية لقسم السنة مع الحضور والواجبات وتقييمها. ولي الأمر 0550000001 يرى دورات ابنيه ونتائجهما.');
})->purpose('إضافة بيانات تجريبية لقسم السنة والدورات');

Artisan::command('gharas:backup', function (BackupService $backup) {
    $this->info('تم إنشاء النسخة الاحتياطية: '.$backup->create());
})->purpose('إنشاء نسخة احتياطية من قاعدة البيانات في storage/app/backups');

Artisan::command('gharas:archive-weeks', function (\App\Services\WeeklyArchiveService $weeks) {
    $this->info('تم حفظ ملفات '.$weeks->archiveMissing().' أسبوع/حلقة في storage/app/private/archives');
})->purpose('حفظ ملف لكل حلقة عن كل أسبوع منتهٍ (من السبت إلى الخميس)');

// Saves the week's files every Friday (the study week ends on Thursday); the archive page also catches up on its own.
Schedule::command('gharas:archive-weeks')->weeklyOn(5, '00:30');

// Daily backup when the Laravel scheduler is running (php artisan schedule:work / cron).
Schedule::command('gharas:backup')->dailyAt('02:00');
