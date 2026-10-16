<?php

use App\Models\Circle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Every circle studies Saturday to Thursday; Friday is off. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('circles')->update(['days' => json_encode(Circle::STUDY_DAYS)]);
    }

    public function down(): void {}
};
