<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->char('uuid', 36)->unique();

            $table->string('name');
            $table->decimal('default_rate', 12, 2)->default(0);
            $table->string('hsn_code', 16)->default('');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'name']);
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
