<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per form that a teacher filled in without internet and the phone sent later.
 * The uuid is made on the phone; a second send of the same uuid is answered "already saved"
 * instead of saving the attendance or recitation twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_submissions');
    }
};
