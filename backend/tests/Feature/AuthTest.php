<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Referer', 'http://localhost');
    }

    private function register(array $overrides = [])
    {
        return $this->postJson('/api/register', array_merge([
            'name' => 'Anna', 'email' => 'anna@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ], $overrides));
    }

    public function test_registration_validates_fields(): void
    {
        $this->register(['name' => 'A'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->register(['name' => str_repeat('a', 101)])->assertJsonValidationErrors('name');
        $this->register(['name' => '<script>'])->assertJsonValidationErrors('name');
        $this->register(['email' => 'not-an-email'])->assertJsonValidationErrors('email');
        $this->register(['email' => str_repeat('a', 250).'@x.com'])->assertJsonValidationErrors('email');
        $this->register(['password' => 'short1', 'password_confirmation' => 'short1'])->assertJsonValidationErrors('password');
        $this->register(['password' => 'onlyletters', 'password_confirmation' => 'onlyletters'])->assertJsonValidationErrors('password');
        $this->register(['password' => '12345678', 'password_confirmation' => '12345678'])->assertJsonValidationErrors('password');
        $long = str_repeat('a1', 37);
        $this->register(['password' => $long, 'password_confirmation' => $long])->assertJsonValidationErrors('password');
        $this->register(['password_confirmation' => 'other123'])->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_email_is_normalized_and_unique_case_insensitively(): void
    {
        $this->register(['email' => '  Anna@Example.COM ', 'name' => '  Anna   Maria '])->assertCreated()
            ->assertJsonPath('email', 'anna@example.com')
            ->assertJsonPath('name', 'Anna Maria');
        $this->postJson('/api/logout');
        $this->register(['email' => 'ANNA@example.com'])->assertJsonValidationErrors('email');
    }

    public function test_messages_follow_language(): void
    {
        $this->withHeader('Accept-Language', 'ru')->register(['email' => 'bad'])
            ->assertJsonPath('errors.email.0', 'Введите корректный email.');
        $this->withHeader('Accept-Language', 'en')->register(['password_confirmation' => 'nope1234'])
            ->assertJsonPath('errors.password.0', 'The passwords do not match.');
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        User::factory()->create(['email' => 'anna@example.com', 'password' => Hash::make('secret123')]);

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/login', ['email' => 'anna@example.com', 'password' => 'wrong'])->assertStatus(422);
        }
        $this->postJson('/api/login', ['email' => 'anna@example.com', 'password' => 'secret123'])
            ->assertStatus(429)->assertJsonValidationErrors('email');
    }

    public function test_successful_login_with_mixed_case_email(): void
    {
        User::factory()->create(['email' => 'anna@example.com', 'password' => Hash::make('secret123')]);
        $this->postJson('/api/login', ['email' => ' Anna@Example.com', 'password' => 'secret123'])->assertOk();
    }

    public function test_change_password_logs_out_other_devices(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $this->actingAs($user);
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->putJson('/api/me/password', ['current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertJsonValidationErrors('current_password');
        $this->putJson('/api/me/password', ['current_password' => 'secret123', 'password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertJsonValidationErrors('password');

        $this->putJson('/api/me/password', ['current_password' => 'secret123', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertNoContent();
        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
    }

    public function test_profile_update_validates_name(): void
    {
        $this->actingAs(User::factory()->create());
        $this->patchJson('/api/me', ['name' => ' '])->assertJsonValidationErrors('name');
        $this->patchJson('/api/me', ['name' => 'Мария'])->assertOk()->assertJsonPath('name', 'Мария');
        $this->patchJson('/api/me', ['locale' => 'de'])->assertJsonValidationErrors('locale');
    }
}
