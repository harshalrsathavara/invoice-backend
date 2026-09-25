<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GST per item rather than per bill.
 *
 * The rate was typed once for the whole document, which is right only when
 * everything on it shares a slab. A bill carrying 5% goods beside 18% ones
 * could not be charged correctly at all — and which rate applies follows from
 * the HSN/SAC code, which is already held against the item.
 *
 * `is_inter_state` decides how the same rate is presented: inside the state it
 * splits into CGST and SGST at half each, outside it is a single IGST row.
 * `businesses.state` is the other half of that comparison.
 *
 * Nothing is backfilled. Bills already raised keep the tax rows they were
 * saved with and still print exactly what went out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('gst_rate', 5, 2)->default(0)->after('hsn_code');
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->decimal('gst_rate', 5, 2)->default(0)->after('rate');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_inter_state')->default(false)->after('round_off');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->string('state', 120)->default('')->after('jurisdiction_text');
        });
    }

    public function down(): void
    {
        Schema::table('items', fn (Blueprint $t) => $t->dropColumn('gst_rate'));
        Schema::table('invoice_lines', fn (Blueprint $t) => $t->dropColumn('gst_rate'));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('is_inter_state'));
        Schema::table('businesses', fn (Blueprint $t) => $t->dropColumn('state'));
    }
};
