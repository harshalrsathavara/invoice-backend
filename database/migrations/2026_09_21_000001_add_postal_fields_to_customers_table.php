<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The customer's postal address, split into its parts, plus an email.
 *
 * `address` keeps its meaning as the street/area line rather than being
 * parsed apart: it was entered as one line of free text, and splitting
 * "Ranip, Ahmedabad" into a city and a state by guesswork would put wrong
 * data on a GST invoice. Existing rows keep what they have and pick the new
 * fields up as they are edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('city', 120)->default('')->after('address');
            $table->string('state', 120)->default('')->after('city');
            $table->string('post_code', 16)->default('')->after('state');
            $table->string('email')->default('')->after('post_code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['city', 'state', 'post_code', 'email']);
        });
    }
};
