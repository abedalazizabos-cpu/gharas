<?php

namespace Database\Seeders;

use App\Models\MemorizationRecord;
use App\Models\Student;
use App\Models\SunnahCourse;
use App\Models\SunnahEnrollment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Trial data for قسم السنة: eight courses across the year, finished ones with results, running ones
 * waiting for grades, and planned ones. Students come from different circles than the sheikh's own.
 * Runs after DemoSeeder, and on its own with: php artisan gharas:demo-sunnah (never in production).
 *
 * Parent 0550000001 sees one finished course with a result for each child and one running course.
 */
class SunnahDemoSeeder extends Seeder
{
    private const COURSES = [
        [
            'title' => 'الأربعون النووية (الأحاديث 1 - 20)', 'category' => 'hadith', 'teacher' => 3, 'level' => ['متوسط', 'متقدم'], 'gender' => 'male', 'count' => 20,
            'weeks' => [-11, -2], 'status' => 'finished', 'schedule' => 'الأحد والثلاثاء بعد صلاة العصر', 'location' => 'المصلى الرئيس',
            'description' => 'شرح ميسر لأول عشرين حديثاً من الأربعين النووية مع حفظ متونها.',
            'objectives' => "أن يحفظ الطالب متون الأحاديث العشرين الأولى حفظاً متقناً\nأن يذكر الطالب راوي كل حديث ومعناه الإجمالي\nأن يستنبط الطالب فائدتين على الأقل من كل حديث\nأن يربط الطالب الحديث بواقعه اليومي",
            'criteria' => [['حفظ متون الأحاديث', 40], ['فهم المعاني والفوائد', 30], ['الحضور والانضباط', 15], ['المشاركة', 15]],
        ],
        [
            'title' => 'آداب حامل القرآن من كتاب التبيان', 'category' => 'quran_adab', 'teacher' => 1, 'level' => ['مبتدئ', 'متوسط'], 'gender' => 'male', 'count' => 24,
            'weeks' => [-7, -1], 'status' => 'finished', 'schedule' => 'الخميس بعد صلاة المغرب', 'location' => 'القاعة 2',
            'description' => 'مختارات من كتاب «التبيان في آداب حملة القرآن» للإمام النووي.',
            'objectives' => "أن يعرف الطالب فضل حفظ القرآن وتعلمه وتعليمه\nأن يلتزم الطالب بآداب التلاوة والمصحف\nأن يتأدب الطالب مع شيخه وزملائه في الحلقة\nأن يحفظ الطالب خمسة أحاديث في فضل القرآن",
            'criteria' => [['الاختبار النهائي', 40], ['تطبيق الآداب في الحلقة', 30], ['حفظ الأحاديث المقررة', 15], ['الحضور', 15]],
            'parent_child' => 'son',
        ],
        [
            'title' => 'صفة صلاة النبي ﷺ: الأركان والواجبات والسنن', 'category' => 'salah', 'teacher' => 8, 'level' => ['مبتدئ'], 'gender' => 'male', 'count' => 22,
            'weeks' => [-3, 3], 'status' => 'running', 'schedule' => 'الاثنين بعد صلاة العصر', 'location' => 'المصلى الرئيس',
            'description' => 'دورة عملية يتعلم فيها الطالب الصلاة كما صلاها النبي ﷺ.',
            'objectives' => "أن يميز الطالب بين أركان الصلاة وواجباتها وسننها\nأن يؤدي الطالب الصلاة أداءً صحيحاً أمام الشيخ\nأن يعرف الطالب مبطلات الصلاة وسجود السهو\nأن يحافظ الطالب على أذكار ما بعد الصلاة",
            'criteria' => [['الاختبار النظري', 35], ['التطبيق العملي للصلاة', 35], ['حفظ الأذكار', 15], ['الحضور والانضباط', 15]],
            'parent_child' => 'son',
        ],
        [
            'title' => 'فقه الطهارة: الوضوء والغسل والتيمم', 'category' => 'taharah', 'teacher' => 17, 'level' => null, 'gender' => 'female', 'count' => 18,
            'weeks' => [-9, -4], 'status' => 'finished', 'schedule' => 'الأربعاء بعد صلاة العصر', 'location' => 'قاعة الطالبات',
            'description' => 'أحكام الطهارة للفتيات بأسلوب ميسر مع التطبيق العملي.',
            'objectives' => "أن تعرف الطالبة فرائض الوضوء وسننه ونواقضه\nأن تتوضأ الطالبة وضوءاً صحيحاً أمام المعلمة\nأن تعرف الطالبة صفة الغسل ومتى يجب\nأن تعرف الطالبة أحكام التيمم والمسح على الخفين",
            'criteria' => [['الاختبار النظري', 40], ['التطبيق العملي للوضوء', 30], ['الحضور والانضباط', 15], ['المشاركة', 15]],
            'parent_child' => 'daughter',
        ],
        [
            'title' => 'السيرة النبوية: من المولد إلى الهجرة', 'category' => 'seerah', 'teacher' => 5, 'level' => ['متوسط'], 'gender' => 'male', 'count' => 25,
            'weeks' => [-4, 4], 'status' => 'running', 'schedule' => 'السبت بعد صلاة المغرب', 'location' => 'القاعة 4',
            'description' => 'المرحلة المكية من سيرة النبي ﷺ بأسلوب قصصي.',
            'objectives' => "أن يسرد الطالب أحداث المرحلة المكية بترتيبها\nأن يذكر الطالب دروساً من صبر النبي ﷺ وأصحابه\nأن يعرف الطالب أسباب الهجرة إلى الحبشة ثم إلى المدينة\nأن يحب الطالب النبي ﷺ ويقتدي به",
            'criteria' => [['الاختبار النهائي', 50], ['البحث المختصر', 20], ['الحضور والانضباط', 15], ['المشاركة والتفاعل', 15]],
        ],
        [
            'title' => 'سير أمهات المؤمنين والصحابيات', 'category' => 'sahaba', 'teacher' => 18, 'level' => null, 'gender' => 'female', 'count' => 20,
            'weeks' => [-2, 5], 'status' => 'running', 'schedule' => 'الأحد بعد صلاة العصر', 'location' => 'قاعة الطالبات',
            'description' => 'نماذج مشرقة من حياة أمهات المؤمنين والصحابيات رضي الله عنهن.',
            'objectives' => "أن تعرف الطالبة أمهات المؤمنين وأبرز صفاتهن\nأن تستخرج الطالبة قدوة عملية من كل سيرة\nأن تعرض الطالبة سيرة صحابية أمام زميلاتها",
            'criteria' => [['الاختبار النهائي', 40], ['العرض والإلقاء', 30], ['الحضور والانضباط', 15], ['المشاركة', 15]],
            'parent_child' => 'daughter',
        ],
        [
            'title' => 'رجال حول الرسول ﷺ: العشرة المبشرون بالجنة', 'category' => 'sahaba', 'teacher' => 14, 'level' => ['متقدم'], 'gender' => 'male', 'count' => 16,
            'weeks' => [1, 7], 'status' => 'planned', 'schedule' => 'الثلاثاء بعد صلاة المغرب', 'location' => 'القاعة 1',
            'description' => 'سير العشرة المبشرين بالجنة ومواقفهم مع النبي ﷺ.',
            'objectives' => "أن يحفظ الطالب أسماء العشرة المبشرين بالجنة\nأن يذكر الطالب موقفاً مشهوراً لكل واحد منهم\nأن يستنبط الطالب صفات تؤهل المسلم لرضا الله",
            'criteria' => [['الاختبار النهائي', 50], ['الحضور والانضباط', 20], ['المشاركة والتفاعل', 15], ['الواجبات والبحوث', 15]],
        ],
        [
            'title' => 'لمحات من الإعجاز البياني في القرآن الكريم', 'category' => 'ijaz', 'teacher' => 2, 'level' => ['متقدم'], 'gender' => 'male', 'count' => 15,
            'weeks' => [2, 8], 'status' => 'planned', 'schedule' => 'الأربعاء بعد صلاة المغرب', 'location' => 'المكتبة',
            'description' => 'أمثلة مختارة من بلاغة القرآن ونظمه لطلاب المستوى المتقدم.',
            'objectives' => "أن يعرف الطالب معنى الإعجاز ووجوهه\nأن يتذوق الطالب أمثلة من بلاغة القرآن في السور التي يحفظها\nأن يرد الطالب على شبهة واحدة بأسلوب علمي مهذب",
            'criteria' => [['الاختبار النهائي', 50], ['البحث المختصر', 25], ['الحضور', 10], ['المشاركة', 15]],
        ],
    ];

    private const NOTES_GOOD = ['متميز ومشارك بفاعلية', 'حفظ متقن وفهم جيد، بارك الله فيه', 'أداء ممتاز وسلوك قدوة', 'مستوى رائع، يُرشَّح للدورة التالية'];

    private const NOTES_MID = ['جيد، ويحتاج مزيداً من المراجعة', 'يحتاج إلى التركيز في الشرح', 'تحسن ملحوظ في آخر الدورة'];

    private const NOTES_WEAK = ['كثرة الغياب أثرت على مستواه', 'يحتاج إلى إعادة الدورة', 'لم يكمل المطلوب من الحفظ'];

    public function run(): void
    {
        mt_srand(1447);
        DB::transaction(fn () => $this->seed());
    }

    /** Remove all courses so the trial data can be built again. */
    public static function clear(): void
    {
        SunnahCourse::withTrashed()->forceDelete();
    }

    private function seed(): void
    {
        $admin = User::admins()->where('role', User::ROLE_ADMIN)->orderBy('id')->first() ?? User::admins()->orderBy('id')->firstOrFail();
        $teachers = User::role(User::ROLE_TEACHER)->get()->keyBy(fn ($t) => (int) preg_replace('/\D/', '', (string) $t->email));
        $students = Student::active()->whereNotNull('current_circle_id')->with(['circle', 'parents'])->get();
        if ($teachers->isEmpty() || $students->isEmpty()) {
            return;
        }
        $parentChildren = User::role(User::ROLE_PARENT)->where('phone', '0550000001')->first()?->children()->get() ?? collect();

        foreach (self::COURSES as $def) {
            $teacher = $teachers[$def['teacher']] ?? $teachers->first();
            $start = now()->startOfWeek(\Carbon\Carbon::SUNDAY)->addWeeks($def['weeks'][0]);
            $end = now()->startOfWeek(\Carbon\Carbon::SUNDAY)->addWeeks($def['weeks'][1])->addDays(4);

            $course = new SunnahCourse([
                'title' => $def['title'], 'category' => $def['category'], 'description' => $def['description'], 'objectives' => $def['objectives'],
                'teacher_id' => $teacher->id, 'starts_on' => $start->toDateString(), 'ends_on' => $end->toDateString(),
                'schedule' => $def['schedule'], 'location' => $def['location'], 'status' => $def['status'], 'pass_score' => 70, 'results_to_parents' => true,
            ]);
            $course->created_by = $admin->id;
            $course->created_at = $start->copy()->subDays(10);
            $course->save();
            $criteria = collect($def['criteria'])->map(fn ($c, $i) => $course->criteria()->create(['name' => $c[0], 'max_score' => $c[1], 'sort_order' => $i + 1]));

            $picked = $this->pick($students, $def, $teacher, $parentChildren);
            foreach ($picked as $student) {
                $enrollment = SunnahEnrollment::create(['sunnah_course_id' => $course->id, 'student_id' => $student->id, 'status' => 'enrolled']);
                if ($def['status'] === 'finished') {
                    $this->grade($enrollment, $criteria, $teacher, $end->copy()->subDays(mt_rand(0, 2)), $parentChildren->contains('id', $student->id));
                }
            }
        }
    }

    /** Students of the course: from several circles, rarely the sheikh's own, plus the demo parent's child. */
    private function pick(Collection $students, array $def, User $teacher, Collection $parentChildren): Collection
    {
        $pool = $students->filter(fn ($s) => $s->gender === $def['gender'] && (! $def['level'] || in_array($s->circle?->level, $def['level'], true)))
            ->sortBy(fn ($s) => [$s->circle?->teacher_id === $teacher->id ? 1 : 0, mt_rand()])->values();
        $picked = $pool->take($def['count']);

        $want = $def['parent_child'] ?? null;
        $child = $want ? $parentChildren->first(fn ($c) => $c->gender === ($want === 'daughter' ? 'female' : 'male')) : null;
        if ($child && ! $picked->contains('id', $child->id)) {
            $picked = $picked->slice(0, $def['count'] - 1)->push($child);
        }

        return $picked->values();
    }

    /** A believable result: most students between good and excellent, a few below the pass score. */
    private function grade(SunnahEnrollment $e, Collection $criteria, User $teacher, $on, bool $showcase = false): void
    {
        $roll = $showcase ? 100 : mt_rand(1, 100);
        $level = $roll <= 8 ? mt_rand(45, 68) : ($roll <= 30 ? mt_rand(70, 82) : mt_rand(83, 99));
        $total = 0;
        $max = 0;
        foreach ($criteria as $c) {
            $pct = max(0, min(100, $level + mt_rand(-10, 10)));
            $score = (int) round($c->max_score * $pct / 100);
            $e->scores()->create(['sunnah_criterion_id' => $c->id, 'max_score' => $c->max_score, 'score' => $score]);
            $total += $score;
            $max += $c->max_score;
        }
        $percentage = round($total / $max * 100, 2);
        $notes = mt_rand(1, 100) <= 45
            ? ($percentage >= 85 ? self::NOTES_GOOD : ($percentage >= 70 ? self::NOTES_MID : self::NOTES_WEAK))[mt_rand(0, 2)]
            : null;
        $e->fill([
            'percentage' => $percentage, 'grade' => MemorizationRecord::gradeForScore((int) floor($percentage)),
            'status' => $percentage >= 70 ? 'passed' : 'failed', 'notes' => $notes,
            'evaluated_on' => $on->toDateString(), 'evaluated_by' => $teacher->id,
        ])->save();
    }
}
