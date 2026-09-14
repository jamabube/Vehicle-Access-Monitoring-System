<?php

namespace Database\Seeders;

use App\Models\CodeSetting;
use App\Models\Employee;
use App\Models\RfidReader;
use App\Models\Visitor;
use App\Models\VisitorVisit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CodeSettingSeeder extends Seeder
{
    /**
     * Seed code_settings with defaults based on existing data.
     *
     * For entities with existing manually-entered codes (employees, rfid_readers),
     * we scan the database to find the highest numeric suffix already in use and
     * set next_number one above that to avoid collisions. For new entities, we
     * start at 1.
     */
    public function run(): void
    {
        $settings = [
            [
                'entity' => 'employees',
                'prefix' => 'EMP-',
                'length' => 5,
                'suffix' => '',
                'next_number' => $this->getNextNumberForEmployees(),
            ],
            [
                'entity' => 'rfid_readers',
                'prefix' => 'RDR-',
                'length' => 4,
                'suffix' => '',
                'next_number' => $this->getNextNumberForRfidReaders(),
            ],
            [
                'entity' => 'rfid_tags',
                'prefix' => 'TAG-',
                'length' => 5,
                'suffix' => '',
                'next_number' => 1, // New entity, start at 1
            ],
            [
                'entity' => 'visitors',
                'prefix' => 'VIS-',
                'length' => 5,
                'suffix' => '',
                'next_number' => $this->getNextNumberForVisitors(),
            ],
            [
                'entity' => 'vehicles',
                'prefix' => 'VEH-',
                'length' => 5,
                'suffix' => '',
                'next_number' => 1, // New entity, start at 1
            ],
            [
                'entity' => 'visitor_visits',
                'prefix' => 'VST-',
                'length' => 6,
                'suffix' => '',
                'next_number' => $this->getNextNumberForVisitorVisits(),
            ],
            [
                'entity' => 'rfid_assignments',
                'prefix' => 'ASG-',
                'length' => 6,
                'suffix' => '',
                'next_number' => 1, // New entity, start at 1
            ],
        ];

        foreach ($settings as $setting) {
            CodeSetting::updateOrCreate(
                ['entity' => $setting['entity']],
                $setting
            );
        }
    }

    /**
     * Find the next safe number for employees based on existing employee_code values.
     * Assumes format like "EMP-00001", "01", "02" etc. Extracts numeric part.
     */
    protected function getNextNumberForEmployees(): int
    {
        $codes = Employee::pluck('employee_code');
        $maxNum = 0;

        foreach ($codes as $code) {
            // Extract only digits from code
            $num = (int) preg_replace('/[^0-9]/', '', $code);
            $maxNum = max($maxNum, $num);
        }

        return $maxNum + 1;
    }

    /**
     * Find the next safe number for rfid_readers based on existing device_code values.
     */
    protected function getNextNumberForRfidReaders(): int
    {
        $codes = RfidReader::pluck('device_code');
        $maxNum = 0;

        foreach ($codes as $code) {
            $num = (int) preg_replace('/[^0-9]/', '', $code);
            $maxNum = max($maxNum, $num);
        }

        return $maxNum + 1;
    }

    /**
     * Find the next safe number for visitors based on existing id (no code field yet).
     * Use max(id) + 1 as a safe starting point.
     */
    protected function getNextNumberForVisitors(): int
    {
        $maxId = Visitor::max('id');

        return $maxId ? $maxId + 1 : 1;
    }

    /**
     * Find the next safe number for visitor_visits based on existing id.
     */
    protected function getNextNumberForVisitorVisits(): int
    {
        $maxId = VisitorVisit::max('id');

        return $maxId ? $maxId + 1 : 1;
    }
}
