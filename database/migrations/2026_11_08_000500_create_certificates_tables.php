<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Certificates for Sunnah courses and for individual and combined quality tests. The admin writes
 * the wording of each template and issues every certificate; nothing is issued automatically.
 * Also: a Sunnah grading rule can be filled from the real attendance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            // course | individual | combined
            $table->string('kind', 20)->unique();
            $table->string('title');
            $table->string('intro')->nullable();
            $table->text('body');
            $table->string('closing')->nullable();
            $table->string('signer1_title')->nullable();
            $table->string('signer1_name')->nullable();
            $table->string('signer2_title')->nullable();
            $table->string('signer2_name')->nullable();
            $table->string('footer')->nullable();
            $table->string('color', 7)->default('#1b6b50');
            $table->timestamps();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->index();
            $table->foreignId('sunnah_enrollment_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('quality_test_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->date('issued_on');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            // The wording as it was when issued, so editing a template never changes an issued certificate.
            $table->json('content');
            $table->timestamps();
        });

        Schema::table('sunnah_criteria', function (Blueprint $table) {
            $table->boolean('from_attendance')->default(false)->after('max_score');
        });

        $now = now();
        $signers = ['signer1_title' => 'مدير المركز', 'signer1_name' => null, 'footer' => 'وثيقة صادرة من {المركز} برقم {رقم_الشهادة} بتاريخ {التاريخ}'];
        DB::table('certificate_templates')->insert([
            $signers + [
                'kind' => 'course', 'title' => 'شهادة إتمام دورة', 'intro' => 'يشهد {المركز} بأن {اللقب}',
                'body' => 'قد أتمّ{ت} دورة «{الدورة}» في قسم السنة، التي قدّمها {الشيخ}، واجتاز{ت}ها بتقدير {التقدير} ونسبة {النسبة}.',
                'closing' => 'سائلين الله تعالى {له} التوفيق والسداد، وأن يجعل{ه} من أهل القرآن والسنة.',
                'signer2_title' => 'شيخ الدورة', 'signer2_name' => '{الشيخ}', 'color' => '#1b6b50', 'created_at' => $now, 'updated_at' => $now,
            ],
            $signers + [
                'kind' => 'individual', 'title' => 'شهادة اجتياز اختبار', 'intro' => 'تشهد لجنة الجودة في {المركز} بأن {اللقب}',
                'body' => 'قد اجتاز{ت} الاختبار المنفرد في {الجزء} حفظاً وإتقاناً، بتقدير {التقدير} ونسبة {النسبة}.',
                'closing' => 'نسأل الله أن يبارك {له} في حفظ{ه} وأن يجعل القرآن حجة {له}.',
                'signer2_title' => 'عضو لجنة الجودة', 'signer2_name' => '{المختبر}', 'color' => '#2f5fb3', 'created_at' => $now, 'updated_at' => $now,
            ],
            $signers + [
                'kind' => 'combined', 'title' => 'شهادة اجتياز الاختبار المجتمع', 'intro' => 'تشهد لجنة الجودة في {المركز} بأن {اللقب}',
                'body' => 'قد اجتاز{ت} الاختبار المجتمع في {الأجزاء} حفظاً وإتقاناً، بتقدير {التقدير} ونسبة {النسبة}.',
                'closing' => 'نسأل الله أن يتم {له} حفظ كتابه وأن يرفع{ه} به درجات.',
                'signer2_title' => 'عضو لجنة الجودة', 'signer2_name' => '{المختبر}', 'color' => '#b8892b', 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        // The attendance rules of existing courses now follow the real attendance.
        DB::table('sunnah_criteria')->where('name', 'like', '%الحضور%')->update(['from_attendance' => true]);
    }

    public function down(): void
    {
        Schema::table('sunnah_criteria', fn (Blueprint $table) => $table->dropColumn('from_attendance'));
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('certificate_templates');
    }
};
