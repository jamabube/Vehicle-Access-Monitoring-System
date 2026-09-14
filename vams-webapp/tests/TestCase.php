<?php

namespace Tests;

use Database\Seeders\CodeSettingSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed core system settings required for all tests.
     * Call this in test classes that need permissions, roles, and code generation.
     */
    protected function seedCoreSettings(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(CodeSettingSeeder::class);
    }
}
