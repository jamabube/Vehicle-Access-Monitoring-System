<?php

namespace Database\Seeders;

use App\Models\RfidReader;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class RfidReaderSeeder extends Seeder
{
    /**
     * Seed the physical S4A UHF-202415 reader connected to the system.
     */
    public function run(): void
    {
        // Generate API credentials for the physical reader
        $apiKey = 'reader_'.Str::random(32);
        $apiSecret = Str::random(64); // Plain secret, will be encrypted in DB

        $reader = RfidReader::firstOrCreate(
            ['ip_address' => '192.168.1.116'],
            [
                'device_name' => 'Main Gate Reader',
                'model' => 'S4A UHF-202415',
                'location' => 'Main Entrance',
                'ip_address' => '192.168.1.116',
                'api_key' => $apiKey,
                'api_secret_hash' => Crypt::encryptString($apiSecret),
                'status' => 'offline', // Will become 'online' when Listener service connects
            ]
        );

        // Output credentials for the Windows Listener service configuration
        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════════════════════');
        $this->command->info('  RFID Reader Registered: '.$reader->device_name);
        $this->command->info('═══════════════════════════════════════════════════════════════════════');
        $this->command->newLine();
        $this->command->line('  Device Code: '.$reader->device_code);
        $this->command->line('  IP Address:  '.$reader->ip_address);
        $this->command->line('  Status:      '.$reader->status);
        $this->command->newLine();
        $this->command->warn('  Save these credentials for the Windows Listener/Device Service:');
        $this->command->newLine();
        $this->command->line('  API_KEY:    '.$apiKey);
        $this->command->line('  API_SECRET: '.$apiSecret);
        $this->command->newLine();
        $this->command->warn('  ⚠ Copy these NOW - the API_SECRET cannot be retrieved later!');
        $this->command->newLine();
        $this->command->info('  Laravel API Endpoint: '.config('app.url').'/api/rfid/detections');
        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════════════════════');
        $this->command->newLine();
    }
}
