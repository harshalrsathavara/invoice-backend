<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Active/inactive, so a customer who has stopped trading can be put away
 * without being deleted.
 *
 * Deleting one was always the wrong shape: their name is printed on bills
 * that are still owed, still in the ledger and still in last year's GST
 * figures, so the row has to stay whatever the list looks like. Inactive
 * keeps the history and takes them out of the pickers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
