<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * لجنة الجودة: examiners chosen by the admin, one individual test per juz, and the staged
 * combined tests (30-28, 30-26, 25-23, 25-21, 30-21 ... then the whole mushaf).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Ajza the student already knew when joining, counted from juz 30 down.
            $table->unsignedTinyInteger('memorized_juz_at_join')->default(0)->after('enrollment_date');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_quality_examiner')->default(false)->after('role');
        });

        Schema::create('quality_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('max_score')->default(10);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('combined_test_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            // Always stored as from_juz >= to_juz (e.g. 30 -> 28).
            $table->unsignedTinyInteger('from_juz');
            $table->unsignedTinyInteger('to_juz');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('quality_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            // individual | combined
            $table->string('kind', 20)->index();
            $table->unsignedTinyInteger('juz')->nullable();
            $table->foreignId('stage_id')->nullable()->constrained('combined_test_stages')->nullOnDelete();
            // Snapshot of the range, so editing the ladder later never changes old results.
            $table->unsignedTinyInteger('from_juz');
            $table->unsignedTinyInteger('to_juz');
            $table->foreignId('examiner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('assigned_on');
            // pending | passed | failed
            $table->string('status', 20)->default('pending')->index();
            $table->date('tested_on')->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->string('grade', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_id', 'kind', 'status']);
        });

        Schema::create('quality_test_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quality_criterion_id')->constrained('quality_criteria')->cascadeOnDelete();
            $table->unsignedTinyInteger('max_score');
            $table->unsignedTinyInteger('score');
            $table->unique(['quality_test_id', 'quality_criterion_id'], 'quality_test_scores_unique');
        });

        $now = now();
        DB::table('quality_criteria')->insert(collect([
            ['الحفظ والإتقان', 60], ['أحكام التجويد', 20], ['حسن الأداء والتلاوة', 10], ['سرعة الاستحضار', 10],
        ])->map(fn ($c, $i) => ['name' => $c[0], 'max_score' => $c[1], 'sort_order' => $i + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now])->all());

        $stages = [
            [30, 28, null], [30, 26, null], [25, 23, null], [25, 21, null], [30, 21, 'العشرة الأولى'],
            [20, 18, null], [20, 16, null], [15, 13, null], [15, 11, null], [20, 11, 'العشرة الثانية'],
            [10, 8, null], [10, 6, null], [5, 3, null], [5, 1, null], [10, 1, 'العشرة الثالثة'],
            [30, 1, 'المصحف كاملاً'],
        ];
        DB::table('combined_test_stages')->insert(collect($stages)->map(fn ($s, $i) => [
            'from_juz' => $s[0], 'to_juz' => $s[1], 'name' => $s[2], 'sort_order' => $i + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ])->all());

        if (! DB::table('settings')->where('key', 'quality_pass_score')->exists()) {
            DB::table('settings')->insert(['key' => 'quality_pass_score', 'value' => '80', 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_test_scores');
        Schema::dropIfExists('quality_tests');
        Schema::dropIfExists('combined_test_stages');
        Schema::dropIfExists('quality_criteria');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_quality_examiner'));
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn('memorized_juz_at_join'));
        DB::table('settings')->where('key', 'quality_pass_score')->delete();
    }
};
