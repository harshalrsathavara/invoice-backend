<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->char('uuid', 36)->unique();

            $table->date('date');
            $table->decimal('amount', 14, 2);
            $table->enum('mode', ['cash', 'upi', 'cheque', 'bank', 'other'])->default('cash');
            $table->string('note')->default('');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['invoice_id', 'date']);
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
