<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\TemplateProgramSeeder;
use Tests\TestCase;

class ProgramBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->withHeader('Referer', 'http://localhost');
    }

    private function ex(string $slug): int
    {
        return Exercise::whereNull('user_id')->where('slug', $slug)->value('id');
    }

    private function tree(): array
    {
        $day = fn ($title, $slug) => ['title_ru' => $title, 'exercises' => [
            ['exercise_id' => $this->ex($slug), 'sets' => 3, 'reps' => 8, 'target_rpe' => 8, 'rest_min' => 2, 'rest_max' => 3],
        ]];

        return [
            'name_ru' => 'Моя программа',
            'weeks' => [
                ['days' => [$day('Грудь', 'barbell-bench-press'), $day('Ноги', 'back-squat')]],
                ['days' => [$day('Грудь', 'barbell-bench-press'), $day('Ноги', 'back-squat')]],
            ],
        ];
    }

    public function test_ready_made_templates_are_seeded(): void
    {
        $this->actingAs(User::factory()->create());
        $this->assertCount(TemplateProgramSeeder::count(), $this->getJson('/api/programs')->json());
    }

    public function test_create_edit_and_keep_history_attached(): void
    {
        $this->actingAs(User::factory()->create());

        $p = $this->postJson('/api/programs', $this->tree())->assertCreated()->json();
        $this->assertSame('Моя программа', $p['name_en']); // falls back to RU name
        $this->assertSame(2, $p['days_per_week']);
        $this->assertCount(2, $p['weeks']);
        $this->assertTrue($p['is_mine']);

        // Train day 1 of week 1
        $this->postJson('/api/cycles', ['program_id' => $p['id']]);
        $day1 = $p['weeks'][0]['days'][0];
        $session = $this->postJson('/api/sessions', ['program_day_id' => $day1['id']])->json('id');

        // Edit: rename day, change sets, add an exercise, drop week 2 (unused)
        $tree = $this->getJson("/api/programs/{$p['id']}")->json();
        $this->assertTrue($tree['weeks'][0]['days'][0]['used']);
        $tree['weeks'][0]['days'][0]['title_ru'] = 'Грудь и спина';
        $tree['weeks'][0]['days'][0]['exercises'][0]['sets'] = 4;
        $tree['weeks'][0]['days'][0]['exercises'][0]['exercise_id'] = $tree['weeks'][0]['days'][0]['exercises'][0]['exercise']['id'];
        $tree['weeks'][0]['days'][0]['exercises'][] = ['exercise_id' => $this->ex('lat-pulldown'), 'sets' => 3, 'reps' => 10];
        foreach ($tree['weeks'][0]['days'] as &$d) {
            foreach ($d['exercises'] as &$x) {
                $x['exercise_id'] ??= $x['exercise']['id'];
            }
        }
        unset($d, $x);
        $tree['weeks'] = [$tree['weeks'][0]];

        $updated = $this->putJson("/api/programs/{$p['id']}", $tree)->assertOk()->json();
        $this->assertCount(1, $updated['weeks']);
        $this->assertSame($day1['id'], $updated['weeks'][0]['days'][0]['id']); // same day id → history intact
        $this->assertSame(4, $updated['weeks'][0]['days'][0]['exercises'][0]['sets']);
        $this->assertCount(2, $updated['weeks'][0]['days'][0]['exercises']);
        $this->getJson("/api/sessions/$session")->assertOk()->assertJsonPath('day.title_ru', 'Грудь и спина');

        // Removing a day that has a workout is refused
        $tree = $updated;
        array_shift($tree['weeks'][0]['days']);
        foreach ($tree['weeks'][0]['days'] as &$d) {
            foreach ($d['exercises'] as &$x) {
                $x['exercise_id'] = $x['exercise']['id'];
            }
        }
        unset($d, $x);
        $this->putJson("/api/programs/{$p['id']}", $tree)->assertStatus(422);
    }

    public function test_copy_template_and_custom_exercise(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $template = Program::whereNull('user_id')->where('slug', 'push-pull-legs')->first();

        $copy = $this->postJson("/api/programs/{$template->id}/copy")->assertCreated()->json();
        $this->assertTrue($copy['is_mine']);
        $this->assertStringContainsString('копия', $copy['name_ru']);
        $this->assertSame(8, $copy['weeks_count']);

        $mine = $this->postJson('/api/exercises', ['name_ru' => 'Моё упражнение', 'muscles' => ['chest']])
            ->assertCreated()->assertJsonPath('custom', true)->json();
        $tree = $this->tree();
        $tree['weeks'][0]['days'][0]['exercises'][0]['exercise_id'] = $mine['id'];
        $this->postJson('/api/programs', $tree)->assertCreated();
    }

    public function test_imported_programs_are_ready_made_not_mine(): void
    {
        $this->actingAs(User::factory()->create());
        $imported = $this->postJson('/api/programs/import', [
            'format' => 'gym-app.program-pack.v1',
            'programs' => [['slug' => 'lib', 'name_en' => 'Library', 'days_per_week' => 1, 'weeks' => [
                ['week_no' => 1, 'days' => [['day_no' => 1, 'title_en' => 'A', 'exercises' => [['exercise' => 'back-squat', 'sets' => 3, 'reps' => 5]]]]],
            ]]],
        ])->json('0');
        $this->assertSame('library', $imported['kind']);
        $created = $this->postJson('/api/programs', $this->tree())->json();
        $this->assertSame('custom', $created['kind']);

        $kinds = collect($this->getJson('/api/programs')->json())->countBy('kind')->all();
        $this->assertSame(['library' => 1, 'custom' => 1, 'template' => TemplateProgramSeeder::count()], $kinds);

        // Imported programs are read-only; copying puts an editable version into "Mine"
        $this->putJson("/api/programs/{$imported['id']}", $this->tree())->assertNotFound();
        $copy = $this->postJson("/api/programs/{$imported['id']}/copy")->assertCreated()->json();
        $this->assertSame('custom', $copy['kind']);
    }

    public function test_cannot_edit_or_use_others_content(): void
    {
        $alice = User::factory()->create();
        $this->actingAs($alice);
        $p = $this->postJson('/api/programs', $this->tree())->json();
        $aliceEx = $this->postJson('/api/exercises', ['name_en' => 'Secret move'])->json('id');

        $this->actingAs(User::factory()->create());
        $this->putJson("/api/programs/{$p['id']}", $this->tree())->assertNotFound();
        $this->postJson("/api/programs/{$p['id']}/copy")->assertNotFound();
        $tree = $this->tree();
        $tree['weeks'][0]['days'][0]['exercises'][0]['exercise_id'] = $aliceEx;
        $this->postJson('/api/programs', $tree)->assertStatus(422);

        // Templates can't be edited by users
        $template = Program::whereNull('user_id')->first();
        $this->putJson("/api/programs/{$template->id}", $this->tree())->assertNotFound();
    }
}
