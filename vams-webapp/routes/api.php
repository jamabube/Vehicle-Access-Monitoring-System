<?php

use App\Http\Controllers\Api\RfidIngestionController;
use Illuminate\Support\Facades\Route;

/*
 * Device ingestion. 'throttle:rfid-ingestion' runs before the HMAC check so a
 * flood is shed cheaply, without paying for signature verification or a
 * database lookup on every junk request (manuscript §1.2.2 objective 8 —
 * resistance to flooding attacks). The limiter is keyed per device API key,
 * so one misbehaving reader cannot starve the others.
 */
Route::middleware(['throttle:rfid-ingestion', 'rfid.hmac'])->group(function () {
    Route::post('rfid/detections', [RfidIngestionController::class, 'store'])
        ->name('api.rfid.detections.store');
});
