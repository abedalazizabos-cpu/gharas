<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Roles & permissions controlled by the super admin; the first admin account becomes the super admin. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('role', 20)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('permission', 50);
            $table->boolean('allowed')->default(true);
            $table->timestamps();
            $table->index(['user_id', 'permission']);
        });

        if (! DB::table('users')->where('role', 'superadmin')->exists()) {
            $first = DB::table('users')->where('role', 'admin')->whereNull('deleted_at')->orderBy('id')->value('id');
            if ($first) {
                DB::table('users')->where('id', $first)->update(['role' => 'superadmin']);
            }
        }
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'superadmin')->update(['role' => 'admin']);
        Schema::dropIfExists('permission_rules');
    }
};
