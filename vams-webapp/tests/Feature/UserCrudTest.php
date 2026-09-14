<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCoreSettings();
    }

    private function userWithRole(string $roleSlug): User
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    private function userPayload(Role $role, array $overrides = []): array
    {
        return [
            'name' => 'Maria Santos',
            'email' => 'maria.santos@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'role_id' => $role->id,
            'status' => 'active',
            ...$overrides,
        ];
    }

    public function test_administrator_can_view_user_management_pages(): void
    {
        $administrator = $this->userWithRole('administrator');
        $user = $this->userWithRole('security-officer');

        $this->actingAs($administrator)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee($user->name);

        $this->actingAs($administrator)
            ->get(route('users.show', $user))
            ->assertOk()
            ->assertSee($user->email);

        $this->actingAs($administrator)
            ->get(route('users.edit', $user))
            ->assertOk()
            ->assertSee('Edit User');
    }

    public function test_administrator_can_create_a_user_with_a_hashed_password_and_audit_record(): void
    {
        $administrator = $this->userWithRole('administrator');
        $role = Role::where('slug', 'security-officer')->firstOrFail();
        $payload = $this->userPayload($role);

        $response = $this->actingAs($administrator)->post(route('users.store'), $payload);

        $user = User::where('email', $payload['email'])->firstOrFail();

        $response->assertRedirect(route('users.show', $user));
        $this->assertTrue(Hash::check($payload['password'], $user->password));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'user.created',
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
        ]);
    }

    public function test_administrator_can_update_a_user_without_replacing_a_blank_password(): void
    {
        $administrator = $this->userWithRole('administrator');
        $role = Role::where('slug', 'security-officer')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'status' => 'active',
            'password' => Hash::make('OriginalPassword123!'),
        ]);
        $originalPasswordHash = $user->password;

        $response = $this->actingAs($administrator)->put(route('users.update', $user), [
            'name' => 'Maria Updated',
            'email' => $user->email,
            'password' => '',
            'password_confirmation' => '',
            'role_id' => $role->id,
            'status' => 'suspended',
        ]);

        $response->assertRedirect(route('users.show', $user));

        $user->refresh();
        $this->assertSame('Maria Updated', $user->name);
        $this->assertSame('suspended', $user->status);
        $this->assertSame($originalPasswordHash, $user->password);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'user.updated',
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
        ]);
    }

    public function test_administrator_can_delete_another_user_but_not_their_own_account(): void
    {
        $administrator = $this->userWithRole('administrator');
        $otherUser = $this->userWithRole('security-officer');

        $this->actingAs($administrator)
            ->delete(route('users.destroy', $otherUser))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', ['id' => $otherUser->id]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'user.deleted',
            'subject_type' => $otherUser->getMorphClass(),
            'subject_id' => $otherUser->id,
        ]);

        $this->actingAs($administrator)
            ->delete(route('users.destroy', $administrator))
            ->assertSessionHas('error', 'You cannot delete your own account.');

        $this->assertDatabaseHas('users', ['id' => $administrator->id]);
    }

    public function test_encoder_registrar_cannot_access_user_management(): void
    {
        $encoder = $this->userWithRole('encoder-registrar');
        $role = Role::where('slug', 'security-officer')->firstOrFail();

        $this->actingAs($encoder)
            ->get(route('users.index'))
            ->assertForbidden()
            ->assertDontSee(route('users.index'), false);

        $this->actingAs($encoder)
            ->post(route('users.store'), $this->userPayload($role))
            ->assertForbidden();
    }

    public function test_users_navigation_item_is_visible_only_to_users_with_view_permission(): void
    {
        $administrator = $this->userWithRole('administrator');
        $encoder = $this->userWithRole('encoder-registrar');

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('users.index'), false)
            ->assertSee('Users');

        $this->actingAs($encoder)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('users.index'), false);
    }
}
