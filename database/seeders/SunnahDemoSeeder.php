<?php

namespace Database\Seeders;

use App\Models\MemorizationRecord;
use App\Models\Student;
use App\Models\SunnahCourse;
use App\Models\SunnahEnrollment;
use App\Models\SunnahPost;
use App\Models\SunnahPostFollow;
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
        [
            'title' => 'الأربعون النووية (الأحاديث 21 - 42)', 'category' => 'hadith', 'teacher' => 3, 'level' => ['متوسط', 'متقدم'], 'gender' => 'male', 'count' => 22,
            'weeks' => [-1, 9], 'status' => 'running', 'schedule' => 'الأحد والثلاثاء بعد صلاة العصر', 'location' => 'المصلى الرئيس',
            'description' => 'الجزء الثاني من الأربعين النووية لمن أتم الجزء الأول.',
            'objectives' => "أن يحفظ الطالب الأحاديث من 21 إلى 42\nأن يشرح الطالب معنى كل حديث بأسلوبه\nأن يربط الطالب الأحاديث بأخلاقه وتعاملاته",
            'criteria' => [['حفظ متون الأحاديث', 40], ['فهم المعاني والفوائد', 30], ['الحضور والانضباط', 15], ['المشاركة', 15]],
        ],
        [
            'title' => 'آداب طالب العلم وحامل القرآن', 'category' => 'quran_adab', 'teacher' => 6, 'level' => ['متقدم'], 'gender' => 'male', 'count' => 18,
            'weeks' => [-30, -22], 'status' => 'finished', 'schedule' => 'الاثنين بعد صلاة المغرب', 'location' => 'المكتبة',
            'description' => 'مختارات من «حلية طالب العلم» مع آداب حملة القرآن.',
            'objectives' => "أن يتحلى الطالب بالإخلاص في طلب العلم\nأن يحترم الطالب شيخه ومجلس العلم\nأن يرتب الطالب وقته بين الحفظ والمراجعة والدراسة",
            'criteria' => [['الاختبار النهائي', 50], ['الحضور والانضباط', 20], ['المشاركة والتفاعل', 15], ['الواجبات والبحوث', 15]],
        ],
        [
            'title' => 'الإعجاز العلمي في آيات الكون', 'category' => 'ijaz', 'teacher' => 2, 'level' => ['متقدم', 'متوسط'], 'gender' => 'male', 'count' => 20,
            'weeks' => [-20, -13], 'status' => 'finished', 'schedule' => 'الأربعاء بعد صلاة المغرب', 'location' => 'القاعة 6',
            'description' => 'وقفات مع آيات الخلق والكون وما فيها من دلائل القدرة.',
            'objectives' => "أن يتأمل الطالب آيات الكون في السور التي يحفظها\nأن يفرق الطالب بين الحقيقة العلمية والنظرية\nأن يزداد يقين الطالب بأن القرآن من عند الله",
            'criteria' => [['الاختبار النهائي', 50], ['البحث المختصر', 25], ['الحضور', 10], ['المشاركة', 15]],
        ],
        [
            'title' => 'صفة الوضوء والصلاة للصغار', 'category' => 'salah', 'teacher' => 10, 'level' => ['مبتدئ'], 'gender' => 'male', 'count' => 26,
            'weeks' => [-16, -10], 'status' => 'finished', 'schedule' => 'السبت والثلاثاء بعد صلاة العصر', 'location' => 'المصلى الرئيس',
            'description' => 'دورة عملية مبسطة للأطفال في الوضوء والصلاة.',
            'objectives' => "أن يتوضأ الطفل وضوءاً صحيحاً\nأن يصلي الطفل صلاة صحيحة بأركانها\nأن يحفظ الطفل أذكار الوضوء والصلاة",
            'criteria' => [['التطبيق العملي للوضوء', 30], ['التطبيق العملي للصلاة', 40], ['حفظ الأذكار', 15], ['الحضور', 15]],
        ],
        [
            'title' => 'السيرة النبوية: العهد المدني', 'category' => 'seerah', 'teacher' => 5, 'level' => ['متوسط', 'متقدم'], 'gender' => 'male', 'count' => 24,
            'weeks' => [-26, -18], 'status' => 'finished', 'schedule' => 'السبت بعد صلاة المغرب', 'location' => 'القاعة 4',
            'description' => 'من بناء المسجد النبوي إلى وفاة النبي ﷺ.',
            'objectives' => "أن يعرف الطالب أسس بناء المجتمع في المدينة\nأن يذكر الطالب أهم الغزوات ودروسها\nأن يستخلص الطالب صفات القيادة من سيرة النبي ﷺ",
            'criteria' => [['الاختبار النهائي', 50], ['البحث المختصر', 20], ['الحضور والانضباط', 15], ['المشاركة والتفاعل', 15]],
        ],
        [
            'title' => 'الخلفاء الراشدون', 'category' => 'sahaba', 'teacher' => 9, 'level' => ['متوسط'], 'gender' => 'male', 'count' => 20,
            'weeks' => [-6, 2], 'status' => 'running', 'schedule' => 'الخميس بعد صلاة العصر', 'location' => 'القاعة 3',
            'description' => 'سير أبي بكر وعمر وعثمان وعلي رضي الله عنهم.',
            'objectives' => "أن يعرف الطالب فضائل الخلفاء الراشدين\nأن يذكر الطالب أبرز أعمال كل خليفة\nأن يحب الطالب الصحابة ويقتدي بهم",
            'criteria' => [['الاختبار النهائي', 50], ['الحضور والانضباط', 20], ['المشاركة والتفاعل', 15], ['الواجبات والبحوث', 15]],
        ],
        [
            'title' => 'أحاديث الأخلاق والآداب للفتيات', 'category' => 'hadith', 'teacher' => 19, 'level' => null, 'gender' => 'female', 'count' => 22,
            'weeks' => [-14, -7], 'status' => 'finished', 'schedule' => 'الاثنين بعد صلاة العصر', 'location' => 'قاعة الطالبات',
            'description' => 'عشرون حديثاً في الأخلاق والحياء وبر الوالدين.',
            'objectives' => "أن تحفظ الطالبة الأحاديث المقررة\nأن تطبق الطالبة آداب الحديث في بيتها\nأن تذكر الطالبة موقفاً عملياً لكل خلق",
            'criteria' => [['حفظ الأحاديث المقررة', 40], ['فهم المعاني والفوائد', 30], ['الحضور والانضباط', 15], ['المشاركة', 15]],
        ],
        [
            'title' => 'صفة صلاة المرأة وأحكامها', 'category' => 'salah', 'teacher' => 20, 'level' => null, 'gender' => 'female', 'count' => 20,
            'weeks' => [-3, 4], 'status' => 'running', 'schedule' => 'الثلاثاء بعد صلاة العصر', 'location' => 'قاعة الطالبات',
            'description' => 'أحكام الصلاة الخاصة بالفتيات مع التطبيق العملي.',
            'objectives' => "أن تؤدي الطالبة الصلاة بأركانها وواجباتها\nأن تعرف الطالبة أحكام اللباس في الصلاة\nأن تحافظ الطالبة على السنن الرواتب",
            'criteria' => [['الاختبار النظري', 35], ['التطبيق العملي للصلاة', 35], ['حفظ الأذكار', 15], ['الحضور والانضباط', 15]],
        ],
        [
            'title' => 'آداب تلاوة القرآن للفتيات', 'category' => 'quran_adab', 'teacher' => 18, 'level' => null, 'gender' => 'female', 'count' => 18,
            'weeks' => [-24, -18], 'status' => 'finished', 'schedule' => 'الخميس بعد صلاة العصر', 'location' => 'قاعة الطالبات',
            'description' => 'آداب التلاوة والاستماع وفضل أهل القرآن.',
            'objectives' => "أن تلتزم الطالبة بآداب التلاوة\nأن تعرف الطالبة فضل حفظ القرآن\nأن تحفظ الطالبة خمسة أحاديث في فضل القرآن",
            'criteria' => [['الاختبار النهائي', 40], ['تطبيق الآداب في الحلقة', 30], ['حفظ الأحاديث المقررة', 15], ['الحضور', 15]],
        ],
        [
            'title' => 'قصص الأنبياء في القرآن', 'category' => 'other', 'teacher' => 12, 'level' => ['مبتدئ', 'متوسط'], 'gender' => 'male', 'count' => 28,
            'weeks' => [-2, 6], 'status' => 'running', 'schedule' => 'الأربعاء بعد صلاة العصر', 'location' => 'القاعة 5',
            'description' => 'قصص الأنبياء كما وردت في القرآن مع الدروس المستفادة.',
            'objectives' => "أن يسرد الطالب قصة خمسة من الأنبياء\nأن يستخرج الطالب درساً عملياً من كل قصة\nأن يربط الطالب القصة بالآيات التي يحفظها",
            'criteria' => [['الاختبار النهائي', 50], ['الحضور والانضباط', 20], ['المشاركة والتفاعل', 15], ['الواجبات والبحوث', 15]],
        ],
    ];

    /** [type, title, details] in the order the sheikh gives them during the course. */
    private const POSTS = [
        'hadith' => [
            ['instruction', 'منهج الدورة وطريقة الحفظ', 'نحفظ حديثين كل أسبوع: نقرأ الحديث في البيت ثلاث مرات قبل الدرس، ونسمعه في الدرس التالي.'],
            ['homework', 'حفظ حديث «إنما الأعمال بالنيات» مع معناه', 'يكتب الطالب في دفتره ثلاث فوائد من الحديث.'],
            ['homework', 'حفظ حديث جبريل عليه السلام', 'التركيز على أركان الإسلام والإيمان والإحسان.'],
            ['instruction', 'مراجعة الأحاديث العشرة الأولى', 'نرجو من ولي الأمر أن يسمع لابنه الأحاديث قبل اختبار منتصف الدورة.'],
            ['homework', 'تطبيق حديث «من حسن إسلام المرء تركه ما لا يعنيه»', 'يذكر الطالب موقفاً طبّق فيه الحديث خلال الأسبوع.'],
        ],
        'quran_adab' => [
            ['instruction', 'آداب حمل المصحف والجلوس للتلاوة', 'يحضر الطالب بطهارة، ويحمل مصحفه باحترام، ويجلس مستقبلاً القبلة.'],
            ['homework', 'حفظ حديث «خيركم من تعلم القرآن وعلمه»', 'مع ذكر معناه بأسلوب الطالب.'],
            ['homework', 'جدول تلاوة يومي لمدة أسبوع', 'يسجل الطالب وقت تلاوته اليومية ويوقّع ولي الأمر على الجدول.'],
            ['instruction', 'الاختبار النهائي للدورة', 'الاختبار في آخر لقاء: أسئلة شفهية عن آداب التلاوة وحفظ الأحاديث المقررة.'],
        ],
        'salah' => [
            ['instruction', 'إحضار سجادة صغيرة للتطبيق العملي', 'سنطبق الصلاة عملياً في كل لقاء.'],
            ['homework', 'كتابة أركان الصلاة الأربعة عشر', 'يكتبها الطالب في دفتره ويحفظها.'],
            ['homework', 'حفظ أذكار ما بعد الصلاة', 'يرددها الطالب بعد كل صلاة هذا الأسبوع، ونرجو من ولي الأمر متابعته.'],
            ['instruction', 'متابعة صلاة الابن في البيت', 'لاحظوا الطمأنينة في الركوع والسجود، وأخبرونا بأي ملاحظة.'],
        ],
        'taharah' => [
            ['instruction', 'منهج الدورة', 'الوضوء ثم الغسل ثم التيمم، مع تطبيق عملي في كل لقاء.'],
            ['homework', 'كتابة فرائض الوضوء ونواقضه', 'في دفتر الدورة مع الدليل إن أمكن.'],
            ['homework', 'تطبيق الوضوء أمام الوالدة', 'نرجو من الوالدة متابعة وضوء ابنتها وتصحيحه.'],
            ['instruction', 'الاختبار العملي والنظري', 'يكون الاختبار في اللقاء الأخير، الحضور مبكراً.'],
        ],
        'seerah' => [
            ['instruction', 'دفتر السيرة', 'يخصص الطالب دفتراً يكتب فيه خلاصة كل درس.'],
            ['homework', 'تلخيص قصة شق الصدر', 'في نصف صفحة بأسلوب الطالب.'],
            ['homework', 'رسم خريطة لطريق الهجرة', 'يمكن الاستعانة بالأطلس، ونرجو مساعدة الأبناء دون أن تُرسم عنهم.'],
            ['instruction', 'بحث مختصر عن أحد السابقين إلى الإسلام', 'يُسلَّم البحث في آخر الدورة، صفحة واحدة.'],
        ],
        'default' => [
            ['instruction', 'منهج الدورة وطريقة التقييم', 'التقييم على الاختبار النهائي والحضور والمشاركة والواجبات.'],
            ['homework', 'قراءة الدرس الأول وتلخيصه', 'نصف صفحة في دفتر الدورة.'],
            ['homework', 'اختيار قدوة من الدرس وكتابة سبب اختيارها', 'تُعرض في اللقاء القادم.'],
            ['instruction', 'التحضير للعرض', 'كل طالب يعرض ما كتبه في دقيقتين.'],
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
            if ($def['status'] !== 'planned') {
                $this->attendance($course, $def, $teacher, $picked, $start, $end, $parentChildren);
                $this->posts($course, $def, $teacher, $picked, $start, $parentChildren);
                $this->gradeHomework($course, $teacher, $parentChildren);
            }
        }
    }

    /** Instructions and homework given so far, with how the parents followed them. */
    private function posts(SunnahCourse $course, array $def, User $teacher, Collection $students, $start, Collection $parentChildren): void
    {
        $items = self::POSTS[$def['category']] ?? self::POSTS['default'];
        $today = now()->startOfDay();
        $last = $def['status'] === 'finished' ? now()->startOfWeek(\Carbon\Carbon::SUNDAY)->addWeeks($def['weeks'][1]) : $today;
        $count = count($items);
        foreach ($items as $i => [$type, $title, $body]) {
            $at = $start->copy()->addDays((int) round($i * max(3, $start->diffInDays($last)) / $count))->setTime(17, 30);
            if ($at->gt(now())) {
                break;
            }
            $post = new SunnahPost(['type' => $type, 'title' => $title, 'body' => $body, 'due_on' => $type === 'homework' ? $at->copy()->addDays(5)->toDateString() : null, 'max_score' => $type === 'homework' ? 10 : null]);
            $post->sunnah_course_id = $course->id;
            $post->author_id = $teacher->id;
            $post->created_at = $post->updated_at = $at;
            $post->save();

            $isLatest = $i === $count - 1 || $start->copy()->addDays((int) round(($i + 1) * max(3, $start->diffInDays($last)) / $count))->gt(now());
            foreach ($students as $student) {
                $showcase = $parentChildren->contains('id', $student->id);
                // The demo parent follows everything except the newest item of a running course.
                $seen = $showcase ? ! ($isLatest && $def['status'] === 'running') : mt_rand(1, 100) <= ($isLatest ? 55 : 80);
                if (! $seen) {
                    continue;
                }
                $seenAt = $at->copy()->addHours(mt_rand(2, 40));
                $doneAt = $type === 'homework' && ($showcase || mt_rand(1, 100) <= 75) ? $seenAt->copy()->addHours(mt_rand(5, 80)) : null;
                SunnahPostFollow::create([
                    'sunnah_post_id' => $post->id, 'student_id' => $student->id, 'parent_id' => $student->parents()->value('users.id'),
                    'seen_at' => $seenAt->min(now()), 'done_at' => $doneAt?->min(now()),
                ]);
            }
        }

        // In a running course the demo parent still has the newest homework to confirm.
        if ($def['status'] === 'running' && ($latest = SunnahPost::where('sunnah_course_id', $course->id)->where('type', 'homework')->latest()->first())) {
            SunnahPostFollow::where('sunnah_post_id', $latest->id)->whereIn('student_id', $parentChildren->pluck('id'))->update(['done_at' => null]);
        }
    }

    private const TOPICS = [
        'hadith' => ['التعريف بالدورة والمتن', 'حديث النية', 'حديث جبريل', 'أركان الإسلام', 'الحلال بيّن', 'الدين النصيحة', 'مراجعة', 'حديث «لا ضرر ولا ضرار»', 'حديث «اتق الله حيثما كنت»', 'الاختبار'],
        'quran_adab' => ['فضل القرآن وأهله', 'آداب التلاوة', 'آداب المصحف', 'الإخلاص في الحفظ', 'آداب الحلقة', 'مراجعة', 'الاختبار'],
        'salah' => ['شروط الصلاة', 'أركان الصلاة', 'واجبات الصلاة', 'سنن الصلاة', 'مبطلات الصلاة', 'سجود السهو', 'تطبيق عملي', 'الاختبار'],
        'taharah' => ['أنواع المياه', 'فرائض الوضوء', 'سنن الوضوء ونواقضه', 'الغسل', 'التيمم', 'تطبيق عملي', 'الاختبار'],
        'seerah' => ['نسب النبي ﷺ ومولده', 'نشأته ﷺ', 'البعثة', 'الدعوة السرية والجهرية', 'الهجرة إلى الحبشة', 'عام الحزن', 'الهجرة إلى المدينة', 'مراجعة', 'الاختبار'],
        'sahaba' => ['مكانة الصحابة', 'أبو بكر الصديق', 'عمر بن الخطاب', 'عثمان بن عفان', 'علي بن أبي طالب', 'مراجعة', 'الاختبار'],
        'default' => ['مقدمة الدورة', 'الدرس الأول', 'الدرس الثاني', 'الدرس الثالث', 'عرض الطلاب', 'مراجعة', 'الاختبار'],
    ];

    /** Meeting days read from the schedule text («الأحد والثلاثاء بعد صلاة العصر»). */
    private function days(string $schedule): array
    {
        $map = ['السبت' => 6, 'الأحد' => 0, 'الاثنين' => 1, 'الثلاثاء' => 2, 'الأربعاء' => 3, 'الخميس' => 4];
        $days = [];
        foreach ($map as $name => $d) {
            if (str_contains($schedule, $name)) {
                $days[] = $d;
            }
        }

        return $days ?: [0];
    }

    /** One attendance sheet per meeting until today; each student has his own habit of attendance. */
    private function attendance(SunnahCourse $course, array $def, User $teacher, Collection $students, $start, $end, Collection $parentChildren): void
    {
        $days = $this->days($def['schedule']);
        $topics = self::TOPICS[$def['category']] ?? self::TOPICS['default'];
        $habit = $students->mapWithKeys(fn ($s) => [$s->id => $parentChildren->contains('id', $s->id) ? 95 : [97, 93, 90, 86, 78][mt_rand(0, 4)]]);
        $rows = [];
        $n = 0;
        $last = $end->copy()->min(now());
        for ($day = $start->copy(); $day->lte($last); $day->addDay()) {
            if (! in_array($day->dayOfWeek, $days, true)) {
                continue;
            }
            $session = \App\Models\SunnahSession::create([
                'sunnah_course_id' => $course->id, 'held_on' => $day->toDateString(),
                'topic' => $topics[min($n, count($topics) - 1)] ?? null, 'taken_by' => $teacher->id,
            ]);
            $n++;
            foreach ($students as $s) {
                $roll = mt_rand(1, 100);
                $status = $roll <= $habit[$s->id] ? ($roll % 9 === 0 ? 'late' : 'present') : ($roll % 3 === 0 ? 'excused' : 'absent');
                $rows[] = ['sunnah_session_id' => $session->id, 'student_id' => $s->id, 'status' => $status,
                    'note' => $status === 'excused' ? ['مريض', 'سفر مع الأسرة', 'موعد طبي'][mt_rand(0, 2)] : null,
                    'created_at' => $day, 'updated_at' => $day];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            \App\Models\SunnahAttendance::insert($chunk);
        }
    }

    /** The sheikh grades homework that is past due; most handed it in. */
    private function gradeHomework(SunnahCourse $course, User $teacher, Collection $parentChildren): void
    {
        $feedback = ['أحسنت، بارك الله فيك', 'عمل متقن', 'يحتاج مزيداً من العناية', 'خط جميل وترتيب حسن', 'ناقص، أكمله', 'ممتاز'];
        $ids = SunnahEnrollment::where('sunnah_course_id', $course->id)->pluck('student_id');
        $newest = $course->status === 'running' ? SunnahPost::where('sunnah_course_id', $course->id)->where('type', 'homework')->latest()->value('id') : null;
        foreach (SunnahPost::where('sunnah_course_id', $course->id)->where('type', 'homework')->whereDate('due_on', '<', now()->toDateString())->get() as $post) {
            foreach ($ids as $sid) {
                $showcase = $parentChildren->contains('id', $sid);
                if ($showcase && $post->id === $newest) {
                    continue; // the demo parent still has this one to confirm
                }
                if (! $showcase && mt_rand(1, 100) > 88) {
                    continue; // not handed in yet
                }
                $score = $showcase ? mt_rand(8, 10) : max(3, min(10, (int) round(mt_rand(55, 100) / 10)));
                $f = SunnahPostFollow::firstOrNew(['sunnah_post_id' => $post->id, 'student_id' => $sid]);
                $f->fill([
                    'score' => $score, 'feedback' => mt_rand(1, 100) <= 40 ? $feedback[$score >= 9 ? [0, 1, 5][mt_rand(0, 2)] : ($score >= 7 ? 3 : [2, 4][mt_rand(0, 1)])] : null,
                    'graded_at' => $post->due_on->copy()->addDays(mt_rand(1, 3))->setTime(19, 0)->min(now()), 'graded_by' => $teacher->id,
                    'done_at' => $f->done_at ?? $post->due_on->copy()->setTime(18, 0),
                ])->save();
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
