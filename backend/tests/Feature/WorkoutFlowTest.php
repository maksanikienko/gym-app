<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\TemplateProgramSeeder;
use Tests\TestCase;

class WorkoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        // Behave like the SPA: requests from a stateful origin get a session.
        $this->withHeader('Referer', 'http://localhost');
    }

    private function pack(): array
    {
        return [
            'format' => 'gym-app.program-pack.v1',
            'exercises' => [['slug' => 'back-squat', 'name_en' => 'Back Squat'], ['slug' => 'my-special-move', 'name_en' => 'My Special Move']],
            'programs' => [[
                'slug' => 'mine', 'name_en' => 'Mine', 'days_per_week' => 2,
                'weeks' => [['week_no' => 1, 'block' => 'base', 'days' => [
                    ['day_no' => 1, 'title_en' => 'Day A', 'exercises' => [
                        ['exercise' => 'back-squat', 'sets' => 3, 'reps' => 5, 'target_rpe' => 7, 'rest_min' => 3, 'rest_max' => 4],
                        ['exercise' => 'my-special-move', 'sets' => 2, 'reps' => 10],
                    ]],
                    ['day_no' => 2, 'title_en' => 'Day B', 'exercises' => [
                        ['exercise' => 'plank', 'sets' => 3, 'seconds' => 30],
                    ]],
                ]]],
            ]],
        ];
    }

    public function test_register_login_logout(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Alex', 'email' => 'A@x.test', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertCreated()->assertJsonPath('email', 'a@x.test');

        $this->getJson('/api/me')->assertOk();
        $this->postJson('/api/logout')->assertNoContent();

        $this->postJson('/api/login', ['email' => 'a@x.test', 'password' => 'wrong'])->assertStatus(422);
        $this->postJson('/api/login', ['email' => 'a@x.test', 'password' => 'secret123'])->assertOk();
    }

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/api/programs')->assertUnauthorized();
    }

    public function test_full_workout_flow(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // No active cycle yet → empty response, not "{}"
        $this->getJson('/api/cycles/current')->assertNoContent();

        // Public template is visible
        $this->getJson('/api/programs')->assertOk()->assertJsonCount(TemplateProgramSeeder::count());

        $imported = $this->postJson('/api/programs/import', $this->pack())->assertCreated()->json();
        $programId = $imported[0]['id'];
        $this->getJson('/api/programs')->assertJsonCount(TemplateProgramSeeder::count() + 1);
        $this->getJson("/api/programs/$programId")->assertOk()
            ->assertJsonPath('weeks.0.days.0.exercises.0.exercise.slug', 'back-squat');

        $this->postJson('/api/cycles', ['program_id' => $programId])->assertCreated();
        $cycle = $this->getJson('/api/cycles/current')->assertOk()->json();
        $this->assertSame(['done' => 0, 'total' => 2], $cycle['progress']);
        $dayA = $cycle['next_day_id'];

        $session = $this->postJson('/api/sessions', ['program_day_id' => $dayA])->assertCreated()->json();
        $slot = $session['slots'][0];
        $this->assertNotEmpty($slot['exercise']['substitutes']); // squat → leg press etc.

        // Same session resumes
        $this->assertSame($session['id'], $this->postJson('/api/sessions', ['program_day_id' => $dayA])->json('id'));
        $this->assertSame('in_progress', $this->getJson('/api/cycles/current')->json('open_session.id') ? 'in_progress' : null);

        $set = $this->postJson("/api/sessions/{$session['id']}/sets", [
            'day_exercise_id' => $slot['id'], 'exercise_id' => $slot['exercise']['id'],
            'set_no' => 1, 'weight_kg' => 100, 'reps' => 5, 'rpe' => 8,
        ])->assertCreated()->json();
        // Re-saving the same set updates it instead of duplicating
        $this->postJson("/api/sessions/{$session['id']}/sets", [
            'day_exercise_id' => $slot['id'], 'exercise_id' => $slot['exercise']['id'],
            'set_no' => 1, 'weight_kg' => 102.5, 'reps' => 5, 'rpe' => 8,
        ])->assertCreated()->assertJsonPath('id', $set['id']);

        $this->postJson("/api/sessions/{$session['id']}/finish")->assertOk()->assertJsonPath('volume_kg', 513);

        $cycle = $this->getJson('/api/cycles/current')->json();
        $this->assertSame(1, $cycle['progress']['done']);
        $this->assertNotSame($dayA, $cycle['next_day_id']);
        $this->assertSame('finished', $cycle['days'][0]['status']);

        // Re-opening a finished day returns the same workout — no duplicates in history
        $this->assertSame($session['id'], $this->postJson('/api/sessions', ['program_day_id' => $dayA])->json('id'));
        $this->assertCount(1, $this->getJson('/api/sessions')->json('data'));

        $history = $this->getJson("/api/exercises/{$slot['exercise']['id']}/history")->assertOk()->json();
        $this->assertSame(126.4, (float) $history['best_e1rm']); // 102.5 * (1 + (5 reps + 2 RIR)/30)
    }

    public function test_days_unlock_in_order_and_can_be_annulled(): void
    {
        $this->actingAs(User::factory()->create());
        $programId = $this->postJson('/api/programs/import', $this->pack())->json('0.id');
        $cycleId = $this->postJson('/api/cycles', ['program_id' => $programId])->json('id');
        [$dayA, $dayB] = array_column($this->getJson('/api/cycles/current')->json('days'), 'id');

        $this->assertSame(['next', 'locked'], array_column($this->getJson('/api/cycles/current')->json('days'), 'status'));
        // Day B is locked while day A is not closed
        $this->postJson('/api/sessions', ['program_day_id' => $dayB])->assertStatus(422);

        // In-progress workout doesn't unlock the next day either
        $sessionA = $this->postJson('/api/sessions', ['program_day_id' => $dayA])->json('id');
        $this->postJson('/api/sessions', ['program_day_id' => $dayB])->assertStatus(422);

        // Annul day A → day B unlocks
        $this->postJson("/api/sessions/$sessionA/skip")->assertOk()->assertJsonPath('status', 'skipped');
        $cycle = $this->getJson('/api/cycles/current')->json();
        $this->assertSame(['skipped', 'next'], array_column($cycle['days'], 'status'));
        $this->assertSame(1, $cycle['progress']['done']);
        $this->postJson('/api/sessions', ['program_day_id' => $dayB])->assertCreated();

        // Deleting day A reopens it and locks day B again for new starts
        $this->deleteJson("/api/sessions/$sessionA")->assertNoContent();
        $this->assertSame($dayA, $this->getJson('/api/cycles/current')->json('next_day_id'));

        // Skip the next day straight from the cycle
        $this->postJson("/api/cycles/$cycleId/skip-next")->assertOk();
        $this->assertSame('skipped', $this->getJson('/api/cycles/current')->json('days.0.status'));
    }

    public function test_set_can_be_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $programId = $this->postJson('/api/programs/import', $this->pack())->json('0.id');
        $this->postJson('/api/cycles', ['program_id' => $programId]);
        $day = $this->getJson('/api/cycles/current')->json('next_day_id');
        $session = $this->postJson('/api/sessions', ['program_day_id' => $day])->json();
        $slot = $session['slots'][0];
        $setId = $this->postJson("/api/sessions/{$session['id']}/sets", [
            'day_exercise_id' => $slot['id'], 'exercise_id' => $slot['exercise']['id'], 'set_no' => 4, 'weight_kg' => 50, 'reps' => 8,
        ])->json('id');

        $this->deleteJson("/api/sets/$setId")->assertNoContent();
        $this->assertCount(0, $this->getJson("/api/sessions/{$session['id']}")->json('sets'));
    }

    public function test_exercise_can_be_skipped_and_restored(): void
    {
        $this->actingAs(User::factory()->create());
        $programId = $this->postJson('/api/programs/import', $this->pack())->json('0.id');
        $this->postJson('/api/cycles', ['program_id' => $programId]);
        $day = $this->getJson('/api/cycles/current')->json('next_day_id');
        $session = $this->postJson('/api/sessions', ['program_day_id' => $day])->json();
        [$squat, $special] = $session['slots'];
        $this->assertSame([], $session['skipped_slots']);

        // A set logged before skipping stays in the log
        $this->postJson("/api/sessions/{$session['id']}/sets", [
            'day_exercise_id' => $special['id'], 'exercise_id' => $special['exercise']['id'], 'set_no' => 1, 'reps' => 10,
        ])->assertCreated();

        $this->patchJson("/api/sessions/{$session['id']}", ['skipped_slots' => [$special['id'], $special['id']]])
            ->assertOk()->assertJsonPath('skipped_slots', [$special['id']]);
        $shown = $this->getJson("/api/sessions/{$session['id']}")->json();
        $this->assertSame([$special['id']], $shown['skipped_slots']);
        $this->assertCount(1, $shown['sets']);

        // Updating the note alone doesn't touch skipped exercises
        $this->patchJson("/api/sessions/{$session['id']}", ['note' => 'tired'])->assertJsonPath('skipped_slots', [$special['id']]);
        $this->getJson('/api/sessions')->assertJsonPath('data.0.note', 'tired');

        // Slot from another day is rejected
        $otherDay = Program::find($programId)->days()->where('day_no', 2)->first()->exercises()->value('id');
        $this->patchJson("/api/sessions/{$session['id']}", ['skipped_slots' => [$otherDay]])->assertStatus(422);

        $this->patchJson("/api/sessions/{$session['id']}", ['skipped_slots' => []])->assertJsonPath('skipped_slots', []);
    }

    public function test_abandoned_workout_closes_automatically(): void
    {
        $this->actingAs(User::factory()->create());
        $programId = $this->postJson('/api/programs/import', $this->pack())->json('0.id');
        $this->postJson('/api/cycles', ['program_id' => $programId]);
        [$dayA, $dayB] = array_column($this->getJson('/api/cycles/current')->json('days'), 'id');

        $a = $this->postJson('/api/sessions', ['program_day_id' => $dayA])->json();
        $slot = $a['slots'][0];
        $this->travel(2)->hours();
        $this->postJson("/api/sessions/{$a['id']}/sets", [
            'day_exercise_id' => $slot['id'], 'exercise_id' => $slot['exercise']['id'], 'set_no' => 1, 'weight_kg' => 100, 'reps' => 5,
        ])->assertCreated();
        $lastSetAt = now()->toIso8601String();

        // 2h after the last set (4h after start) it's still open: the clock counts from the last activity
        $this->travel(2)->hours();
        $this->getJson("/api/sessions/{$a['id']}")->assertJsonPath('status', 'in_progress');

        // 3h+ of inactivity → finished at the last set
        $this->travel(90)->minutes();
        $this->getJson("/api/sessions/{$a['id']}")
            ->assertJsonPath('status', 'finished')->assertJsonPath('finished_at', $lastSetAt);
        $this->assertNull($this->getJson('/api/cycles/current')->json('open_session'));

        // Nothing logged at all → annulled
        $b = $this->postJson('/api/sessions', ['program_day_id' => $dayB])->assertCreated()->json('id');
        $this->travel(4)->hours();
        $this->getJson('/api/sessions')->assertJsonPath('data.0.id', $b)->assertJsonPath('data.0.status', 'skipped');
    }

    public function test_cycle_summary(): void
    {
        $this->actingAs(User::factory()->create());
        $programId = $this->postJson('/api/programs/import', $this->pack())->json('0.id');
        $cycleId = $this->postJson('/api/cycles', ['program_id' => $programId])->json('id');
        [$dayA, $dayB] = array_column($this->getJson('/api/cycles/current')->json('days'), 'id');

        // Day A: squat 100×5 @8; then (pretend) a later workout isn't possible in a 2-day pack, so log two sessions via day B skip
        $a = $this->postJson('/api/sessions', ['program_day_id' => $dayA])->json();
        [$squat, $special] = $a['slots'];
        $this->postJson("/api/sessions/{$a['id']}/sets", [
            'day_exercise_id' => $squat['id'], 'exercise_id' => $squat['exercise']['id'], 'set_no' => 1, 'weight_kg' => 100, 'reps' => 5, 'rpe' => 8,
        ])->assertCreated();
        $this->patchJson("/api/sessions/{$a['id']}", ['skipped_slots' => [$special['id']]])->assertOk();
        $this->travel(70)->minutes();
        $this->postJson("/api/sessions/{$a['id']}/finish")->assertOk();
        $this->postJson("/api/cycles/$cycleId/skip-next")->assertOk(); // day B annulled

        $this->assertNull($this->getJson('/api/cycles/current')->json('next_day_id'));
        $summary = $this->getJson("/api/cycles/$cycleId/summary")->assertOk()->json();
        $this->assertSame(['finished' => 1, 'skipped' => 1, 'total' => 2], $summary['workouts']);
        $this->assertSame(70, $summary['training_minutes']);
        $this->assertSame(1, $summary['sets_count']);
        $this->assertSame(500, $summary['volume_kg']);
        $this->assertSame([], $summary['progress']); // one workout per exercise → nothing to compare yet
        $this->assertSame('my-special-move', $summary['skipped_exercises'][0]['exercise']['slug']);

        $this->actingAs(User::factory()->create());
        $this->getJson("/api/cycles/$cycleId/summary")->assertNotFound();
    }

    public function test_cycle_summary_shows_strength_progress(): void
    {
        $this->actingAs(User::factory()->create());
        $day = fn ($t) => ['day_no' => $t, 'title_en' => "D$t", 'exercises' => [['exercise' => 'back-squat', 'sets' => 2, 'reps' => 5]]];
        $programId = $this->postJson('/api/programs/import', ['format' => 'gym-app.program-pack.v1', 'programs' => [[
            'slug' => 'sq', 'name_en' => 'Squat', 'days_per_week' => 2, 'weeks' => [['week_no' => 1, 'days' => [$day(1), $day(2)]]],
        ]]])->json('0.id');
        $cycleId = $this->postJson('/api/cycles', ['program_id' => $programId])->json('id');

        foreach ([[100, 5], [110, 5]] as [$kg, $reps]) {
            $dayId = $this->getJson('/api/cycles/current')->json('next_day_id');
            $s = $this->postJson('/api/sessions', ['program_day_id' => $dayId])->json();
            $this->postJson("/api/sessions/{$s['id']}/sets", [
                'day_exercise_id' => $s['slots'][0]['id'], 'exercise_id' => $s['slots'][0]['exercise']['id'],
                'set_no' => 1, 'weight_kg' => $kg, 'reps' => $reps, 'rpe' => 10,
            ]);
            $this->postJson("/api/sessions/{$s['id']}/finish");
        }

        $p = $this->getJson("/api/cycles/$cycleId/summary")->json('progress.0');
        $this->assertSame('back-squat', $p['exercise']['slug']);
        $this->assertSame(2, $p['sessions']);
        $this->assertEquals(116.7, $p['start_e1rm']); // 100 × (1 + 5/30)
        $this->assertEquals(128.3, $p['best_e1rm']);
        $this->assertEquals(9.9, $p['change_pct']);
    }

    public function test_users_cannot_see_each_others_data(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice);
        $programId = $this->postJson('/api/programs/import', $this->pack())->json('0.id');
        $this->postJson('/api/cycles', ['program_id' => $programId]);
        $dayId = $this->getJson('/api/cycles/current')->json('next_day_id');
        $sessionId = $this->postJson('/api/sessions', ['program_day_id' => $dayId])->json('id');

        $this->actingAs($bob);
        $this->getJson('/api/programs')->assertJsonCount(TemplateProgramSeeder::count()); // only the public templates
        $this->getJson("/api/programs/$programId")->assertNotFound();
        $this->deleteJson("/api/programs/$programId")->assertNotFound();
        $this->postJson('/api/cycles', ['program_id' => $programId])->assertNotFound();
        $this->getJson("/api/sessions/$sessionId")->assertNotFound();
        $this->postJson("/api/sessions/$sessionId/sets", ['exercise_id' => 1, 'set_no' => 1])->assertNotFound();
        $custom = Program::find($programId)->days()->first()->exercises()->where('position', 2)->first()->exercise_id;
        $this->getJson("/api/exercises/$custom/history")->assertNotFound();
    }

    public function test_account_deletion_removes_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->postJson('/api/programs/import', $this->pack());
        $this->deleteJson('/api/me', ['password' => 'password'])->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertSame(TemplateProgramSeeder::count(), Program::count());
    }
}
