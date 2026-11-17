<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->getJson('/api/programs')->assertOk()->assertJsonCount(3);

        $imported = $this->postJson('/api/programs/import', $this->pack())->assertCreated()->json();
        $programId = $imported[0]['id'];
        $this->getJson('/api/programs')->assertJsonCount(4);
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
        $this->getJson('/api/programs')->assertJsonCount(3); // only the public templates
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
        $this->assertSame(3, Program::count());
    }
}
