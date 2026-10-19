<?php

namespace App\Support;

use App\Models\AssessmentCriterion;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Announcement;
use App\Models\Circle;
use App\Models\MemorizationRecord;
use App\Models\Student;
use App\Models\Surah;
use App\Models\TeacherWarning;
use App\Models\User;
use Illuminate\Support\Carbon;

/** Turns audit log codes and raw values into plain Arabic for the audit log page. */
class AuditPresenter
{
    public const ACTIONS = [
        'auth.login' => ['تسجيل دخول', 'secondary'],
        'user.create' => ['إنشاء حساب', 'success'],
        'user.update' => ['تعديل حساب', 'primary'],
        'user.status' => ['تغيير حالة حساب', 'warning'],
        'user.delete' => ['حذف حساب', 'danger'],
        'student.create' => ['إضافة طالب', 'success'],
        'student.update' => ['تعديل بيانات طالب', 'primary'],
        'student.status' => ['تغيير حالة طالب', 'warning'],
        'student.delete' => ['أرشفة طالب', 'danger'],
        'student.transfer' => ['نقل طالب', 'info'],
        'student.parents' => ['تعديل أولياء الأمور', 'primary'],
        'parent.children' => ['تعديل الأبناء المرتبطين', 'primary'],
        'guardian.update' => ['تعديل بيانات ولي الأمر', 'primary'],
        'circle.create' => ['إنشاء حلقة', 'success'],
        'circle.update' => ['تعديل حلقة', 'primary'],
        'circle.delete' => ['حذف حلقة', 'danger'],
        'circle.teacher' => ['تغيير محفظ الحلقة', 'info'],
        'teacher.reassign' => ['نقل حلقات محفظ', 'info'],
        'teacher.warn' => ['تنبيه أو إنذار لمحفظ', 'warning'],
        'teacher.warn.revoke' => ['إلغاء تنبيه أو إنذار', 'secondary'],
        'teacher.warn.ack' => ['اطلاع المحفظ على تنبيه', 'secondary'],
        'memorization.update' => ['تعديل سجل تسميع', 'primary'],
        'memorization.delete' => ['حذف سجل تسميع', 'danger'],
        'attendance.update' => ['تعديل الحضور', 'primary'],
        'assessment.delete' => ['حذف تقييم', 'danger'],
        'criterion.create' => ['إضافة معيار تقييم', 'success'],
        'criterion.update' => ['تعديل معيار تقييم', 'primary'],
        'criterion.delete' => ['حذف معيار تقييم', 'danger'],
        'announcement.create' => ['نشر إعلان', 'success'],
        'announcement.update' => ['تعديل إعلان', 'primary'],
        'announcement.delete' => ['حذف إعلان', 'danger'],
        'settings.update' => ['تعديل الإعدادات', 'primary'],
        'system.backup' => ['نسخة احتياطية', 'secondary'],
        'access.roles' => ['تعديل صلاحيات الأدوار', 'warning'],
        'access.user' => ['تعديل صلاحيات مستخدم', 'warning'],
    ];

    /** Groups for the «نوع العملية» filter (first part of the action code). */
    public const GROUPS = [
        'auth' => 'تسجيل الدخول', 'user' => 'الحسابات', 'student' => 'الطلاب', 'parent' => 'أولياء الأمور',
        'guardian' => 'أولياء الأمور (من صفحة الطالب)', 'circle' => 'الحلقات', 'teacher' => 'المحفظون',
        'memorization' => 'سجلات التسميع', 'attendance' => 'الحضور', 'assessment' => 'التقييمات',
        'criterion' => 'معايير التقييم', 'announcement' => 'الإعلانات', 'settings' => 'الإعدادات',
        'system' => 'النظام', 'access' => 'الصلاحيات',
    ];

    private const FIELDS = [
        'name' => 'الاسم', 'full_name' => 'الاسم', 'email' => 'البريد', 'phone' => 'الجوال', 'role' => 'الدور',
        'status' => 'الحالة', 'student_number' => 'رقم الطالب', 'gender' => 'الجنس', 'birth_date' => 'تاريخ الميلاد',
        'enrollment_date' => 'تاريخ الالتحاق', 'internal_notes' => 'ملاحظات الإدارة', 'circle' => 'الحلقة',
        'circle_id' => 'الحلقة', 'teacher_id' => 'المحفظ', 'student_id' => 'الطالب', 'parent_id' => 'ولي الأمر',
        'code' => 'رمز الحلقة', 'level' => 'المستوى', 'age_group' => 'الفئة العمرية', 'days' => 'الأيام',
        'time_type' => 'نوع الموعد', 'prayer' => 'الصلاة', 'time_note' => 'ملاحظة الموعد', 'start_time' => 'وقت البداية',
        'end_time' => 'وقت النهاية', 'capacity' => 'السعة', 'location' => 'المكان', 'description' => 'الوصف',
        'is_public' => 'ظاهرة في الموقع', 'recorded_on' => 'تاريخ التسميع', 'type' => 'النوع', 'surah_id' => 'السورة',
        'from_ayah' => 'من آية', 'to_ayah' => 'إلى آية', 'pages' => 'المقدار', 'score' => 'الدرجة',
        'grade' => 'التقدير', 'mistakes_count' => 'عدد الأخطاء', 'notes' => 'الملاحظات', 'recommendations' => 'التوصيات',
        'shared_with_parent' => 'ظاهر لولي الأمر', 'title' => 'العنوان', 'body' => 'النص', 'audience' => 'الجمهور',
        'publish_at' => 'تاريخ النشر', 'expires_at' => 'تاريخ الانتهاء', 'max_score' => 'الدرجة العظمى',
        'sort_order' => 'الترتيب', 'is_active' => 'مفعّل', 'reason' => 'السبب', 'capacity_override' => 'تجاوز السعة',
        'percentage' => 'النسبة', 'attached' => 'أُضيف', 'detached' => 'أُزيل', 'changed' => 'الحقول المعدّلة',
        'password' => 'كلمة المرور', 'center_name' => 'اسم المركز', 'center_logo' => 'شعار المركز',
        'pass_score' => 'درجة الاجتياز', 'teacher_edit_days' => 'أيام تعديل المحفظ', 'theme_color' => 'لون الواجهة',
        'brand_title' => 'العنوان في الشعار', 'brand_subtitle' => 'العنوان الفرعي', 'reminders_enabled' => 'التذكيرات',
        'reminders_interval' => 'فترة التذكير (دقائق)', 'reminders_text' => 'نصوص التذكير', 'contact_phone' => 'هاتف التواصل',
        'contact_email' => 'بريد التواصل', 'contact_address' => 'العنوان',
    ];

    public static function action(string $code): string
    {
        return self::ACTIONS[$code][0] ?? self::GROUPS[explode('.', $code)[0]] ?? $code;
    }

    public static function color(string $code): string
    {
        return self::ACTIONS[$code][1] ?? 'secondary';
    }

    public static function group(string $prefix): string
    {
        return self::GROUPS[$prefix] ?? $prefix;
    }

    /**
     * Readable changes: [['label' => ..., 'old' => ?string, 'new' => ?string], ...].
     * Values that did not change are left out.
     */
    public static function changes(AuditLog $log): array
    {
        $old = (array) ($log->old_values ?? []);
        $new = (array) ($log->new_values ?? []);

        if ($log->action === 'attendance.update' && isset($new['changes'])) {
            return self::attendance((array) $new['changes']);
        }

        $rows = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $field) {
            $o = array_key_exists($field, $old) ? self::value($field, $old[$field], $log->action) : null;
            $n = array_key_exists($field, $new) ? self::value($field, $new[$field], $log->action) : null;
            if ($o === $n || ($o === null && $n === '—') || ($n === null && $o === '—')) {
                continue;
            }
            $rows[] = ['label' => self::FIELDS[$field] ?? 'حقل آخر', 'old' => $o, 'new' => $n];
        }

        return $rows;
    }

    private static function attendance(array $changes): array
    {
        $names = Student::withTrashed()->whereIn('id', array_keys($changes))->pluck('full_name', 'id');

        return collect($changes)->map(fn ($pair, $id) => [
            'label' => $names[$id] ?? 'طالب',
            'old' => AttendanceRecord::STATUSES[$pair[0]] ?? $pair[0],
            'new' => AttendanceRecord::STATUSES[$pair[1]] ?? $pair[1],
        ])->values()->all();
    }

    private static function value(string $field, mixed $value, string $action): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '—';
        }
        if (is_bool($value) || in_array($field, ['shared_with_parent', 'is_public', 'is_active', 'capacity_override', 'reminders_enabled'], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'نعم' : 'لا';
        }

        return match ($field) {
            'role' => User::ROLES[$value] ?? $value,
            'status' => User::STATUSES[$value] ?? Student::STATUSES[$value] ?? Circle::STATUSES[$value] ?? $value,
            'gender' => Student::GENDERS[$value] ?? $value,
            'type' => MemorizationRecord::TYPES[$value] ?? $value,
            'grade' => MemorizationRecord::GRADES[$value] ?? $value,
            'level' => TeacherWarning::LEVELS[$value][0] ?? $value,
            'audience' => Announcement::AUDIENCES[$value] ?? $value,
            'time_type' => Circle::TIME_TYPES[$value] ?? $value,
            'prayer' => Circle::PRAYERS[$value][0] ?? $value,
            'pages' => MemorizationRecord::formatPages($value),
            'surah_id' => Surah::find($value)?->name ?? (string) $value,
            'circle_id' => Circle::withTrashed()->find($value)?->name ?? (string) $value,
            'student_id' => Student::withTrashed()->find($value)?->full_name ?? (string) $value,
            'teacher_id', 'parent_id' => User::withTrashed()->find($value)?->nameFor(auth()->user()) ?? (string) $value,
            'attached', 'detached' => ($action === 'parent.children'
                ? Student::withTrashed()->whereIn('id', (array) $value)->pluck('full_name')
                : User::withTrashed()->whereIn('id', (array) $value)->pluck('name'))->join('، ') ?: '—',
            'changed' => collect((array) $value)->map(fn ($f) => self::FIELDS[$f] ?? $f)->join('، '),
            'days' => collect(is_array($value) ? $value : explode(',', (string) $value))->map(fn ($d) => Circle::DAYS[(int) $d] ?? $d)->join('، '),
            'recorded_on', 'birth_date', 'enrollment_date' => self::date($value, 'Y/m/d'),
            'publish_at', 'expires_at' => self::date($value, 'Y/m/d H:i'),
            default => is_array($value) ? collect($value)->flatten()->join('، ') : (string) $value,
        };
    }

    private static function date(mixed $value, string $format): string
    {
        try {
            return Carbon::parse($value)->format($format);
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
