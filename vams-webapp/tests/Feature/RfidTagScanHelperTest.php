<?php

namespace Tests\Feature;

use App\Models\RfidDetection;
use App\Models\RfidReader;
use App\Models\RfidTag;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The "scan a tag" helper on the RFID tag form, which lets staff pick an EPC
 * the reader just saw instead of reading it off a terminal and retyping it.
 *
 * It reads recent rows from `rfid_detections` rather than talking to the
 * reader, so these tests seed detections directly — that is exactly what the
 * listener would have written.
 */
class RfidTagScanHelperTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $slug): User
    {
        $this->seedCoreSettings();

        $role = Role::where('slug', $slug)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $this->actingAs($user);

        return $user;
    }

    private function recordDetection(string $epc, int $secondsAgo = 0, int $rssi = -55): RfidDetection
    {
        $detectedAt = now()->subSeconds($secondsAgo);

        return RfidDetection::create([
            'event_uuid' => (string) Str::uuid(),
            'rfid_reader_id' => RfidReader::factory()->create()->id,
            'epc' => $epc,
            'rfid_tag_id' => null,
            'rssi' => $rssi,
            'antenna' => '0',
            'detected_at' => $detectedAt,
            'received_at' => $detectedAt,
            'is_duplicate' => false,
        ]);
    }

    public function test_it_returns_a_recently_seen_unregistered_tag(): void
    {
        $this->actingAsRole('encoder-registrar');
        $this->recordDetection('E2806915000040281DB049BE9C91', 3, -76);

        $response = $this->getJson(route('rfid-tags.recent-scans'));

        $response->assertOk();
        $response->assertJsonPath('scans.0.epc', 'E2806915000040281DB049BE9C91');
        $response->assertJsonPath('scans.0.rssi', -76);
        $response->assertJsonPath('scans.0.registered_as', null);
    }

    public function test_it_flags_a_tag_that_is_already_registered(): void
    {
        $this->actingAsRole('encoder-registrar');

        $tag = RfidTag::factory()->create(['epc' => 'E2806915000040281DB049BE9C91']);
        $this->recordDetection('E2806915000040281DB049BE9C91', 3);

        $response = $this->getJson(route('rfid-tags.recent-scans'));

        $response->assertOk();
        $response->assertJsonPath('scans.0.registered_as', $tag->tag_code);
    }

    public function test_it_ignores_reads_older_than_the_scan_window(): void
    {
        $this->actingAsRole('encoder-registrar');

        $this->recordDetection('FRESH-TAG', 10);
        $this->recordDetection('STALE-TAG', 600);

        $response = $this->getJson(route('rfid-tags.recent-scans'));

        $response->assertOk();
        $response->assertJsonCount(1, 'scans');
        $response->assertJsonPath('scans.0.epc', 'FRESH-TAG');
    }

    public function test_it_lists_each_tag_once_using_its_most_recent_read(): void
    {
        $this->actingAsRole('encoder-registrar');

        // A tag lingering near the antenna is read repeatedly; the picker must
        // show it as one entry, not one row per read.
        $this->recordDetection('REPEATED-TAG', 30, -80);
        $this->recordDetection('REPEATED-TAG', 2, -60);

        $response = $this->getJson(route('rfid-tags.recent-scans'));

        $response->assertOk();
        $response->assertJsonCount(1, 'scans');
        $response->assertJsonPath('scans.0.rssi', -60, 'The newest read should win.');
    }

    public function test_security_officer_cannot_use_the_scan_helper(): void
    {
        $this->actingAsRole('security-officer');
        $this->recordDetection('E2806915000040281DB049BE9C91', 3);

        $this->getJson(route('rfid-tags.recent-scans'))->assertForbidden();
    }

    public function test_guests_cannot_use_the_scan_helper(): void
    {
        $this->seedCoreSettings();
        $this->recordDetection('E2806915000040281DB049BE9C91', 3);

        $this->get(route('rfid-tags.recent-scans'))->assertRedirect(route('login'));
    }

    public function test_the_scan_panel_is_rendered_on_the_create_form(): void
    {
        $this->actingAsRole('encoder-registrar');

        $response = $this->get(route('rfid-tags.create'));

        $response->assertOk();
        $response->assertSee('Scan a tag');
        $response->assertSee('data-scan-panel', escape: false);
        $response->assertSee(route('rfid-tags.recent-scans'), escape: false);
    }

    public function test_the_recent_scans_route_is_not_mistaken_for_a_tag_id(): void
    {
        $this->actingAsRole('encoder-registrar');

        // 'rfid-tags/recent-scans' must not be captured by the resource's
        // 'rfid-tags/{rfid_tag}' show route.
        $this->getJson(route('rfid-tags.recent-scans'))
            ->assertOk()
            ->assertJsonStructure(['window_seconds', 'scans']);
    }
}
