<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_be_created_from_the_command_line(): void
    {
        $this->artisan('users:create', ['--admin' => true])
            ->expectsQuestion('Name', 'Papa')
            ->expectsQuestion('Benutzername', 'Papa')
            ->expectsQuestion('Passwort', '1234')
            ->assertSuccessful();

        $user = User::firstWhere('username', 'papa');
        $this->assertSame('Papa', $user->name);
        $this->assertSame(Role::Admin, $user->role);
        $this->assertTrue(Hash::check('1234', $user->password));
    }

    public function test_role_is_asked_without_admin_option(): void
    {
        $this->artisan('users:create')
            ->expectsQuestion('Name', 'Lena')
            ->expectsQuestion('Benutzername', 'lena')
            ->expectsQuestion('Passwort', 'geheim')
            ->expectsQuestion('Rolle', 'child')
            ->assertSuccessful();

        $this->assertSame(Role::Child, User::firstWhere('username', 'lena')->role);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->artisan('users:create')
            ->expectsQuestion('Name', 'Papa')
            ->expectsQuestion('Benutzername', 'papa mama')
            ->expectsQuestion('Passwort', 'geheim')
            ->expectsQuestion('Rolle', 'adult')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['name' => 'Papa']);
    }
}
