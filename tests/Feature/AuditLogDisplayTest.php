<?php

namespace Tests\Feature;

use App\Models\MemorizationRecord;
use Tests\TestCase;

class AuditLogDisplayTest extends TestCase
{
    public function test_audit_log_shows_actions_and_changes_in_plain_arabic(): void
    {
        $teacher = $this->teacher();
        $student = $this->studentIn($this->circleFor($teacher));
        $record = MemorizationRecord::create(['student_id' => $student->id, 'teacher_id' => $teacher->id, 'recorded_on' => now(), 'type' => 'new',
            'surah_id' => 112, 'from_ayah' => 1, 'to_ayah' => 4, 'pages' => 0.25, 'score' => 70, 'shared_with_parent' => false]);

        $this->actingAs($teacher)->put(route('teacher.memorization.update', $record), [
            'recorded_on' => now()->toDateString(), 'type' => 'new', 'surah_id' => 112, 'from_ayah' => 1, 'to_ayah' => 4,
            'pages' => 0.5, 'score' => 88, 'shared_with_parent' => 1,
        ])->assertRedirect();

        $this->actingAs($this->admin())->get(route('admin.audit.index'))->assertOk()
            ->assertSee('تعديل سجل تسميع')->assertDontSee('memorization.update')
            ->assertSee('ظاهر لولي الأمر')->assertSee('الدرجة')->assertSee('ربع صفحة')->assertSee('نصف صفحة')
            ->assertDontSee('shared_with_parent')->assertDontSee('{&quot;', false);
    }
}
