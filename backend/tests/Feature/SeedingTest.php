<?php

namespace Tests\Feature;

use App\Models\DayExercise;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TemplateProgramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->withHeader('Referer', 'http://localhost');
    }

    public function test_reseeding_updates_templates_in_place_without_duplicates(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $template = Program::whereNull('user_id')->where('slug', 'foundation-upper-lower')->first();
        $this->postJson('/api/cycles', ['program_id' => $template->id])->assertCreated();
        $dayId = $this->getJson('/api/cycles/current')->json('next_day_id');
        $session = $this->postJson('/api/sessions', ['program_day_id' => $dayId])->json();
        $slot = $session['slots'][0];
        $this->postJson("/api/sessions/{$session['id']}/sets", [
            'day_exercise_id' => $slot['id'], 'exercise_id' => $slot['exercise']['id'], 'set_no' => 1, 'weight_kg' => 60, 'reps' => 6,
        ])->assertCreated();

        // Someone edited a template in the database; re-seeding restores it.
        DayExercise::whereKey($slot['id'])->update(['sets' => 9]);
        $countsBefore = [Program::count(), DayExercise::count()];

        $this->seed(TemplateProgramSeeder::class);
        $this->seed(TemplateProgramSeeder::class);

        $this->assertSame($countsBefore, [Program::count(), DayExercise::count()]);
        $this->assertSame(3, Program::whereNull('user_id')->count());
        $this->assertSame(3, DayExercise::find($slot['id'])->sets);                // same row, restored
        $this->getJson("/api/sessions/{$session['id']}")->assertOk()->assertJsonCount(1, 'sets');
        $this->assertSame($template->id, $this->getJson('/api/cycles/current')->json('program.id'));
    }

    public function test_import_command_is_idempotent(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        $file = tempnam(sys_get_temp_dir(), 'pack').'.json';
        file_put_contents($file, json_encode([
            'format' => 'gym-app.program-pack.v1',
            'programs' => [['slug' => 'my-pack', 'name_en' => 'Pack', 'days_per_week' => 1, 'weeks' => [
                ['week_no' => 1, 'days' => [['day_no' => 1, 'title_en' => 'A', 'exercises' => [['exercise' => 'deadlift', 'sets' => 3, 'reps' => 5]]]]],
            ]]],
        ]));

        $this->artisan('programs:import', ['email' => 'ME@example.com', '--path' => $file])->assertSuccessful();
        $this->artisan('programs:import', ['email' => 'me@example.com', '--path' => $file])->assertSuccessful();
        $this->artisan('programs:import', ['email' => 'nobody@example.com', '--path' => $file])->assertFailed();

        $programs = Program::where('user_id', $user->id)->get();
        $this->assertCount(1, $programs);
        $this->assertSame('import', $programs[0]->source);
        $this->assertSame('private', $programs[0]->visibility);
        unlink($file);
    }
}
