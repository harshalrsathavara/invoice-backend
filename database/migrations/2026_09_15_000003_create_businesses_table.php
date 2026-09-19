<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('uuid', 36)->unique();

            $table->string('name');
            $table->string('tagline')->default('');
            $table->text('address')->nullable();
            $table->string('mobile', 32)->default('');
            $table->string('jurisdiction_text')->default('');
            $table->string('gst_number', 20)->default('');
            $table->string('email')->default('');
            $table->text('bank_details')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('upi_id')->default('');

            // Numbering policy. Each document kind counts separately, so
            // raising a quotation never burns a bill number.
            $table->unsignedInteger('next_bill_no')->default(1);
            $table->unsignedInteger('next_quote_no')->default(1);
            $table->unsignedInteger('next_challan_no')->default(1);
            $table->string('bill_prefix', 16)->default('');
            $table->boolean('fy_reset')->default(false);
            $table->string('bill_fy', 8)->default('');
            $table->text('terms_text')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'deleted_at']);
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
