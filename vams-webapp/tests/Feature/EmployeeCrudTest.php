<?php

namespace Tests\Feature;

use App\Models\CodeSetting;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function seedRolesAndPermissions(): void
    {
        $this->seedCoreSettings();
    }

    protected function actingAsRole(string $slug): User
    {
        $role = Role::where('slug', $slug)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $this->actingAs($user);

        return $user;
    }

    public function test_encoder_registrar_can_view_employee_index(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        Employee::factory()->count(3)->create();

        $response = $this->get(route('employees.index'));

        $response->assertOk();
        $response->assertViewIs('employees.index');
    }

    public function test_security_officer_cannot_create_employee(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('security-officer');

        $response = $this->get(route('employees.create'));

        $response->assertForbidden();
    }

    public function test_encoder_registrar_can_create_employee_and_audit_log_is_recorded(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $payload = [
            'employee_code' => 'EMP-00001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'department' => 'Operations',
            'position' => 'Supervisor',
            'contact_number' => '09171234567',
            'email' => 'juan@example.com',
            'status' => 'active',
        ];

        $response = $this->post(route('employees.store'), $payload);

        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('employees', ['employee_code' => 'EMP-00001']);

        $employee = Employee::where('employee_code', 'EMP-00001')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'employee.created',
            'subject_type' => $employee->getMorphClass(),
            'subject_id' => $employee->id,
        ]);
    }

    public function test_employee_code_is_generated_server_side(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        Employee::factory()->create(['employee_code' => 'EMP-00001']);
        CodeSetting::where('entity', 'employees')->update(['next_number' => 2]);

        $response = $this->post(route('employees.store'), [
            'employee_code' => 'EMP-00001',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('employees', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'employee_code' => 'EMP-00002',
        ]);
    }

    public function test_encoder_registrar_can_update_employee(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $employee = Employee::factory()->create(['status' => 'active']);

        $response = $this->put(route('employees.update', $employee), [
            'employee_code' => $employee->employee_code,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'status' => 'inactive',
        ]);

        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'status' => 'inactive']);
    }

    public function test_administrator_can_delete_employee_soft_delete(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('administrator');

        $employee = Employee::factory()->create();

        $response = $this->delete(route('employees.destroy', $employee));

        $response->assertRedirect(route('employees.index'));
        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
    }

    public function test_encoder_registrar_cannot_delete_employee(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $employee = Employee::factory()->create();

        $response = $this->delete(route('employees.destroy', $employee));

        $response->assertForbidden();
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'deleted_at' => null]);
    }

    public function test_security_officer_cannot_delete_employee(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('security-officer');

        $employee = Employee::factory()->create();

        $response = $this->delete(route('employees.destroy', $employee));

        $response->assertForbidden();
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'deleted_at' => null]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('employees.index'));

        $response->assertRedirect(route('login'));
    }
}
