<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            // barbell | dumbbell | machine | cable | bodyweight | other — drives load steps and the plate calculator
            $table->string('equipment', 16)->nullable()->after('muscles');
        });
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn('equipment');
        });
    }
};
