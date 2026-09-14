<?php

namespace App\Http\Controllers;

use App\Http\Requests\RfidReader\StoreRfidReaderRequest;
use App\Http\Requests\RfidReader\UpdateRfidReaderRequest;
use App\Models\AuditLog;
use App\Models\RfidReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RfidReaderController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:rfid_readers.view', only: ['index', 'show']),
            new Middleware('can:rfid_readers.create', only: ['create', 'store']),
            new Middleware('can:rfid_readers.update', only: ['edit', 'update', 'regenerateCredentials']),
            new Middleware('can:rfid_readers.delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of RFID readers.
     */
    public function index(Request $request): View
    {
        $rfidReaders = RfidReader::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('device_name', 'like', "%{$search}%")
                        ->orWhere('device_code', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('device_name')
            ->paginate(15)
            ->withQueryString();

        return view('rfid-readers.index', compact('rfidReaders'));
    }

    /**
     * Show the form for creating a new RFID reader.
     */
    public function create(): View
    {
        return view('rfid-readers.create', ['rfidReader' => new RfidReader]);
    }

    /**
     * Store a newly created RFID reader.
     *
     * The device-service API credentials (api_key/api_secret) are generated
     * server-side and never accepted from client input. The plaintext secret
     * is only ever shown once, immediately after creation, via a flashed
     * session value — it is never logged in plaintext. It is persisted
     * encrypted (Crypt::encryptString(), AES-256 keyed off APP_KEY) rather
     * than hashed, because the RFID ingestion API must be able to recompute
     * an HMAC signature from it — a one-way hash would make that impossible.
     */
    public function store(StoreRfidReaderRequest $request): RedirectResponse
    {
        [$apiKey, $apiSecret] = $this->generateCredentials();

        $rfidReader = RfidReader::create([
            ...$request->validated(),
            'api_key' => $apiKey,
            'api_secret_hash' => Crypt::encryptString($apiSecret),
        ]);

        AuditLog::record('rfid_reader.created', $rfidReader, $request, $request->validated());

        return redirect()->route('rfid-readers.show', $rfidReader)
            ->with('status', 'RFID reader created successfully.')
            ->with('plain_api_secret', $apiSecret);
    }

    /**
     * Display the specified RFID reader.
     */
    public function show(RfidReader $rfidReader): View
    {
        return view('rfid-readers.show', compact('rfidReader'));
    }

    /**
     * Show the form for editing the specified RFID reader.
     */
    public function edit(RfidReader $rfidReader): View
    {
        return view('rfid-readers.edit', ['rfidReader' => $rfidReader]);
    }

    /**
     * Update the specified RFID reader (metadata only, not credentials).
     */
    public function update(UpdateRfidReaderRequest $request, RfidReader $rfidReader): RedirectResponse
    {
        $rfidReader->update($request->validated());

        AuditLog::record('rfid_reader.updated', $rfidReader, $request, $request->validated());

        return redirect()->route('rfid-readers.index')->with('status', 'RFID reader updated successfully.');
    }

    /**
     * Regenerate the API key/secret for the specified RFID reader.
     *
     * Invalidates the previous credentials immediately (overwritten, not
     * retained) — the device service must be reconfigured with the new pair.
     */
    public function regenerateCredentials(Request $request, RfidReader $rfidReader): RedirectResponse
    {
        [$apiKey, $apiSecret] = $this->generateCredentials();

        $rfidReader->update([
            'api_key' => $apiKey,
            'api_secret_hash' => Crypt::encryptString($apiSecret),
        ]);

        AuditLog::record('rfid_reader.credentials_regenerated', $rfidReader, $request);

        return redirect()->route('rfid-readers.show', $rfidReader)
            ->with('status', 'RFID reader credentials regenerated successfully.')
            ->with('plain_api_secret', $apiSecret);
    }

    /**
     * Remove the specified RFID reader.
     */
    public function destroy(Request $request, RfidReader $rfidReader): RedirectResponse
    {
        $rfidReader->delete();

        AuditLog::record('rfid_reader.deleted', $rfidReader, $request);

        return redirect()->route('rfid-readers.index')->with('status', 'RFID reader deleted successfully.');
    }

    /**
     * Generate a new [api_key, api_secret] pair for a reader.
     *
     * @return array{0: string, 1: string}
     */
    protected function generateCredentials(): array
    {
        return [Str::random(40), Str::random(64)];
    }
}
