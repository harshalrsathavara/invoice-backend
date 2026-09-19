<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();

            // Kept in the same normalised form the handset sends, so a number
            // typed with spaces or a leading zero still finds its code.
            $table->string('phone', 20)->index();

            // Hashed, like a password: a stolen database dump must not hand
            // over a working sign-in for every number in it.
            $table->string('code_hash');

            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
