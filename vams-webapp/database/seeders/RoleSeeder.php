<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class RoleSeeder extends Seeder
{
    /**
     * Role definitions mapped to permission slug patterns.
     *
     * A permission slug matches if it equals an entry exactly, or if the
     * entry ends with '.*' and the slug's resource prefix matches.
     *
     * @var array<string, array{name: string, description: string, permissions: array<int, string>}>
     */
    protected array $roles = [
        'administrator' => [
            'name' => 'Administrator',
            'description' => 'Full system access, including user account management and system settings.',
            'permissions' => ['*'],
        ],
        'security-officer' => [
            'name' => 'Security Officer',
            'description' => 'Monitors real-time gate access; read-only visibility into logs, employees, visitors, vehicles, and RFID credentials.',
            'permissions' => [
                'dashboard.view',
                'access_logs.view',
                'employees.view',
                'visitors.view',
                'visitor_visits.view',
                'vehicles.view',
                'rfid_tags.view',
                'rfid_readers.view',
            ],
        ],
        'encoder-registrar' => [
            'name' => 'Encoder/Registrar',
            'description' => 'Manages employees, visitors, vehicles, and RFID tags/assignments/readers (create/update only — deletion is admin-only). No user account or system settings access.',
            'permissions' => [
                'dashboard.view',
                // Explicit view/create/update lists (no '.delete') for every
                // resource below — deleting records is reserved for the
                // Administrator role only.
                'employees.view',
                'employees.create',
                'employees.update',
                'visitors.view',
                'visitors.create',
                'visitors.update',
                'visitor_visits.view',
                'visitor_visits.create',
                'visitor_visits.update',
                // Vehicles additionally exclude vehicles.update_state so
                // encoder/registrar cannot override the RFID-driven
                // inside/outside state machine (FINDING #11 — admin-only).
                'vehicles.view',
                'vehicles.create',
                'vehicles.update',
                'rfid_tags.view',
                'rfid_tags.create',
                'rfid_tags.update',
                'rfid_assignments.view',
                'rfid_assignments.create',
                'rfid_assignments.update',
                'rfid_readers.view',
                'rfid_readers.create',
                'rfid_readers.update',
            ],
        ],
    ];

    /**
     * Seed the roles table and attach their permissions.
     */
    public function run(): void
    {
        $allPermissions = Permission::all();

        foreach ($this->roles as $slug => $definition) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                ]
            );

            $resolved = $this->resolvePermissionIds($definition['permissions'], $allPermissions);

            $role->permissions()->sync($resolved);
        }
    }

    /**
     * Resolve a list of permission slug patterns ('*' or 'resource.*' or exact) to IDs.
     *
     * @param  array<int, string>  $patterns
     * @param  Collection<int, Permission>  $allPermissions
     * @return array<int, int>
     */
    protected function resolvePermissionIds(array $patterns, $allPermissions): array
    {
        if (in_array('*', $patterns, true)) {
            return $allPermissions->pluck('id')->all();
        }

        $ids = [];

        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1); // keep trailing '.'
                $matches = $allPermissions->filter(
                    fn (Permission $permission) => str_starts_with($permission->slug, $prefix)
                );
                $ids = array_merge($ids, $matches->pluck('id')->all());

                continue;
            }

            $match = $allPermissions->firstWhere('slug', $pattern);

            if ($match) {
                $ids[] = $match->id;
            }
        }

        return array_values(array_unique($ids));
    }
}
