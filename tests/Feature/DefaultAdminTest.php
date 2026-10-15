<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_create_a_default_admin(): void
    {
        $admin = User::firstWhere('username', 'admin');

        $this->assertNotNull($admin);
        $this->assertSame('Admin', $admin->name);
        $this->assertSame(Role::Admin, $admin->role);
    }

    public function test_default_admin_can_log_in_with_admin_and_test(): void
    {
        $this->post(route('login'), ['username' => 'Admin', 'password' => 'Test'])
            ->assertRedirect(route('recipes.index'));

        $this->assertAuthenticatedAs(User::firstWhere('username', 'admin'));
    }
}
