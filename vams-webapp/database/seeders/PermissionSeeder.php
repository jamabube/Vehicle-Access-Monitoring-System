<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Permissions available in the system, grouped by resource.
     *
     * @var array<string, array<string, string>>
     */
    protected array $permissions = [
        'dashboard' => [
            'view' => 'View the dashboard',
        ],
        'employees' => [
            'view' => 'View employees',
            'create' => 'Create employees',
            'update' => 'Update employees',
            'delete' => 'Delete employees',
        ],
        'visitors' => [
            'view' => 'View visitors',
            'create' => 'Create visitors',
            'update' => 'Update visitors',
            'delete' => 'Delete visitors',
        ],
        'visitor_visits' => [
            'view' => 'View visitor visits',
            'create' => 'Check in visitors (create visits)',
            'update' => 'Update visits and check out visitors',
            'delete' => 'Delete visitor visits',
        ],
        'vehicles' => [
            'view' => 'View vehicles',
            'create' => 'Create vehicles',
            'update' => 'Update vehicles',
            'update_state' => 'Manually update vehicle state (override RFID-driven state)',
            'delete' => 'Delete vehicles',
        ],
        'rfid_tags' => [
            'view' => 'View RFID tags',
            'create' => 'Create RFID tags',
            'update' => 'Update RFID tags',
            'delete' => 'Delete RFID tags',
        ],
        'rfid_assignments' => [
            'view' => 'View RFID assignments',
            'create' => 'Create RFID assignments',
            'update' => 'Update RFID assignments',
            'delete' => 'Delete RFID assignments',
        ],
        'rfid_readers' => [
            'view' => 'View RFID readers',
            'create' => 'Create RFID readers',
            'update' => 'Update RFID readers',
            'delete' => 'Delete RFID readers',
        ],
        'access_logs' => [
            'view' => 'View access logs',
        ],
        'audit_logs' => [
            'view' => 'View audit logs',
        ],
        'system_logs' => [
            'view' => 'View system logs',
        ],
        'system_settings' => [
            'view' => 'View system settings',
            'update' => 'Update system settings',
        ],
        'users' => [
            'view' => 'View user accounts',
            'create' => 'Create user accounts',
            'update' => 'Update user accounts',
            'delete' => 'Delete user accounts',
        ],
    ];

    /**
     * Seed the permissions table.
     */
    public function run(): void
    {
        foreach ($this->permissions as $resource => $actions) {
            foreach ($actions as $action => $description) {
                Permission::updateOrCreate(
                    ['slug' => "{$resource}.{$action}"],
                    [
                        'name' => ucfirst($action).' '.str_replace('_', ' ', $resource),
                        'description' => $description,
                    ]
                );
            }
        }
    }
}
