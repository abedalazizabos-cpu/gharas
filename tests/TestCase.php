<?php

namespace Tests;

use App\Models\Circle;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SurahSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SurahSeeder::class, SettingSeeder::class]);
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function superAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    protected function teacher(): User
    {
        return User::factory()->teacher()->create();
    }

    protected function parentUser(): User
    {
        return User::factory()->parent()->create();
    }

    protected function circleFor(?User $teacher, array $attributes = []): Circle
    {
        return Circle::factory()->create(['teacher_id' => $teacher?->id] + $attributes);
    }

    protected function studentIn(?Circle $circle, array $attributes = []): Student
    {
        return Student::factory()->create(['current_circle_id' => $circle?->id] + $attributes);
    }
}
