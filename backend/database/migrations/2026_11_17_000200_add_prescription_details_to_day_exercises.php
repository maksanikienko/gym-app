<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('day_exercises', function (Blueprint $table) {
            $table->unsignedTinyInteger('warmup_sets')->nullable()->after('sets');
            $table->unsignedSmallInteger('reps_max')->nullable()->after('reps');   // range: reps..reps_max
            $table->string('reps_text', 40)->nullable()->after('reps_max');        // "15/15", "AMRAP", "20 each leg"
            $table->decimal('load_pct', 4, 1)->nullable()->after('target_rpe');    // % of 1RM
        });
    }

    public function down(): void
    {
        Schema::table('day_exercises', function (Blueprint $table) {
            $table->dropColumn(['warmup_sets', 'reps_max', 'reps_text', 'load_pct']);
        });
    }
};
