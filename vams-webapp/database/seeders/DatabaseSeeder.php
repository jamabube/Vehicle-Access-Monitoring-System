<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            CodeSettingSeeder::class,
            RfidReaderSeeder::class,
        ]);

        $administrator = Role::where('slug', 'administrator')->first();

        $admin = User::firstOrCreate(
            ['email' => 'admin@vams.test'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password'),
                'status' => 'active',
            ]
        );

        $admin->update([
            'role_id' => $administrator?->id,
            'status' => 'active',
        ]);
    }
}
