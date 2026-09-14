<?php

namespace App\Events;

use App\Models\AccessLog;
use App\Models\RfidDetection;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushed to the dashboard the instant a tag is read, so the gate log updates
 * without waiting for the next poll.
 *
 * `ShouldBroadcastNow` rather than `ShouldBroadcast`: the queue runs on the
 * `database` driver, so a queued broadcast would wait for a worker to pick the
 * job up — seconds of latency, which defeats the point. Broadcasting inline
 * costs the ingestion request one local HTTP call to Reverb.
 *
 * The payload is deliberately a *notification*, not the data: the dashboard
 * reacts by re-fetching the rendered log from the existing gate-activity
 * endpoint. That keeps one rendering path (Blade) instead of duplicating row
 * markup in JavaScript, and means a viewer whose socket dropped and reconnected
 * still converges on the correct state.
 */
class GateActivityRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly RfidDetection $detection,
        public readonly ?AccessLog $accessLog,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('gate-activity')];
    }

    public function broadcastAs(): string
    {
        return 'gate.activity';
    }

    /**
     * A small summary, enough for the dashboard to show a toast without
     * waiting for its follow-up fetch.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'epc' => $this->detection->epc,
            'is_duplicate' => (bool) $this->detection->is_duplicate,
            'decision' => $this->accessLog?->decision,
            'direction' => $this->accessLog?->direction,
            'denial_reason' => $this->accessLog?->denial_reason,
            'plate_number' => $this->accessLog?->vehicle?->plate_number,
            'occurred_at' => $this->detection->detected_at->format('g:i:s A'),
        ];
    }
}
