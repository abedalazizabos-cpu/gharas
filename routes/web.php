<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\Guardian;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MonthlyReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\Teacher;
use App\Http\Controllers\Visitor;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------- Public
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/contact', [HomeController::class, 'contact'])->middleware('throttle:5,1')->name('contact.store');
Route::get('/register', [RegistrationController::class, 'create'])->name('registration.create');
Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:5,10')->name('registration.store');
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/app-icon-{size}.png', [PwaController::class, 'icon'])->whereIn('size', ['192', '512'])->name('pwa.icon');

// ---------------------------------------------------------------- Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');

    Route::post('/push/subscribe', [PwaController::class, 'subscribe'])->middleware('throttle:20,1')->name('push.subscribe');
    Route::post('/push/unsubscribe', [PwaController::class, 'unsubscribe'])->name('push.unsubscribe');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('/students/{student}/monthly-report', [MonthlyReportController::class, 'download'])->name('monthly-reports.download');
    Route::get('/certificates/{certificate}/pdf', [CertificateController::class, 'download'])->name('certificates.download');

    // Parent <-> teacher messaging
    Route::middleware(['role:teacher,parent', 'perm:messages.send'])->group(function () {
        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/create', [MessageController::class, 'create'])->name('messages.create');
        Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
        Route::get('/messages/{message}', [MessageController::class, 'show'])->name('messages.show');
    });

    // ------------------------------------------------------------ Admin (each section behind a permission the super admin can switch off)
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        Route::middleware('perm:students.manage')->group(function () {
            Route::get('registrations', [Admin\RegistrationController::class, 'index'])->name('registrations.index');
            Route::post('registrations/{registration}/accept', [Admin\RegistrationController::class, 'accept'])->name('registrations.accept');
            Route::post('registrations/{registration}/reject', [Admin\RegistrationController::class, 'reject'])->name('registrations.reject');
            Route::get('guardian-lookup', [Admin\StudentController::class, 'guardianLookup'])->name('students.guardian-lookup');
            Route::put('students/{student}/guardians/{parent}', [Admin\StudentController::class, 'updateGuardian'])->name('students.guardians.update');
            Route::resource('students', Admin\StudentController::class);
            Route::patch('students/{student}/status', [Admin\StudentController::class, 'status'])->name('students.status');
            Route::post('students/{student}/transfer', [Admin\StudentController::class, 'transfer'])->name('students.transfer');
        });

        Route::middleware('perm:teachers.manage')->group(function () {
            Route::resource('teachers', Admin\TeacherController::class)->parameters(['teachers' => 'teacher']);
            Route::patch('teachers/{teacher}/status', [Admin\TeacherController::class, 'status'])->name('teachers.status');
            Route::post('teachers/{teacher}/reassign', [Admin\TeacherAssignmentController::class, 'reassign'])->name('teachers.reassign');
            Route::post('teachers/{teacher}/warnings', [Admin\TeacherAssignmentController::class, 'warn'])->name('teachers.warn');
            Route::delete('warnings/{warning}', [Admin\TeacherAssignmentController::class, 'revokeWarning'])->name('warnings.destroy');
        });

        Route::middleware('perm:parents.manage')->group(function () {
            Route::resource('parents', Admin\ParentController::class)->parameters(['parents' => 'parent']);
            Route::patch('parents/{parent}/status', [Admin\ParentController::class, 'status'])->name('parents.status');
        });

        Route::patch('admins/{admin}/status', [Admin\AdminUserController::class, 'status'])->name('admins.status')->middleware('perm:admins.manage');
        Route::resource('admins', Admin\AdminUserController::class)->except('show')->parameters(['admins' => 'admin'])->middleware('perm:admins.manage');
        Route::resource('visitors', Admin\VisitorController::class)->except('show')->parameters(['visitors' => 'visitor'])->middleware('perm:visitors.manage');

        Route::middleware('perm:circles.manage')->group(function () {
            Route::resource('circles', Admin\CircleController::class);
            Route::post('circles/{circle}/teacher', [Admin\TeacherAssignmentController::class, 'changeCircleTeacher'])->name('circles.teacher');
        });

        Route::middleware('perm:attendance.manage')->group(function () {
            Route::get('attendance', [Admin\AttendanceController::class, 'index'])->name('attendance.index');
            Route::get('attendance/{circle}/take', [Admin\AttendanceController::class, 'create'])->name('attendance.create');
            Route::post('attendance/{circle}', [Admin\AttendanceController::class, 'store'])->name('attendance.store');
        });

        Route::middleware('perm:memorization.manage')->group(function () {
            Route::get('memorization', [Admin\MemorizationController::class, 'index'])->name('memorization.index');
            Route::get('memorization/{record}/edit', [Admin\MemorizationController::class, 'edit'])->name('memorization.edit');
            Route::put('memorization/{record}', [Admin\MemorizationController::class, 'update'])->name('memorization.update');
            Route::delete('memorization/{record}', [Admin\MemorizationController::class, 'destroy'])->name('memorization.destroy');
        });

        Route::middleware('perm:assessments.manage')->group(function () {
            Route::get('assessments', [Admin\AssessmentController::class, 'index'])->name('assessments.index');
            Route::delete('assessments/{assessment}', [Admin\AssessmentController::class, 'destroy'])->name('assessments.destroy');
            Route::resource('criteria', Admin\AssessmentCriterionController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['criteria' => 'criterion']);
        });

        // لجنة الجودة: individual (per juz) and combined tests.
        Route::prefix('quality')->name('quality.')->middleware('perm:quality.manage')->group(function () {
            Route::get('/', [Admin\QualityController::class, 'index'])->name('index');
            Route::post('assign', [Admin\QualityController::class, 'assign'])->name('assign');
            Route::delete('tests/{test}', [Admin\QualityController::class, 'destroy'])->name('destroy');
            Route::get('tests/{test}/grade', [Admin\QualityController::class, 'gradeForm'])->name('grade');
            Route::post('tests/{test}/grade', [Admin\QualityController::class, 'grade'])->name('grade.store');
            Route::get('settings', [Admin\QualityController::class, 'settings'])->name('settings');
            Route::post('examiners/{teacher}', [Admin\QualityController::class, 'examiner'])->name('examiner');
            Route::put('pass-score', [Admin\QualityController::class, 'passScore'])->name('pass-score');
            Route::post('criteria', [Admin\QualityController::class, 'storeCriterion'])->name('criteria.store');
            Route::put('criteria/{criterion}', [Admin\QualityController::class, 'updateCriterion'])->name('criteria.update');
            Route::delete('criteria/{criterion}', [Admin\QualityController::class, 'destroyCriterion'])->name('criteria.destroy');
            Route::post('stages', [Admin\QualityController::class, 'storeStage'])->name('stages.store');
            Route::put('stages/{stage}', [Admin\QualityController::class, 'updateStage'])->name('stages.update');
            Route::delete('stages/{stage}', [Admin\QualityController::class, 'destroyStage'])->name('stages.destroy');
        });

        // قسم السنة: courses, their sheikh, students and grading rules.
        Route::prefix('sunnah')->name('sunnah.')->middleware('perm:sunnah.manage')->group(function () {
            Route::get('/', [Admin\SunnahCourseController::class, 'index'])->name('index');
            Route::get('create', [Admin\SunnahCourseController::class, 'create'])->name('create');
            Route::post('/', [Admin\SunnahCourseController::class, 'store'])->name('store');
            Route::get('{course}', [Admin\SunnahCourseController::class, 'show'])->name('show');
            Route::get('{course}/edit', [Admin\SunnahCourseController::class, 'edit'])->name('edit');
            Route::put('{course}', [Admin\SunnahCourseController::class, 'update'])->name('update');
            Route::delete('{course}', [Admin\SunnahCourseController::class, 'destroy'])->name('destroy');
            Route::get('{course}/grade', [Admin\SunnahCourseController::class, 'gradeForm'])->name('grade');
            Route::post('{course}/grade', [Admin\SunnahCourseController::class, 'grade'])->name('grade.store');
            Route::post('{course}/posts', [Admin\SunnahCourseController::class, 'storePost'])->name('posts.store');
            Route::get('{course}/attendance', [Admin\SunnahCourseController::class, 'attendance'])->name('attendance');
            Route::post('{course}/attendance', [Admin\SunnahCourseController::class, 'storeAttendance'])->name('attendance.store');
            Route::get('{course}/posts/{post}/grades', [Admin\SunnahCourseController::class, 'homework'])->name('homework');
            Route::post('{course}/posts/{post}/grades', [Admin\SunnahCourseController::class, 'storeHomework'])->name('homework.store');
            Route::delete('{course}/posts/{post}', [Admin\SunnahCourseController::class, 'destroyPost'])->name('posts.destroy');
        });

        // لوحة الشرف: one student per circle each month, on the login screen.
        Route::prefix('honor-board')->name('honor.')->middleware('perm:honor.manage')->group(function () {
            Route::get('/', [Admin\HonorBoardController::class, 'index'])->name('index');
            Route::post('/', [Admin\HonorBoardController::class, 'store'])->name('store');
            Route::post('rechoose', [Admin\HonorBoardController::class, 'rechoose'])->name('rechoose');
            Route::post('{entry}', [Admin\HonorBoardController::class, 'replace'])->name('replace');
        });

        // SMS to parents from the center's phone.
        Route::middleware('perm:sms.send')->group(function () {
            Route::get('sms', [Admin\SmsController::class, 'index'])->name('sms.index');
            Route::post('sms', [Admin\SmsController::class, 'send'])->name('sms.send')->middleware('throttle:10,1');
        });
        Route::middleware('role:superadmin')->group(function () {
            Route::get('sms/settings', [Admin\SmsController::class, 'settings'])->name('sms.settings');
            Route::put('sms/settings', [Admin\SmsController::class, 'saveSettings'])->name('sms.settings.update');
        });

        // Days teachers did not record, and days circles did not meet.
        Route::middleware('perm:attendance.manage')->group(function () {
            Route::get('recording-gaps', [Admin\RecordingGapController::class, 'index'])->name('recording-gaps.index');
            Route::post('day-offs', [Admin\RecordingGapController::class, 'storeOff'])->name('day-offs.store');
            Route::delete('day-offs/{off}', [Admin\RecordingGapController::class, 'destroyOff'])->name('day-offs.destroy');
        });

        // Certificates: the admin writes the wording and issues each one.
        Route::prefix('certificates')->name('certificates.')->middleware('perm:certificates.manage')->group(function () {
            Route::get('/', [Admin\CertificateController::class, 'index'])->name('index');
            Route::post('issue', [Admin\CertificateController::class, 'issue'])->name('issue');
            Route::delete('{certificate}', [Admin\CertificateController::class, 'destroy'])->name('destroy');
            Route::put('templates/{template}', [Admin\CertificateController::class, 'updateTemplate'])->name('templates.update');
            Route::get('templates/{template}/preview', [Admin\CertificateController::class, 'preview'])->name('templates.preview');
        });

        Route::middleware('perm:reports.view')->group(function () {
            Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/center', [Admin\ReportController::class, 'center'])->name('reports.center');
            Route::get('reports/circles/{circle}', [Admin\ReportController::class, 'circle'])->name('reports.circle');
            Route::get('reports/teachers/{teacher}', [Admin\ReportController::class, 'teacher'])->name('reports.teacher');
            Route::get('reports/students/{student}', [Admin\ReportController::class, 'student'])->name('reports.student');
        });

        Route::resource('announcements', Admin\AnnouncementController::class)->except('show')->middleware('perm:announcements.manage');

        // Settings and backups belong to the super admin only.
        Route::middleware('role:superadmin')->group(function () {
            Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
            Route::get('backup', [Admin\SettingController::class, 'backup'])->name('settings.backup');
        });

        Route::middleware('perm:archive.view')->group(function () {
            Route::get('weeks', [ArchiveController::class, 'week'])->name('weeks.show');
            Route::get('archive', [ArchiveController::class, 'index'])->name('archive.index');
            Route::get('archive/{archive}/download', [ArchiveController::class, 'download'])->name('archive.download');
        });

        Route::get('audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit.index')->middleware('perm:audit.view');
        Route::middleware('perm:contact.manage')->group(function () {
            Route::get('contact-messages', [Admin\ContactMessageController::class, 'index'])->name('contact.index');
            Route::delete('contact-messages/{contactMessage}', [Admin\ContactMessageController::class, 'destroy'])->name('contact.destroy');
        });

        // Super admin only: remove circles with everything in them, clear the trial data, and the trash.
        Route::prefix('trash')->name('trash.')->middleware('role:superadmin')->group(function () {
            Route::get('/', [Admin\TrashController::class, 'index'])->name('index');
            Route::post('restore', [Admin\TrashController::class, 'restore'])->name('restore');
            Route::delete('purge', [Admin\TrashController::class, 'purge'])->name('purge');
            Route::delete('purge-all', [Admin\TrashController::class, 'purgeAll'])->name('purge-all');
            Route::post('wipe', [Admin\TrashController::class, 'wipe'])->name('wipe');
            Route::get('circles/{circle}', [Admin\TrashController::class, 'confirmCircle'])->name('circle');
            Route::delete('circles/{circle}', [Admin\TrashController::class, 'removeCircle'])->name('circle.remove');
        });

        // Super admin only: roles and permissions of every user.
        Route::prefix('access')->name('access.')->middleware('role:superadmin')->group(function () {
            Route::get('roles', [Admin\AccessController::class, 'roles'])->name('roles');
            Route::put('roles', [Admin\AccessController::class, 'updateRoles'])->name('roles.update');
            Route::get('users', [Admin\AccessController::class, 'users'])->name('users');
            Route::get('users/{user}', [Admin\AccessController::class, 'editUser'])->name('users.edit');
            Route::put('users/{user}', [Admin\AccessController::class, 'updateUser'])->name('users.update');
        });
    });

    // ------------------------------------------------------------ Teacher
    Route::prefix('teacher')->name('teacher.')->middleware('role:teacher')->group(function () {
        Route::middleware('perm:teacher.archive')->group(function () {
            Route::get('weeks', [ArchiveController::class, 'week'])->name('weeks.show');
            Route::get('archive', [ArchiveController::class, 'index'])->name('archive.index');
            Route::get('archive/{archive}/download', [ArchiveController::class, 'download'])->name('archive.download');
        });
        Route::post('warnings/{warning}/acknowledge', [Teacher\WarningController::class, 'acknowledge'])->name('warnings.acknowledge');
        Route::get('/', [Teacher\DashboardController::class, 'index'])->name('dashboard');
        Route::get('circles', [Teacher\CircleController::class, 'index'])->name('circles.index');
        Route::get('circles/{circle}', [Teacher\CircleController::class, 'show'])->name('circles.show');
        Route::get('circles/{circle}/edit', [Teacher\CircleController::class, 'edit'])->name('circles.edit')->middleware('perm:teacher.circle.edit');
        Route::put('circles/{circle}', [Teacher\CircleController::class, 'update'])->name('circles.update')->middleware('perm:teacher.circle.edit');
        Route::get('circles/{circle}/report', [Teacher\CircleController::class, 'report'])->name('circles.report');

        Route::middleware('perm:teacher.attendance')->group(function () {
            Route::get('circles/{circle}/attendance', [Teacher\AttendanceController::class, 'create'])->name('attendance.create');
            Route::post('circles/{circle}/attendance', [Teacher\AttendanceController::class, 'store'])->name('attendance.store');
        });

        Route::get('students/{student}', [Teacher\StudentController::class, 'show'])->name('students.show');
        Route::middleware('perm:teacher.students.edit')->group(function () {
            Route::get('students/{student}/edit', [Teacher\StudentController::class, 'edit'])->name('students.edit');
            Route::put('students/{student}', [Teacher\StudentController::class, 'update'])->name('students.update');
            Route::put('students/{student}/guardians/{parent}', [Teacher\StudentController::class, 'updateGuardian'])->name('students.guardians.update');
            Route::get('guardian-lookup', [Teacher\StudentController::class, 'guardianLookup'])->name('students.guardian-lookup');
        });

        Route::middleware('perm:teacher.memorization')->group(function () {
            Route::get('memorization', [Teacher\MemorizationController::class, 'index'])->name('memorization.index');
            Route::get('memorization/create', [Teacher\MemorizationController::class, 'create'])->name('memorization.create');
            Route::post('memorization', [Teacher\MemorizationController::class, 'store'])->name('memorization.store');
            Route::get('memorization/{record}/edit', [Teacher\MemorizationController::class, 'edit'])->name('memorization.edit');
            Route::put('memorization/{record}', [Teacher\MemorizationController::class, 'update'])->name('memorization.update');
        });

        Route::middleware('perm:teacher.assessments')->group(function () {
            Route::get('assessments', [Teacher\AssessmentController::class, 'index'])->name('assessments.index');
            Route::get('assessments/create', [Teacher\AssessmentController::class, 'create'])->name('assessments.create');
            Route::post('assessments', [Teacher\AssessmentController::class, 'store'])->name('assessments.store');
        });

        Route::middleware('perm:teacher.quality')->group(function () {
            Route::get('quality', [Teacher\QualityController::class, 'index'])->name('quality.index');
            Route::get('quality/{test}/grade', [Teacher\QualityController::class, 'gradeForm'])->name('quality.grade');
            Route::post('quality/{test}/grade', [Teacher\QualityController::class, 'grade'])->name('quality.grade.store');
        });

        Route::prefix('sunnah')->name('sunnah.')->middleware('perm:teacher.sunnah')->group(function () {
            Route::get('/', [Teacher\SunnahCourseController::class, 'index'])->name('index');
            Route::get('{course}', [Teacher\SunnahCourseController::class, 'show'])->name('show');
            Route::get('{course}/grade', [Teacher\SunnahCourseController::class, 'gradeForm'])->name('grade');
            Route::post('{course}/grade', [Teacher\SunnahCourseController::class, 'grade'])->name('grade.store');
            Route::post('{course}/posts', [Teacher\SunnahCourseController::class, 'storePost'])->name('posts.store');
            Route::get('{course}/attendance', [Teacher\SunnahCourseController::class, 'attendance'])->name('attendance');
            Route::post('{course}/attendance', [Teacher\SunnahCourseController::class, 'storeAttendance'])->name('attendance.store');
            Route::get('{course}/posts/{post}/grades', [Teacher\SunnahCourseController::class, 'homework'])->name('homework');
            Route::post('{course}/posts/{post}/grades', [Teacher\SunnahCourseController::class, 'storeHomework'])->name('homework.store');
            Route::delete('{course}/posts/{post}', [Teacher\SunnahCourseController::class, 'destroyPost'])->name('posts.destroy');
        });

        Route::get('certificates', [CertificateController::class, 'teacher'])->name('certificates.index');
        Route::post('circles/{circle}/day-off', [Teacher\DayOffController::class, 'store'])->name('day-offs.store');
        Route::get('announcements', [Teacher\DashboardController::class, 'announcements'])->name('announcements');
    });

    // ------------------------------------------------------------ Visitor (read-only: GET routes only)
    Route::prefix('visitor')->name('visitor.')->middleware('role:visitor')->group(function () {
        Route::get('/', [Visitor\DashboardController::class, 'index'])->name('dashboard');
        Route::middleware('perm:visitor.reports')->group(function () {
            Route::get('reports/center', [Visitor\ReportController::class, 'center'])->name('reports.center');
            Route::get('reports/circles/{circle}', [Visitor\ReportController::class, 'circle'])->name('reports.circle');
            Route::get('reports/teachers/{teacher}', [Visitor\ReportController::class, 'teacher'])->name('reports.teacher');
            Route::get('sunnah', [Visitor\SunnahController::class, 'index'])->name('sunnah.index');
        });
        Route::middleware('perm:visitor.archive')->group(function () {
            Route::get('weeks', [ArchiveController::class, 'week'])->name('weeks.show');
            Route::get('archive', [ArchiveController::class, 'index'])->name('archive.index');
            Route::get('archive/{archive}/download', [ArchiveController::class, 'download'])->name('archive.download')->middleware('perm:visitor.download');
        });
    });

    // ------------------------------------------------------------ Parent
    Route::prefix('parent')->name('parent.')->middleware('role:parent')->group(function () {
        Route::get('/', [Guardian\DashboardController::class, 'index'])->name('dashboard');
        Route::get('children/{student}', [Guardian\ChildController::class, 'show'])->name('children.show');
        Route::get('children/{student}/courses', [Guardian\ChildController::class, 'courses'])->name('children.courses');
        Route::post('children/{student}/sunnah-posts/{post}/done', [Guardian\ChildController::class, 'homeworkDone'])->name('children.homework');
        Route::get('children/{student}/weeks', [Guardian\ChildController::class, 'weeks'])->name('children.weeks')->middleware('perm:parent.weeks');
        Route::get('announcements', [Guardian\DashboardController::class, 'announcements'])->name('announcements');
    });
});
