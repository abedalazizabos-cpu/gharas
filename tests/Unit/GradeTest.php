<?php

namespace Tests\Unit;

use App\Models\MemorizationRecord;
use PHPUnit\Framework\TestCase;

class GradeTest extends TestCase
{
    public function test_score_maps_to_arabic_grade(): void
    {
        $this->assertSame('excellent', MemorizationRecord::gradeForScore(95));
        $this->assertSame('very_good', MemorizationRecord::gradeForScore(80));
        $this->assertSame('good', MemorizationRecord::gradeForScore(79));
        $this->assertSame('acceptable', MemorizationRecord::gradeForScore(60));
        $this->assertSame('weak', MemorizationRecord::gradeForScore(10));
    }
}
