<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_log_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Last-write-wins is the rule, but a discarded change is never
            // thrown away silently: the losing payload is kept here so the
            // admin can see what was overwritten and restore it by hand.
            $table->string('model_type', 64);
            $table->char('uuid', 36);
            $table->json('incoming')->nullable();
            $table->json('existing')->nullable();
            $table->enum('resolution', ['server_kept', 'client_won'])->default('server_kept');
            $table->boolean('reviewed')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'reviewed']);
            $table->index(['model_type', 'uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
    }
};
