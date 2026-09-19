<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->char('uuid', 36)->unique();

            // Bills, quotations and delivery challans share this table because
            // on paper they are the same document. Only bills are money owed.
            $table->enum('doc_type', ['bill', 'quotation', 'challan'])->default('bill');

            $table->unsignedInteger('bill_no');
            // The reference exactly as issued, e.g. 'RS/26-27/007'. Frozen at
            // save time: changing numbering settings must never renumber a
            // document that has already gone out.
            $table->string('bill_ref', 64)->default('');

            // Stored as text like the app does, so a bill keeps the name it was
            // raised under. The uuid is an optional link to the customer row.
            $table->string('customer_name');
            $table->char('customer_uuid', 36)->nullable();

            $table->date('date');
            $table->string('amount_in_words')->default('');

            $table->enum('discount_type', ['none', 'percent', 'amount'])->default('none');
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('round_off', 8, 2)->default(0);
            $table->text('notes')->nullable();

            // Cached money columns, recomputed from the lines, taxes and
            // payments on every write. Every list, ledger and report reads
            // these rather than re-deriving the arithmetic in SQL.
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);

            // Cancel, never delete: the number stays spent so the series has
            // no hole, and the reason is recorded against it.
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->default('');

            $table->char('converted_from_uuid', 36)->nullable();
            $table->string('photo_path')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Deliberately NOT unique. With fy_reset on, numbering restarts
            // at 1 each 1 April, so the same number legitimately recurs in
            // every financial year — a unique index here makes the second
            // year's first bill impossible to store.
            //
            // What prevents a duplicate within a year is NumberingService,
            // which reads and increments the counter in one locked
            // transaction. The genuinely unique value is `bill_ref`
            // (RS/26-27/001), which carries the year for exactly this reason;
            // it is left unconstrained because documents predating series
            // numbering carry an empty reference and MySQL has no partial
            // unique index to exempt them.
            $table->index(['business_id', 'doc_type', 'bill_no']);
            $table->index(['business_id', 'date']);
            $table->index(['business_id', 'customer_name']);
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
