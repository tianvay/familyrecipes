<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->admin()->create(['name' => 'Papa']);
    }

    public function test_members_cannot_manage_users(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($member);
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('users.create'))->assertForbidden();
        $this->post(route('users.store'), $this->payload())->assertForbidden();
        $this->get(route('users.edit', $other))->assertForbidden();
        $this->put(route('users.update', $other), $this->payload())->assertForbidden();
        $this->delete(route('users.destroy', $other))->assertForbidden();

        $this->assertModelExists($other);
    }

    public function test_admin_sees_user_list(): void
    {
        $member = User::factory()->create(['name' => 'Lena']);

        $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee(['Papa', 'Lena']);
    }

    public function test_admin_can_create_member(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->payload(['username' => 'Lena', 'role' => 'child']))
            ->assertRedirect(route('users.index'));

        $user = User::firstWhere('username', 'lena');
        $this->assertSame('Lena', $user->name);
        $this->assertSame(Role::Child, $user->role);
        $this->assertTrue(Hash::check('geheim123', $user->password));
    }

    public function test_passwords_may_be_short_and_simple(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->payload([
                'password' => '1',
                'password_confirmation' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('1', User::firstWhere('username', 'lena')->password));
    }

    public function test_usernames_must_be_unique(): void
    {
        User::factory()->create(['username' => 'lena']);

        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->payload(['username' => 'LENA']))
            ->assertSessionHasErrors('username');
    }

    public function test_admin_can_update_member_and_keep_password(): void
    {
        $member = User::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('users.update', $member), $this->payload([
                'name' => 'Max',
                'username' => 'max',
                'password' => '',
                'password_confirmation' => '',
                'role' => 'admin',
            ]))
            ->assertRedirect(route('users.index'));

        $member->refresh();
        $this->assertSame('Max', $member->name);
        $this->assertSame(Role::Admin, $member->role);
        $this->assertTrue(Hash::check('password', $member->password));
    }

    public function test_admin_can_reset_member_password(): void
    {
        $member = User::factory()->create();

        $this->actingAs($this->admin)->put(route('users.update', $member), $this->payload([
            'username' => $member->username,
        ]));

        $this->assertTrue(Hash::check('geheim123', $member->fresh()->password));
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $this->actingAs($this->admin)
            ->put(route('users.update', $this->admin), $this->payload([
                'username' => $this->admin->username,
                'role' => 'adult',
            ]))
            ->assertSessionHasErrors('role');

        $this->assertSame(Role::Admin, $this->admin->fresh()->role);
    }

    public function test_role_is_required_and_must_be_valid(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->payload(['role' => 'chef']))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['username' => 'lena']);
    }

    public function test_admin_can_delete_member(): void
    {
        $member = User::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $member))
            ->assertRedirect(route('users.index'));

        $this->assertModelMissing($member);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $this->admin))
            ->assertForbidden();

        $this->assertModelExists($this->admin);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Lena',
            'username' => 'lena',
            'password' => 'geheim123',
            'password_confirmation' => 'geheim123',
            'role' => 'adult',
            ...$overrides,
        ];
    }
}
