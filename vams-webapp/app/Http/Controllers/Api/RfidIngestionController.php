<?php

namespace App\Http\Controllers\Api;

use App\Events\GateActivityRecorded;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rfid\StoreRfidDetectionRequest;
use App\Models\AccessLog;
use App\Models\RfidDetection;
use App\Models\RfidReader;
use App\Models\RfidTag;
use App\Services\Rfid\RfidAccessResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * RFID device ingestion endpoint, consumed by the external Windows RFID
 * Listener/Device Service. Authenticated by the 'rfid.hmac' middleware
 * (App\Http\Middleware\VerifyRfidSignature), which attaches the resolved
 * RfidReader to the request under the 'rfidReader' attribute.
 */
class RfidIngestionController extends Controller
{
    public function __construct(private readonly RfidAccessResolver $resolver) {}

    /**
     * Record a single RFID detection event and resolve it into an access
     * decision (authorized/denied), inside one DB transaction.
     */
    public function store(StoreRfidDetectionRequest $request): JsonResponse
    {
        /** @var RfidReader $reader */
        $reader = $request->attributes->get('rfidReader');

        $data = $request->validated();
        $detectedAt = Carbon::parse($data['detected_at']);
        $receivedAt = now();

        $result = DB::transaction(function () use ($reader, $data, $detectedAt, $receivedAt) {
            $tag = RfidTag::where('epc', $data['epc'])->first();

            $isDuplicate = $this->isWithinDebounceWindow($data['epc'], $detectedAt);

            $detection = RfidDetection::create([
                'event_uuid' => $data['event_uuid'],
                'rfid_reader_id' => $reader->id,
                'epc' => $data['epc'],
                'rfid_tag_id' => $tag?->id,
                'rssi' => $data['rssi'] ?? null,
                'antenna' => $data['antenna'] ?? null,
                'detected_at' => $detectedAt,
                'received_at' => $receivedAt,
                'is_duplicate' => $isDuplicate,
            ]);

            if ($isDuplicate) {
                return ['detection' => $detection, 'accessLog' => null];
            }

            $resolution = $this->resolver->resolve($tag, $detectedAt);

            $accessLog = AccessLog::create([
                'rfid_detection_id' => $detection->id,
                'rfid_tag_id' => $resolution['rfid_tag_id'],
                'vehicle_id' => $resolution['vehicle_id'],
                'visitor_visit_id' => $resolution['visitor_visit_id'],
                'direction' => $resolution['direction'],
                'decision' => $resolution['decision'],
                'denial_reason' => $resolution['denial_reason'],
                'occurred_at' => $detectedAt,
            ]);

            return ['detection' => $detection, 'accessLog' => $accessLog];
        });

        $this->announce($result['detection'], $result['accessLog']);

        return response()->json([
            'event_uuid' => $result['detection']->event_uuid,
            'is_duplicate' => $result['detection']->is_duplicate,
            'decision' => $result['accessLog']?->decision,
            'denial_reason' => $result['accessLog']?->denial_reason,
            'direction' => $result['accessLog']?->direction,
        ], 201);
    }

    /**
     * Push the detection to any dashboard watching, outside the transaction.
     *
     * Deliberately fire-and-forget: broadcasting is a convenience, and the
     * WebSocket server is a separate process that may well be stopped. A gate
     * detection must never fail to be recorded because a nicety is down, so a
     * broadcast failure is logged and swallowed — the detection is already
     * committed by this point, and dashboards still poll as a fallback.
     */
    private function announce(RfidDetection $detection, ?AccessLog $accessLog): void
    {
        try {
            GateActivityRecorded::dispatch($detection, $accessLog);
        } catch (Throwable $e) {
            Log::warning('Could not broadcast gate activity; the detection was still recorded.', [
                'event_uuid' => $detection->event_uuid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * True if a prior detection for this EPC exists within the configured
     * debounce window (context/RULES.md §5 — RFID_DEBOUNCE_SECONDS).
     */
    private function isWithinDebounceWindow(string $epc, Carbon $detectedAt): bool
    {
        $debounceSeconds = (int) config('rfid.debounce_seconds');

        return RfidDetection::where('epc', $epc)
            ->where('detected_at', '>=', $detectedAt->clone()->subSeconds($debounceSeconds))
            ->where('detected_at', '<=', $detectedAt)
            ->exists();
    }
}
