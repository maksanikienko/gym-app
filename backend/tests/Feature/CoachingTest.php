<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Support\Equipment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->withHeader('Referer', 'http://localhost');
        $this->actingAs(User::factory()->create());
    }

    /** Program with one slot per day; returns the cycle's day ids. */
    private function program(array $slots, string $exercise = 'back-squat'): array
    {
        $days = [];
        foreach ($slots as $i => $slot) {
            $days[] = ['day_no' => $i + 1, 'title_en' => 'D'.($i + 1), 'exercises' => [['exercise' => $exercise] + $slot]];
        }
        $id = $this->postJson('/api/programs/import', [
            'format' => 'gym-app.program-pack.v1',
            'programs' => [['slug' => 'p', 'name_en' => 'P', 'days_per_week' => 7, 'weeks' => [['week_no' => 1, 'days' => $days]]]],
        ])->json('0.id');
        $this->postJson('/api/cycles', ['program_id' => $id]);

        return array_column($this->getJson('/api/cycles/current')->json('days'), 'id');
    }

    /** Log sets [[weight, reps, rpe], ...] on a day and finish it. */
    private function train(int $dayId, array $sets): array
    {
        $s = $this->postJson('/api/sessions', ['program_day_id' => $dayId])->json();
        $slot = $s['slots'][0];
        $responses = [];
        foreach ($sets as $i => [$w, $r, $rpe]) {
            $responses[] = $this->postJson("/api/sessions/{$s['id']}/sets", [
                'day_exercise_id' => $slot['id'], 'exercise_id' => $slot['exercise']['id'],
                'set_no' => $i + 1, 'weight_kg' => $w, 'reps' => $r, 'rpe' => $rpe,
            ])->json();
        }
        $this->postJson("/api/sessions/{$s['id']}/finish");

        return $responses;
    }

    private function advice(int $dayId, string $exercise = 'back-squat'): array
    {
        $s = $this->postJson('/api/sessions', ['program_day_id' => $dayId])->json();
        $id = Exercise::whereNull('user_id')->where('slug', $exercise)->value('id');

        return $s['slots'][0]['advice'][$id];
    }

    public function test_first_time_explains_how_to_pick_a_weight(): void
    {
        [$d1] = $this->program([['sets' => 3, 'reps' => 5, 'target_rpe' => 8]]);
        $a = $this->advice($d1);
        $this->assertSame('first', $a['basis']);
        $this->assertNull($a['weight_kg']);
        $this->assertSame(2, $a['params']['rir']);
    }

    public function test_easy_last_time_adds_weight(): void
    {
        $slot = ['sets' => 3, 'reps' => 5, 'target_rpe' => 8];
        [$d1, $d2] = $this->program([$slot, $slot]);
        $this->train($d1, [[100, 5, 6.5], [100, 5, 7], [100, 5, 7]]);
        $a = $this->advice($d2);
        $this->assertSame('increase_easy', $a['basis']);
        $this->assertEquals(102.5, $a['weight_kg']);
    }

    public function test_on_target_keeps_weight_and_too_hard_reduces(): void
    {
        $slot = ['sets' => 3, 'reps' => 5, 'target_rpe' => 8];
        [$d1, $d2, $d3] = $this->program([$slot, $slot, $slot]);
        $this->train($d1, [[100, 5, 7.5], [100, 5, 8], [100, 5, 8]]);
        $a = $this->advice($d2);
        $this->assertSame('hold_target', $a['basis']);
        $this->assertEquals(100, $a['weight_kg']);

        $this->train($d2, [[110, 3, 10], [110, 2, 10]]); // missed reps, maximal effort
        $a = $this->advice($d3);
        $this->assertSame('decrease', $a['basis']);
        $this->assertEquals(105, $a['weight_kg']); // −5% of 110 = 104.5 → nearest 105
    }

    public function test_rep_range_double_progression(): void
    {
        $slot = ['sets' => 3, 'reps' => 8, 'reps_max' => 12, 'target_rpe' => 8];
        [$d1, $d2] = $this->program([$slot, $slot], 'dumbbell-incline-press');
        $this->train($d1, [[24, 10, 8], [24, 10, 8], [24, 9, 8]]);
        $a = $this->advice($d2, 'dumbbell-incline-press');
        $this->assertSame('add_reps', $a['basis']);
        $this->assertEquals(24, $a['weight_kg']);
        $this->assertSame(10, $a['reps']);
    }

    public function test_top_of_range_increases_weight(): void
    {
        $slot = ['sets' => 3, 'reps' => 8, 'reps_max' => 12, 'target_rpe' => 8];
        [$d1, $d2] = $this->program([$slot, $slot], 'dumbbell-incline-press');
        $this->train($d1, [[24, 12, 8], [24, 12, 8], [24, 12, 8.5]]);
        $a = $this->advice($d2, 'dumbbell-incline-press');
        $this->assertSame('increase_range', $a['basis']);
        $this->assertEquals(26, $a['weight_kg']); // dumbbells step 2 kg
        $this->assertSame(8, $a['reps']);
    }

    public function test_percent_of_1rm_and_rep_scheme_conversion(): void
    {
        [$d1, $d2, $d3] = $this->program([
            ['sets' => 3, 'reps' => 5, 'target_rpe' => 8],
            ['sets' => 3, 'reps' => 3, 'load_pct' => 80],
            ['sets' => 3, 'reps' => 10, 'target_rpe' => 8],
        ]);
        $this->train($d1, [[100, 5, 8], [100, 5, 8], [100, 5, 8]]); // e1RM = 100 * (1 + 7/30) = 123.3
        $a = $this->advice($d2);
        $this->assertSame('pct', $a['basis']);
        $this->assertEquals(97.5, $a['weight_kg']); // 123.3 * 0.8 = 98.7 → nearest 2.5
        $this->postJson('/api/sessions/'.$this->postJson('/api/sessions', ['program_day_id' => $d2])->json('id').'/skip');

        $a = $this->advice($d3);
        $this->assertSame('convert', $a['basis']);
        $this->assertEquals(87.5, $a['weight_kg']); // 123.3 / (1 + 12/30) = 88.1 → down to 87.5
    }

    public function test_bodyweight_and_timed_slots(): void
    {
        [$d1] = $this->program([['sets' => 3, 'reps' => 8]], 'pull-up');
        $this->assertSame('bodyweight', $this->advice($d1, 'pull-up')['basis']);

        $this->postJson('/api/cycles/'.$this->getJson('/api/cycles/current')->json('id').'/finish');
        [$t1] = $this->program([['sets' => 3, 'seconds' => 30]], 'plank');
        $this->assertSame('first_timed', $this->advice($t1, 'plank')['basis']);
    }

    public function test_records_are_reported(): void
    {
        $slot = ['sets' => 3, 'reps' => 5, 'target_rpe' => 8];
        [$d1, $d2] = $this->program([$slot, $slot]);
        $first = $this->train($d1, [[100, 5, 8]]);
        $this->assertSame([], $first[0]['records']); // first time ever: no noise

        $second = $this->train($d2, [[105, 5, 8], [100, 7, 9]]);
        $this->assertEqualsCanonicalizing(['weight', 'e1rm'], array_column($second[0]['records'], 'type'));
        $this->assertSame(['reps'], array_column($second[1]['records'], 'type'));

        $list = $this->getJson('/api/records')->assertOk()->json();
        $this->assertCount(1, $list);
        $this->assertEquals(105, $list[0]['heaviest']['weight_kg']);
    }

    public function test_equipment_rounding(): void
    {
        $this->assertSame(20.0, Equipment::round(12, 'barbell'));
        $this->assertSame(102.5, Equipment::round(101.6, 'barbell'));
        $this->assertSame(26.0, Equipment::round(25.2, 'dumbbell'));
        $this->assertSame(7.0, Equipment::round(7.4, 'dumbbell'));
        $this->assertSame('barbell', Equipment::guess('barbell-hip-thrust'));
        $this->assertSame('bodyweight', Equipment::guess('pull-up'));
    }
}
