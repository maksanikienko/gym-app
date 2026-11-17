<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Exercise catalogue: user_id = null → shared, otherwise owned by a user.
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name_en');
            $table->string('name_ru')->nullable();
            $table->json('muscles')->nullable();
            $table->string('video_url')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'slug']);
            $table->index('slug');
        });

        Schema::create('exercise_substitutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('substitute_id')->constrained('exercises')->cascadeOnDelete();
            $table->string('note_en')->nullable();
            $table->string('note_ru')->nullable();
            $table->unique(['exercise_id', 'substitute_id']);
        });

        // Programs: user_id = null → system template.
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name_en');
            $table->string('name_ru')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ru')->nullable();
            $table->unsignedTinyInteger('days_per_week');
            $table->string('visibility', 16)->default('private'); // public | private
            $table->string('source', 16)->default('user');        // system | user | import
            $table->timestamps();
            $table->index(['visibility', 'user_id']);
        });

        Schema::create('program_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('week_no');
            $table->string('block', 32)->nullable();
            $table->unique(['program_id', 'week_no']);
        });

        Schema::create('program_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_week_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_no');
            $table->string('title_en');
            $table->string('title_ru')->nullable();
            $table->unique(['program_week_id', 'day_no']);
        });

        Schema::create('day_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedTinyInteger('sets');
            $table->unsignedSmallInteger('reps')->nullable();
            $table->unsignedSmallInteger('seconds')->nullable();
            $table->decimal('target_rpe', 3, 1)->nullable();
            $table->unsignedTinyInteger('rest_min')->nullable();
            $table->unsignedTinyInteger('rest_max')->nullable();
            $table->text('notes_en')->nullable();
            $table->text('notes_ru')->nullable();
            $table->index(['program_day_id', 'position']);
        });

        Schema::create('cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'finished_at']);
        });

        Schema::create('workout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('program_day_id')->nullable()->constrained()->nullOnDelete();
            // in_progress | finished | skipped (annulled)
            $table->string('status', 16)->default('in_progress');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'started_at']);
            // One workout per program day within a cycle → no duplicates in history.
            $table->unique(['cycle_id', 'program_day_id']);
        });

        Schema::create('set_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('day_exercise_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('set_no');
            $table->boolean('is_warmup')->default(false);
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->unsignedSmallInteger('reps')->nullable();
            $table->unsignedSmallInteger('seconds')->nullable();
            $table->decimal('rpe', 3, 1)->nullable();
            $table->timestamps();
            $table->index(['exercise_id', 'workout_session_id']);
        });
    }

    public function down(): void
    {
        foreach (['set_logs', 'workout_sessions', 'cycles', 'day_exercises', 'program_days',
            'program_weeks', 'programs', 'exercise_substitutions', 'exercises'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
