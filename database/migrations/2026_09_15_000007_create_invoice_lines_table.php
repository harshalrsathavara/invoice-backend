<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->char('uuid', 36)->unique();

            $table->string('particulars');
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('rate', 12, 2)->default(0);
            // Line amount stays derived, never stored, exactly as in the app.
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['invoice_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
