<?php

namespace App\Services\Rfid;

use App\Models\AccessLog;
use App\Models\RfidTag;
use App\Models\Vehicle;
use App\Models\VisitorVisit;
use Carbon\CarbonInterface;

/**
 * Resolves a detected EPC into an access decision (authorized/denied) plus
 * the entry/exit direction, and applies the resulting vehicle state change.
 *
 * Kept as its own service (rather than inline controller logic) per
 * context/RULES.md §4 Dependency Inversion guidance for RFID business logic.
 *
 * Direction/state-machine design note (documented per
 * context/ARCHITECTURE.md §9 "vehicle state machine" placeholder):
 *   - Vehicle-linked credentials (sticker or a card assignment carrying a
 *     vehicle) toggle off `vehicles.current_state` (outside <-> inside),
 *     which is the authoritative record of where that vehicle currently is.
 *   - Visitor-only credentials with no vehicle (on-foot visitor card) toggle
 *     off the direction of their own last authorized access_logs entry for
 *     that visit, defaulting to 'entry' if none exists yet.
 */
class RfidAccessResolver
{
    /**
     * @return array{decision: string, denial_reason: ?string, direction: ?string, rfid_tag_id: ?int, vehicle_id: ?int, visitor_visit_id: ?int}
     */
    public function resolve(?RfidTag $tag, CarbonInterface $occurredAt): array
    {
        if (! $tag) {
            return $this->denied(null, 'unknown_credential');
        }

        if (! $tag->isActive()) {
            return $this->denied($tag->id, 'credential_inactive');
        }

        if ($tag->expires_at !== null && $tag->expires_at->isPast()) {
            return $this->denied($tag->id, 'credential_expired');
        }

        $assignment = $tag->currentAssignment;

        if (! $assignment) {
            return $this->denied($tag->id, 'unassigned_credential');
        }

        if ($assignment->vehicle_id !== null) {
            return $this->resolveForVehicle($tag, $assignment->vehicle, $occurredAt);
        }

        if ($assignment->visitor_visit_id !== null) {
            return $this->resolveForVisit($tag, $assignment->visitorVisit, $occurredAt);
        }

        return $this->denied($tag->id, 'unassigned_credential');
    }

    /**
     * @return array{decision: string, denial_reason: ?string, direction: ?string, rfid_tag_id: ?int, vehicle_id: ?int, visitor_visit_id: ?int}
     */
    private function resolveForVehicle(RfidTag $tag, ?Vehicle $vehicle, CarbonInterface $occurredAt): array
    {
        if (! $vehicle || $vehicle->trashed() || $vehicle->status !== 'active') {
            return $this->denied($tag->id, 'vehicle_inactive');
        }

        $direction = $vehicle->isInside() ? 'exit' : 'entry';

        $vehicle->forceFill([
            'current_state' => $direction === 'entry' ? 'inside' : 'outside',
            'last_seen_at' => $occurredAt,
        ])->save();

        return [
            'decision' => 'authorized',
            'denial_reason' => null,
            'direction' => $direction,
            'rfid_tag_id' => $tag->id,
            'vehicle_id' => $vehicle->id,
            'visitor_visit_id' => null,
        ];
    }

    /**
     * @return array{decision: string, denial_reason: ?string, direction: ?string, rfid_tag_id: ?int, vehicle_id: ?int, visitor_visit_id: ?int}
     */
    private function resolveForVisit(RfidTag $tag, ?VisitorVisit $visit, CarbonInterface $occurredAt): array
    {
        if (! $visit || ! $visit->isActive()) {
            return $this->denied($tag->id, 'visit_not_active');
        }

        if ($visit->isExpired()) {
            return $this->denied($tag->id, 'visit_expired');
        }

        $lastDirection = AccessLog::query()
            ->where('visitor_visit_id', $visit->id)
            ->where('decision', 'authorized')
            ->orderByDesc('occurred_at')
            ->value('direction');

        $direction = $lastDirection === 'entry' ? 'exit' : 'entry';

        if ($visit->vehicle_id !== null) {
            $visit->vehicle?->forceFill([
                'current_state' => $direction === 'entry' ? 'inside' : 'outside',
                'last_seen_at' => $occurredAt,
            ])->save();
        }

        return [
            'decision' => 'authorized',
            'denial_reason' => null,
            'direction' => $direction,
            'rfid_tag_id' => $tag->id,
            'vehicle_id' => null,
            'visitor_visit_id' => $visit->id,
        ];
    }

    /**
     * @return array{decision: string, denial_reason: ?string, direction: ?string, rfid_tag_id: ?int, vehicle_id: ?int, visitor_visit_id: ?int}
     */
    private function denied(?int $tagId, string $reason): array
    {
        return [
            'decision' => 'denied',
            'denial_reason' => $reason,
            'direction' => null,
            'rfid_tag_id' => $tagId,
            'vehicle_id' => null,
            'visitor_visit_id' => null,
        ];
    }
}
