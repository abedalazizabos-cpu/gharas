<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Every SMS the site sent to parents (through the center's Android phone), for the admin's record. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            // manual | absence | day_off | certificate | test
            $table->string('purpose', 20)->index();
            $table->text('text');
            $table->json('phones');
            $table->unsignedInteger('phones_count');
            // sent | failed
            $table->string('status', 10)->index();
            $table->string('error')->nullable();
            $table->string('gateway_id')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
