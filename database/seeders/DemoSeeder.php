<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\AssessmentCriterion;
use App\Models\AttendanceRecord;
use App\Models\Circle;
use App\Models\CircleEnrollment;
use App\Models\CircleSession;
use App\Models\MemorizationRecord;
use App\Models\Message;
use App\Models\Student;
use App\Models\Surah;
use App\Models\TeacherWarning;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

/**
 * Fictional Arabic demo data for development only: 3 admins, 2 visitors,
 * 20 teachers, 25 circles, 200 parents and 300 students. Password for all: password123
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'password123';

    private ?string $passwordHash = null;

    public function run(): void
    {
        // One transaction keeps seeding ~15k rows fast on MySQL.
        DB::transaction(fn () => $this->seed());
    }

    private function seed(): void
    {
        mt_srand(2026);

        // Administration: the director plus two administrative staff.
        $this->account('المدير الأعلى', 'admin@gharas.test', User::ROLE_SUPERADMIN, '0500000001');
        $this->account('أ. سلمان القحطاني (الشؤون الإدارية)', 'admin2@gharas.test', User::ROLE_ADMIN, '0500000002');
        $this->account('أ. هند العمري (شؤون الطلاب)', 'admin3@gharas.test', User::ROLE_ADMIN, '0500000003');

        // Read-only accounts for external delegations and supporters.
        $this->account('وفد جمعية تحفيظ القرآن الكريم', 'visitor@gharas.test', User::ROLE_VISITOR, '0560000001');
        $this->account('مؤسسة الخير الداعمة', 'supporter@gharas.test', User::ROLE_VISITOR, '0560000002');

        // 20 teachers; the last four are female and teach the girls' circles.
        $teacherNames = [
            'الشيخ عبدالرحمن الحربي', 'الشيخ خالد العتيبي', 'الشيخ ياسر الشمري', 'الشيخ إبراهيم الدوسري', 'الشيخ أنس المالكي',
            'الشيخ بدر السهلي', 'الشيخ حسن القرني', 'الشيخ طارق الزهراني', 'الشيخ ماهر العسيري', 'الشيخ عادل الشهري',
            'الشيخ مشاري الرشيد', 'الشيخ نايف البلوي', 'الشيخ رائد الغامدي', 'الشيخ وليد الحارثي', 'الشيخ صالح المطيري',
            'الشيخ فهد العنزي', 'الأستاذة نورة القحطاني', 'الأستاذة هيفاء الشهري', 'الأستاذة منيرة العتيبي', 'الأستاذة أمل الزهراني',
        ];
        $qualifications = [['بكالوريوس شريعة', 'إجازة برواية حفص'], ['ماجستير قراءات', 'إجازة بالقراءات العشر'], ['بكالوريوس دراسات قرآنية', 'إجازة برواية حفص'], ['دبلوم تحفيظ', null]];
        $teachers = collect($teacherNames)->map(function ($name, $i) use ($qualifications) {
            $user = $this->account($name, 'teacher'.($i + 1).'@gharas.test', User::ROLE_TEACHER, '051'.str_pad((string) ($i + 1), 7, '0', STR_PAD_LEFT));
            $q = $qualifications[$i % 4];
            $user->teacherProfile()->create(['qualification' => $q[0], 'ijazah' => $q[1], 'hire_date' => now()->subYears(1 + $i % 6)]);

            return $user;
        });

        // 25 circles: every teacher has one, the first five teach a second evening circle.
        $boysCircles = ['أبي بن كعب', 'زيد بن ثابت', 'عبدالله بن مسعود', 'معاذ بن جبل', 'أبي موسى الأشعري', 'عثمان بن عفان', 'علي بن أبي طالب', 'سالم مولى أبي حذيفة',
            'عبدالله بن عباس', 'أنس بن مالك', 'مصعب بن عمير', 'سعد بن أبي وقاص', 'خالد بن الوليد', 'أبي الدرداء', 'تميم الداري', 'عبدالله بن عمر',
            'سعيد بن جبير', 'عاصم بن أبي النجود', 'نافع المدني', 'حفص بن سليمان', 'ابن كثير'];
        $girlsCircles = ['عائشة أم المؤمنين', 'خديجة بنت خويلد', 'حفصة بنت عمر', 'أم سلمة'];
        $levels = [['مبتدئ', '7 - 10 سنوات'], ['متوسط', '10 - 14 سنة'], ['متقدم', '14 - 18 سنة']];
        $circleTeachers = array_merge(range(0, 19), range(0, 4));
        $girlIndex = 0;
        $boyIndex = 0;
        // Most circles use a clock range; some start after a prayer, one has a free-text time.
        $timing = function (int $i, bool $second): array {
            if ($i === 24) {
                return ['time_type' => 'custom', 'time_note' => 'الضحى، من 9:00 إلى 10:30 صباحاً'];
            }
            if ($i % 5 === 3) {
                return ['time_type' => 'prayer', 'prayer' => $second ? 'maghrib' : 'asr', 'time_note' => 'لمدة ساعة ونصف'];
            }

            return ['time_type' => 'clock', 'start_time' => $second ? '18:00' : '16:00', 'end_time' => $second ? '19:30' : '17:30'];
        };
        $circles = collect($circleTeachers)->map(function ($t, $i) use ($timing, $teachers, $boysCircles, $girlsCircles, $levels, &$girlIndex, &$boyIndex) {
            $girls = $t >= 16;
            $name = 'حلقة '.($girls ? $girlsCircles[$girlIndex++] : $boysCircles[$boyIndex++]);
            $level = $levels[$i % 3];
            $second = $i >= 20;

            return Circle::create([
                'name' => $name, 'code' => 'C-'.(101 + $i), 'teacher_id' => $teachers[$t]->id, 'level' => $level[0], 'age_group' => $level[1],
                'days' => Circle::STUDY_DAYS] + $timing($i, $second) + [
                'capacity' => 15, 'status' => 'active', 'location' => 'القاعة '.(1 + $i % 8),
                'description' => 'حلقة لتحفيظ القرآن الكريم للمستوى '.$level[0].'.', 'is_public' => true,
            ]);
        });

        // ~200 parents (the first 100 have two children). Parents sign in with their mobile number.
        $fathers = ['أحمد', 'محمد', 'سعد', 'فهد', 'عبدالله', 'سلمان', 'ماجد', 'تركي', 'ناصر', 'سامي', 'وليد', 'هشام', 'منصور', 'بندر', 'عبدالعزيز', 'خالد', 'حمد', 'سعود', 'عمر', 'يزيد'];
        $families = ['السبيعي', 'الدوسري', 'الغامدي', 'الزهراني', 'المطيري', 'العنزي', 'الشهري', 'البقمي', 'الحارثي', 'الرشيدي', 'السلمي', 'الجهني'];
        $boys = ['يوسف', 'عمر', 'إبراهيم', 'حمزة', 'أنس', 'معاذ', 'بلال', 'زياد', 'سلطان', 'عبدالعزيز', 'مالك', 'عثمان', 'إياد', 'فيصل', 'راكان', 'نواف', 'عبدالملك', 'تميم', 'سليمان', 'هاني'];
        $girls = ['مريم', 'فاطمة', 'سارة', 'ريم', 'جود', 'لينا', 'هيا', 'رهف', 'شهد', 'أسماء', 'نوف', 'لمى'];
        $parentCount = 200;
        $parents = collect(range(0, $parentCount - 1))->map(function ($i) use ($fathers, $families) {
            $user = $this->account($fathers[$i % 20].' '.$families[intdiv($i, 20) % 12], null, User::ROLE_PARENT, '0550'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT));
            $user->parentProfile()->create(['address' => 'الرياض - حي '.['النرجس', 'الياسمين', 'الملقا', 'العارض'][$i % 4]]);

            return $user;
        });

        // 300 students, 12 per circle.
        $number = 1;
        $slot = 0;
        foreach ($circles as $ci => $circle) {
            $isGirls = $circleTeachers[$ci] >= 16;
            for ($s = 0; $s < 12; $s++) {
                // slots 0..199 -> parents 0..199, slots 200..299 -> second child of parents 0..99
                $parentIndex = $slot < $parentCount ? $slot : $slot - $parentCount;
                $slot++;
                $parent = $parents[$parentIndex];
                $first = $isGirls ? $girls[($number + $s) % count($girls)] : $boys[($number + $s * 7) % count($boys)];
                $student = new Student([
                    'full_name' => $first.' '.explode(' ', $parent->name)[0].' '.explode(' ', $parent->name)[1],
                    'gender' => $isGirls ? 'female' : 'male',
                    'birth_date' => now()->subYears(7 + ($ci % 3) * 3 + ($s % 3))->subDays($s * 17),
                    'enrollment_date' => now()->subMonths(3 + $s % 9),
                ]);
                $student->student_number = 'G'.now()->year.str_pad((string) $number++, 4, '0', STR_PAD_LEFT);
                $student->status = $s === 11 && $ci % 6 === 1 ? 'suspended' : 'active';
                $student->current_circle_id = $circle->id;
                $student->save();
                $student->parents()->attach($parent->id, ['relationship' => 'father']);
                CircleEnrollment::create(['student_id' => $student->id, 'circle_id' => $circle->id, 'joined_at' => $student->enrollment_date]);
            }
        }

        // One historical transfer to show membership history.
        $moved = Student::where('current_circle_id', $circles[0]->id)->first();
        CircleEnrollment::where('student_id', $moved->id)->update(['left_at' => now()->subMonth(), 'reason' => 'انتقال إلى مستوى أعلى']);
        CircleEnrollment::create(['student_id' => $moved->id, 'circle_id' => $circles[1]->id, 'joined_at' => now()->subMonth(), 'reason' => 'انتقال إلى مستوى أعلى']);
        $moved->current_circle_id = $circles[1]->id;
        $moved->save();

        // Withdrawn student without a circle.
        $w = new Student(['full_name' => 'خالد فهد المطيري', 'gender' => 'male', 'enrollment_date' => now()->subYear()]);
        $w->student_number = 'G'.now()->year.str_pad((string) $number++, 4, '0', STR_PAD_LEFT);
        $w->status = 'withdrawn';
        $w->withdrawn_at = now()->subMonths(2);
        $w->save();
        $w->parents()->attach($parents[4]->id, ['relationship' => 'father']);

        $surahs = Surah::all()->keyBy('id');
        $criteria = AssessmentCriterion::active()->get();

        // Attendance + memorization over the last 21 days.
        $statuses = array_merge(array_fill(0, 16, 'present'), ['late', 'late', 'absent', 'excused']);
        $comments = ['أحسنت، حفظ متقن', 'يحتاج إلى مراجعة أحكام المد', 'تحسن ملحوظ', 'ركز على مخارج الحروف', 'ممتاز، بارك الله فيك', 'يحتاج تثبيت الآيات الأخيرة'];
        $recs = ['مراجعة الصفحة السابقة يومياً', 'الاستماع لتلاوة الشيخ الحصري', 'تكرار المقطع عشر مرات قبل النوم', null];
        $start = now()->subDays(21)->startOfDay();

        $attendanceRows = [];
        foreach ($circles as $circle) {
            $students = $circle->students()->active()->get();
            $pointer = $students->mapWithKeys(fn ($st) => [$st->id => ['surah' => 114 - ($st->id % 6), 'ayah' => 1]])->all();

            for ($day = $start->copy(); $day->lte(now()); $day->addDay()) {
                if (! $circle->meetsOn($day->dayOfWeek)) {
                    continue;
                }
                $session = CircleSession::create(['circle_id' => $circle->id, 'teacher_id' => $circle->teacher_id, 'session_date' => $day->toDateString(), 'start_time' => $circle->start_time]);

                foreach ($students as $student) {
                    $status = $statuses[mt_rand(0, count($statuses) - 1)];
                    $attendanceRows[] = [
                        'circle_session_id' => $session->id, 'student_id' => $student->id, 'status' => $status,
                        'notes' => $status === 'excused' ? 'ظرف عائلي' : null, 'recorded_by' => $circle->teacher_id,
                        'created_at' => $day->toDateTimeString(), 'updated_at' => $day->toDateTimeString(),
                    ];
                    if (in_array($status, ['absent', 'excused'], true)) {
                        continue;
                    }

                    // New memorization (walking backwards through Juz Amma).
                    $p = &$pointer[$student->id];
                    $surah = $surahs[$p['surah']];
                    $to = min($surah->ayah_count, $p['ayah'] + mt_rand(3, 7));
                    $this->record($student, $circle, $day, 'new', $surah->id, $p['ayah'], $to, $comments, $recs);
                    if ($to >= $surah->ayah_count) {
                        $p['surah'] = max(78, $p['surah'] - 1);
                        $p['ayah'] = 1;
                    } else {
                        $p['ayah'] = $to + 1;
                    }

                    // Revision of an already memorized short surah.
                    if (mt_rand(0, 1)) {
                        $rev = $surahs[mt_rand(108, 114)];
                        $this->record($student, $circle, $day, mt_rand(0, 3) ? 'revision' : 'consolidation', $rev->id, 1, $rev->ayah_count, $comments, $recs);
                    }
                    unset($p);
                }
            }

            // Two assessments per student.
            foreach ($students as $student) {
                foreach ([20, 5] as $daysAgo) {
                    $assessment = new Assessment(['assessed_on' => now()->subDays($daysAgo), 'notes' => $comments[mt_rand(0, 5)], 'shared_with_parent' => true, 'percentage' => 0]);
                    $assessment->student_id = $student->id;
                    $assessment->circle_id = $circle->id;
                    $assessment->teacher_id = $circle->teacher_id;
                    $assessment->save();
                    $total = 0;
                    foreach ($criteria as $criterion) {
                        $score = (int) round($criterion->max_score * mt_rand(60, 100) / 100);
                        $assessment->scores()->create(['assessment_criterion_id' => $criterion->id, 'max_score' => $criterion->max_score, 'score' => $score]);
                        $total += $score;
                    }
                    $assessment->update(['percentage' => round($total / $criteria->sum('max_score') * 100, 2)]);
                }
            }
        }

        foreach (array_chunk($attendanceRows, 500) as $chunk) {
            AttendanceRecord::insert($chunk);
        }

        $admin = User::where('email', 'admin@gharas.test')->first();
        $announcements = [
            ['بدء التسجيل في الفصل الجديد', 'يسر مركز غراس الإعلان عن فتح باب التسجيل للفصل الدراسي الجديد في جميع الحلقات. الأماكن محدودة.', 'public'],
            ['مسابقة المركز السنوية في حفظ القرآن', 'تقام المسابقة السنوية للمركز في نهاية الشهر القادم بإذن الله، ويتم التسجيل عن طريق المحفظ.', 'all'],
            ['اجتماع المحفظين الشهري', 'يعقد اجتماع المحفظين يوم الخميس القادم بعد صلاة العصر في مكتب الإدارة.', 'teachers'],
            ['لقاء أولياء الأمور', 'ندعو أولياء الأمور الكرام لحضور اللقاء التعريفي بخطط الحفظ يوم السبت القادم.', 'parents'],
        ];
        foreach ($announcements as $i => [$title, $body, $audience]) {
            $a = new Announcement(['title' => $title, 'body' => $body, 'audience' => $audience, 'publish_at' => now()->subDays(6 - $i)]);
            $a->created_by = $admin->id;
            $a->save();
        }

        // Sample warnings: one waiting for the teacher to read, one already acknowledged.
        TeacherWarning::create(['teacher_id' => $teachers[0]->id, 'issued_by' => $admin->id, 'level' => 'notice',
            'reason' => 'نرجو الالتزام برفع حضور الحلقة في نفس اليوم، فقد تأخر تسجيله مرتين هذا الأسبوع.', 'created_at' => now()->subDay()]);
        TeacherWarning::create(['teacher_id' => $teachers[2]->id, 'issued_by' => $admin->id, 'level' => 'first', 'circle_id' => $teachers[2]->circles()->value('id'),
            'reason' => 'تكرر التأخر عن موعد بدء الحلقة ثلاث مرات هذا الشهر.', 'acknowledged_at' => now()->subDays(3), 'created_at' => now()->subDays(5)]);

        // A sample conversation.
        $child = $parents[0]->children()->first();
        $m = new Message(['recipient_id' => $child->circle->teacher_id, 'student_id' => $child->id, 'subject' => 'استفسار عن خطة الحفظ', 'body' => 'السلام عليكم شيخنا، أرجو إفادتي عن خطة الحفظ المقترحة لابني هذا الشهر. وجزاكم الله خيراً.']);
        $m->sender_id = $parents[0]->id;
        $m->save();

        $this->parentViews($parents);
    }

    /** Some parents follow closely, some rarely, so follow-up rates and «✓ تم» marks vary in the demo. */
    private function parentViews($parents): void
    {
        $rows = [];
        foreach ($parents as $i => $parent) {
            $rate = [100, 90, 75, 60, 40, 20, 0][$i % 7];
            $records = MemorizationRecord::whereIn('student_id', $parent->children()->pluck('students.id'))
                ->where('shared_with_parent', true)->orderBy('recorded_on')->get(['id', 'recorded_on']);
            foreach ($records as $record) {
                if (mt_rand(1, 100) <= $rate) {
                    $seenAt = $record->recorded_on->copy()->setTime(mt_rand(17, 22), mt_rand(0, 59));
                    $rows[] = ['memorization_record_id' => $record->id, 'parent_id' => $parent->id, 'seen_at' => $seenAt->min(now())->toDateTimeString()];
                }
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('memorization_record_views')->insertOrIgnore($chunk);
        }
    }

    private function account(string $name, ?string $email, string $role, string $phone): User
    {
        // Hash once: bcrypt for each of ~225 accounts would dominate seeding time.
        $this->passwordHash ??= Hash::make(self::PASSWORD);
        $user = new User(['name' => $name, 'email' => $email, 'phone' => $phone, 'password' => $this->passwordHash]);
        $user->role = $role;
        $user->status = 'active';
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function record(Student $student, Circle $circle, Carbon $day, string $type, int $surahId, int $from, int $to, array $comments, array $recs): void
    {
        $score = mt_rand(55, 100);
        MemorizationRecord::create([
            'student_id' => $student->id, 'circle_id' => $circle->id, 'teacher_id' => $circle->teacher_id,
            'recorded_on' => $day->toDateString(), 'type' => $type, 'surah_id' => $surahId,
            'from_ayah' => $from, 'to_ayah' => $to, 'score' => $score, 'mistakes_count' => max(0, intdiv(100 - $score, 8)),
            'notes' => $comments[mt_rand(0, count($comments) - 1)], 'recommendations' => $recs[mt_rand(0, count($recs) - 1)],
            'shared_with_parent' => mt_rand(0, 9) > 0,
        ]);
    }
}
