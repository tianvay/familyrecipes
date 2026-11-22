<?php

namespace Tests\Feature;

use App\Enums\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_paper_is_the_default_theme(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('account.edit'))
            ->assertOk()
            ->assertSee('data-theme="papier"', false)
            ->assertSee('Catppuccin Mocha');
    }

    public function test_user_can_switch_to_mocha(): void
    {
        $user = User::factory()->child()->create();

        $this->actingAs($user)
            ->from(route('account.edit'))
            ->put(route('account.theme'), ['theme' => 'mocha'])
            ->assertRedirect(route('account.edit'))
            ->assertCookie(Theme::COOKIE, 'mocha');

        $this->assertSame(Theme::Mocha, $user->fresh()->theme);

        $this->actingAs($user->fresh())
            ->get(route('recipes.index'))
            ->assertSee('data-theme="mocha"', false)
            ->assertSee('<meta name="theme-color" content="#181825">', false);
    }

    public function test_theme_is_per_user(): void
    {
        User::factory()->create(['theme' => 'mocha']);
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('account.edit'))
            ->assertSee('data-theme="papier"', false);
    }

    public function test_unknown_theme_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('account.theme'), ['theme' => 'neon'])
            ->assertSessionHasErrors('theme');

        $this->assertNull($user->fresh()->theme);
    }

    public function test_login_page_uses_theme_from_cookie(): void
    {
        $this->withCookie(Theme::COOKIE, 'auto')
            ->get(route('login'))
            ->assertSee('data-theme="auto"', false)
            ->assertSee('media="(prefers-color-scheme: dark)"', false);
    }

    public function test_guests_cannot_change_theme(): void
    {
        $this->put(route('account.theme'), ['theme' => 'mocha'])->assertRedirect(route('login'));
    }
}
