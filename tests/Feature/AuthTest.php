<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('recipes.index'))->assertRedirect(route('login'));
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_login_page_renders(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Anmelden');
    }

    public function test_member_can_log_in_with_username_case_insensitively(): void
    {
        $user = User::factory()->create(['username' => 'anna']);

        $this->post(route('login'), ['username' => ' Anna ', 'password' => 'password'])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['username' => 'anna']);

        $this->post(route('login'), ['username' => 'anna', 'password' => 'falsch'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $this->freezeTime();
        User::factory()->create(['username' => 'anna']);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login'), ['username' => 'anna', 'password' => 'falsch']);
        }

        $this->post(route('login'), ['username' => 'anna', 'password' => 'password'])
            ->assertSessionHasErrors(['username' => __('auth.throttle', ['seconds' => 60])]);

        $this->assertGuest();
    }

    public function test_logged_in_users_are_redirected_away_from_login(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('home'));
    }

    public function test_member_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_member_can_change_own_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('account.password'), [
                'current_password' => 'password',
                'password' => 'neues-passwort',
                'password_confirmation' => 'neues-passwort',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('neues-passwort', $user->fresh()->password));
    }

    public function test_changing_own_password_keeps_the_member_logged_in(): void
    {
        $user = User::factory()->create(['username' => 'anna']);
        $this->post(route('login'), ['username' => 'anna', 'password' => 'password']);

        $this->put(route('account.password'), [
            'current_password' => 'password',
            'password' => 'neues-passwort',
            'password_confirmation' => 'neues-passwort',
        ]);

        $this->get(route('recipes.index'))->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_reset_by_admin_ends_existing_sessions(): void
    {
        $child = User::factory()->child()->create(['username' => 'kind']);
        $this->post(route('login'), ['username' => 'kind', 'password' => 'password']);
        $this->get(route('recipes.index'))->assertOk();

        // Das Familienoberhaupt setzt das Passwort an einem anderen Gerät zurück.
        $child->update(['password' => 'ganz-neu-123']);
        // Wie bei einem echten neuen Request: Benutzer frisch aus der DB laden.
        $this->app['auth']->forgetGuards();

        $this->get(route('recipes.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('account.password'), [
                'current_password' => 'falsch',
                'password' => 'neues-passwort',
                'password_confirmation' => 'neues-passwort',
            ])
            ->assertSessionHasErrorsIn('password', 'current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
