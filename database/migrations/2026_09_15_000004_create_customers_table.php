<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->char('uuid', 36)->unique();

            $table->string('name');
            $table->string('phone', 32)->default('');
            $table->text('address')->nullable();
            $table->string('gst_number', 20)->default('');

            $table->timestamps();
            $table->softDeletes();

            // The app matches customers case-insensitively by name within a
            // business; this index backs the same lookup server side.
            $table->index(['business_id', 'name']);
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
