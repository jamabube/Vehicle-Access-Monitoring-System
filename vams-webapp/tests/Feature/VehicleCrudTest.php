<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleCrudTest extends TestCase
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

    public function test_security_officer_can_view_vehicle_index_but_not_create(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('security-officer');

        Vehicle::factory()->count(2)->create();

        $this->get(route('vehicles.index'))->assertOk();
        $this->get(route('vehicles.create'))->assertForbidden();
    }

    public function test_encoder_registrar_can_create_vehicle_linked_to_employee(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $employee = Employee::factory()->create();

        $response = $this->post(route('vehicles.store'), [
            'plate_number' => 'ABC-1234',
            'vehicle_type' => 'Sedan',
            'make' => 'Toyota',
            'model' => 'Vios',
            'color' => 'White',
            'owner_type' => 'employee',
            'employee_id' => $employee->id,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('vehicles.index'));
        $this->assertDatabaseHas('vehicles', [
            'plate_number' => 'ABC-1234',
            'employee_id' => $employee->id,
        ]);

        $vehicle = Vehicle::where('plate_number', 'ABC-1234')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'vehicle.created',
            'subject_type' => $vehicle->getMorphClass(),
            'subject_id' => $vehicle->id,
        ]);
    }

    public function test_vehicle_requires_employee_id_when_owner_type_is_employee(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $response = $this->post(route('vehicles.store'), [
            'plate_number' => 'XYZ-9999',
            'owner_type' => 'employee',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('employee_id');
    }

    public function test_vehicle_plate_number_must_be_unique(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        Vehicle::factory()->create(['plate_number' => 'DUP-0001']);

        $response = $this->post(route('vehicles.store'), [
            'plate_number' => 'DUP-0001',
            'owner_type' => 'visitor',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('plate_number');
    }

    public function test_encoder_registrar_can_update_vehicle_but_not_delete_it(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $vehicle = Vehicle::factory()->create(['status' => 'active', 'owner_type' => 'visitor', 'employee_id' => null]);

        $updateResponse = $this->put(route('vehicles.update', $vehicle), [
            'plate_number' => $vehicle->plate_number,
            'owner_type' => 'visitor',
            'status' => 'inactive',
        ]);

        $updateResponse->assertRedirect(route('vehicles.index'));
        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'status' => 'inactive']);

        $deleteResponse = $this->delete(route('vehicles.destroy', $vehicle));

        $deleteResponse->assertForbidden();
        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'deleted_at' => null]);
    }

    public function test_administrator_can_delete_vehicle(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('administrator');

        $vehicle = Vehicle::factory()->create(['status' => 'active', 'owner_type' => 'visitor', 'employee_id' => null]);

        $deleteResponse = $this->delete(route('vehicles.destroy', $vehicle));

        $deleteResponse->assertRedirect(route('vehicles.index'));
        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }

    public function test_security_officer_cannot_update_vehicle(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('security-officer');

        $vehicle = Vehicle::factory()->create();

        $response = $this->put(route('vehicles.update', $vehicle), [
            'plate_number' => $vehicle->plate_number,
            'owner_type' => 'visitor',
            'status' => 'inactive',
        ]);

        $response->assertForbidden();
    }
}
