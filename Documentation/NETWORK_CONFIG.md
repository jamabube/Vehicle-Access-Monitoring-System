# VAMS Network Configuration

> **Purpose:** Document the physical network topology and IP assignments for the RFID reader hardware deployment.

_Last updated: 2026-09-09_

---

## Network Topology

```
┌─────────────────────────────────────────────────────────┐
│  Gateway/Router: 192.168.1.1                            │
│  Subnet: 192.168.1.0/24                                 │
└───────────────────┬─────────────────────────────────────┘
                    │
        ┌───────────┴──────────┬──────────────────────────┐
        │                      │                          │
   ┌────▼─────┐         ┌─────▼──────┐          ┌───────▼────────┐
   │ Dev PC   │         │ RFID Reader│          │ MySQL Server   │
   │ (Laptop) │         │ S4A UHF-   │          │ (XAMPP)        │
   │          │         │ 202415     │          │                │
   │ IP:      │         │            │          │ IP: [same as   │
   │ 192.168. │         │ IP:        │          │ dev PC/local-  │
   │ 1.33     │         │ 192.168.   │          │ host]          │
   │          │         │ 1.116      │          │                │
   │ Port:    │         │            │          │ Port: 3306     │
   │ 8000     │◄────────┤ Port:      │          │ (default)      │
   │ (Laravel)│  HMAC   │ 49152      │          │                │
   └──────────┘  POST   │ (TCP)      │          └────────────────┘
                        └────────────┘
```

---

## Device Inventory

### 1. Development PC / Web Server
- **Role:** Runs Laravel VAMS application + MySQL (XAMPP) + RFID Listener/Device Service (`php artisan rfid:listen`)
- **IP Address:** `192.168.1.33` *(was `192.168.1.21` on 2026-09-08 — this is a DHCP lease, not a static assignment, so re-check with `ipconfig` before relying on it. The listener defaults to `127.0.0.1` for the API precisely so it does not care what this address is.)*
- **Services:**
  - Laravel dev server: `http://192.168.1.33:8000` (or configured port)
  - MySQL: `localhost:3306` (listening on loopback only by default)
- **OS:** Windows (PowerShell-based deployment)

### 2. S4A UHF-202415 RFID Reader
- **Role:** Detects UHF RFID tags (EPC Gen2 protocol) and sends reads to the Listener Service
- **Model:** S4A UHF-202415 (900MHz UHF, WiFi/Ethernet + USB variants)
- **IP Address:** `192.168.1.116` — **a static IP set on the device itself**, not a DHCP lease, so it does not drift. Confirmed in RFIDDemo → Network tab → NET SETTINGS.
- **MAC / Device Name:** `E0:4E:7A:3A:89:39` / `AD-NU_8939`
- **Module settings (as configured, verified 2026-09-09):** Server Type `TCP Server`, DHCP Mode `Static IP`, Device Port `49152`, Net Mask `255.255.255.0`, Gateway `192.168.1.1`
- **Connection:** Ethernet cable to the router

> **⚠ Do not click "Default Settings" in the NET SETTINGS dialog.** It resets the module to its factory address `192.168.2.100` / gateway `192.168.2.1` — a different subnet from this network, which makes the reader invisible to every machine on the LAN and recoverable only over USB or by giving a PC a temporary `192.168.2.x` address. Use "Get Settings" to read, and "Set Settings" + "Save & Restart" only when deliberately changing something.
- **Control Port:** `49152` (TCP, reader's native binary protocol per "MM Version Reader Control Protocol_v1.2")
- **Discovery Port:** `48899` (UDP, "HF-A11ASSISTHREAD" broadcast discovery — see `rfid reader sdk/discover-reader.ps1`)
- **Protocol:** Binary frame format (`SOI/ADR/CID1/CID2/LEN/DATA/CHKSUM`), documented in the vendor SDK at `rfid reader sdk/sdk_UHF Reader/900MHz UHF Reader SDK(MM)/`
- **Current Status (2026-09-09): working.** `php artisan rfid:doctor` passes every check — control port open, MM protocol frames received, API reachable, credentials verified.
- **Note on an earlier false alarm:** for a stretch on 2026-09-09 the reader answered ping but had *every* TCP port closed, and did not reply to `HF-A11ASSISTHREAD` UDP discovery on `48899`. It recovered on its own (a power cycle or module restart). **The reader not answering on `49152` while still replying to ping is a known state for this module — restart the reader before assuming anything worse.** Note also that this module does not implement the `HF-A11ASSISTHREAD` discovery protocol at all, so a silent `48899` is normal and is *not* evidence the reader is missing; use RFIDDemo's Network tab to locate it instead.

### 3. Network Gateway/Router
- **IP Address:** `192.168.1.1`
- **Role:** DHCP server + default gateway for the `192.168.1.0/24` subnet

---

## Communication Flow

### Phase 1: Reader → Listener (Implemented)
The **RFID Listener/Device Service** is `php artisan rfid:listen` (a Laravel console command — a separate long-running process from the web server). It:
1. Maintains a persistent TCP socket to `192.168.1.116:49152` (`RFID_LISTENER_MODE=client`), **or** listens for the reader to dial in (`RFID_LISTENER_MODE=server`) if the reader's WiFi module is configured in TCP Client mode
2. Sends the MM protocol inventory command `7C FF FF 20 00 00 66` ("Read Type C UII", §4.1) every `RFID_LISTENER_POLL_INTERVAL_MS`
3. Parses incoming tag frames (`CC FF FF 20 02 …`) into EPC, RSSI, antenna
4. Suppresses repeat reads of the same tag within `RFID_LISTENER_DEBOUNCE_SECONDS`, then POSTs each remaining read to the API as an HMAC-signed request (see Phase 2)

```powershell
# from the workspace root, with the web server already running:
.\start-listener.bat              # normal operation
.\start-listener.bat --dry-run    # print tag reads without posting them
.\start-listener.bat check        # connectivity diagnostics only
```

**Normally you do not start the listener by hand.** `start.bat` launches it automatically alongside the web server, in its own window, 10 seconds behind the server. Without it, tags are read by the hardware and go nowhere — the reader's lights come on but nothing reaches the website, which is the single most confusing failure mode this system has.

**Only one listener runs at a time.** `start-listener-auto.bat` checks for an existing `rfid:listen` process and backs off if it finds one, so `start.bat` and the logon Scheduled Task cannot end up fighting over the reader's single TCP socket.

**Unattended operation.** The Windows Scheduled Task **"VAMS RFID Listener"** also starts `start-listener-auto.bat` 30 seconds after logon, restarts it if it stops, and appends everything to `logs/rfid-listener.log`. Manage it with:

```powershell
Get-ScheduledTask -TaskName "VAMS RFID Listener"        # is it registered / running?
Start-ScheduledTask  -TaskName "VAMS RFID Listener"     # start it now
Stop-ScheduledTask   -TaskName "VAMS RFID Listener"     # stop it
Disable-ScheduledTask -TaskName "VAMS RFID Listener"    # stop it starting at logon
Get-Content ".\logs\rfid-listener.log" -Tail 30 -Wait   # watch it live
```

The task only starts the *listener*, not the web server — the API must be running for detections to be recorded. The listener tolerates the API being down (it logs the failure and retries on the tag's next read), so ordering at startup does not matter, but nothing is stored until the web server is up.

### Phase 2: Listener → Laravel API (Implemented)
The Listener POSTs HMAC-signed detection payloads to:
```
POST http://127.0.0.1:8000/api/rfid/detections
```
(`RFID_LISTENER_API_URL`. Loopback is the default because the listener runs on the same PC as the web server, so it is immune to the dev PC's IP moving. Point it at the LAN address or an HTTPS production URL when the two are split across machines.)

**Security:** HMAC-SHA256 signature + nonce replay protection + timestamp tolerance window — see `context/SCHEMA.md` §6 for full API contract.

**Headers Required:**
- `X-Rfid-Api-Key`: Matches `rfid_readers.api_key` (plaintext)
- `X-Rfid-Timestamp`: Unix epoch seconds
- `X-Rfid-Nonce`: UUID or random unique string
- `X-Rfid-Signature`: `hash_hmac('sha256', "{api_key}.{timestamp}.{nonce}.{raw_json_body}", decrypted_api_secret)`

**Body Example:**
```json
{
  "event_uuid": "550e8400-e29b-41d4-a716-446655440000",
  "epc": "E2000019760801234567890A",
  "rssi": -42,
  "antenna": "1",
  "detected_at": "2026-09-08 12:34:56"
}
```

**Response:** `201 Created` with decision envelope (`{event_uuid, is_duplicate, decision, denial_reason, direction}`)

### Phase 3: Laravel → MySQL (Already Implemented)
- Laravel connects to `localhost:3306` via `DB_CONNECTION=mysql` in `.env`
- Database: `vams_laravel` (dedicated, separate from the legacy `vams` database)

### Phase 4: Laravel → Dashboard, real-time (Implemented)
The moment a detection is recorded, the API broadcasts `GateActivityRecorded` to the **Reverb** WebSocket server on `127.0.0.1:8080`, which pushes it to every dashboard subscribed to the private `gate-activity` channel. The browser reacts by re-fetching the rendered log, so the gate table updates with no perceptible delay.

Reverb runs as part of `composer dev` (and therefore `start.bat`). To run it alone:

```powershell
php artisan reverb:start          # add --debug to watch connections and messages
```

**If Reverb is not running, nothing breaks.** The broadcast failure is caught and logged, the detection is still recorded, and the dashboard falls back to polling every 2 seconds. Check `storage/logs/laravel.log` for `Could not broadcast gate activity` if you want to confirm why pushes stopped.

---

## Firewall & Port Rules

Ensure these ports are open on the **Dev PC** (Windows Defender Firewall):

| Port  | Protocol | Direction | Purpose |
|-------|----------|-----------|---------|
| 8000  | TCP      | Inbound   | Laravel dev server (or whatever port `php artisan serve` uses) |
| 3306  | TCP      | Loopback only | MySQL (XAMPP default; no external access required) |
| 48899 | UDP      | Inbound/Outbound | RFID reader discovery broadcasts (`discover-reader.ps1`) |
| 49152 | TCP      | Outbound  | Listener → Reader control socket (initiated by Listener, not Laravel) |
| 8080  | TCP      | Inbound (loopback) | Reverb WebSocket server — the dashboard's real-time push. Only needs to be reachable by browsers viewing the dashboard; do not expose it publicly |

**Note:** The Laravel app itself never directly connects to the RFID reader — only the Listener service does. The Laravel app only receives HTTPS POSTs from the Listener.

---

## Verification Steps

### 1. Run the built-in diagnostics (start here)
```powershell
cd "C:\Users\jamabube\Downloads\Vehicle Access Monitoring System\vams-webapp"
php artisan rfid:doctor
```
This is read-only — it writes nothing and posts nothing. It checks, in order: the TCP path to the reader, UDP discovery (which reports the reader's *real* current IP even if the configured one is stale), whether the reader actually speaks the MM protocol and what tags it can see, whether the ingestion API is up, and whether `RFID_LISTENER_API_KEY`/`RFID_LISTENER_API_SECRET` match a registered `rfid_readers` row. Each failure prints the specific next step.

### 1b. Confirm Reader is Reachable (vendor discovery script)
```powershell
powershell -ExecutionPolicy Bypass -File ".\rfid reader sdk\discover-reader.ps1"
```
Expected output:
```
Reply from 192.168.1.116:48899 -> [reader response string with IP/MAC]
```

### 2. Test Reader Control Socket (Manual)
Use the bundled `RFIDDemo.exe`:
1. Open `rfid reader sdk/RFIDDemo3410(1)(1)/RFIDDemo.exe`
2. Network tab → enter IP `192.168.1.116`, port `49152` → click **Connect**
3. Click **Inventory** — should list all tags in range
4. Verify each physical RFID tag/sticker/card is being read correctly

### 3. Watch real tag reads without touching the database
```powershell
php artisan rfid:listen --dry-run
```
Decodes and prints every tag the reader sees, posting nothing. This is the fastest way to confirm the physical tags/stickers/cards are being read and to collect their EPCs for registration in the web UI.

### 4. Run it for real
```powershell
php artisan rfid:listen
```
Each read prints its API outcome: `AUTHORIZED (entry)`, `DENIED (unknown_credential)`, or `duplicate (debounced)`.

See `tests/Feature/RfidListenerForwardingTest.php` and `tests/Feature/RfidIngestionApiTest.php` for working HMAC signature examples.

---

## Listener/Device Service Configuration

All of the below live in `vams-webapp/.env` and are surfaced through `config/rfid.php` under `rfid.listener`.

| Key | Default | Purpose |
|-----|---------|---------|
| `RFID_LISTENER_MODE` | `client` | `client` = VAMS dials the reader (module in TCP Server mode). `server` = VAMS listens and the reader dials in (module in TCP Client mode). |
| `RFID_READER_HOST` / `RFID_READER_PORT` | `192.168.1.116` / `49152` | Where to dial, in `client` mode. |
| `RFID_LISTENER_BIND` / `RFID_LISTENER_PORT` | `0.0.0.0` / `49152` | Where to listen, in `server` mode. |
| `RFID_LISTENER_POLL` | `true` | Send inventory commands on an interval. Harmless (just redundant) if the reader is in active/auto mode and pushes frames unprompted. |
| `RFID_LISTENER_POLL_INTERVAL_MS` | `500` | How often to sweep. |
| `RFID_LISTENER_RECONNECT_DELAY` | `5` | Backoff after the reader link drops. |
| `RFID_LISTENER_DEBOUNCE_SECONDS` | `20` | **Local** repeat-read suppression — see the warning below. |
| `RFID_LISTENER_API_URL` | `http://127.0.0.1:8000/api/rfid/detections` | Where signed detections are POSTed. |
| `RFID_LISTENER_API_KEY` / `RFID_LISTENER_API_SECRET` | — | From `php artisan db:seed --class=RfidReaderSeeder`, or the RFID Readers page. |

> **⚠ Two debounce windows, and they do different jobs.** `RFID_LISTENER_DEBOUNCE_SECONDS` suppresses repeat reads *at the device*, so a tag sitting in the antenna field does not flood the API and the `api_request_nonces` table. `RFID_DEBOUNCE_SECONDS` is the *server's* authoritative duplicate flag. Keep the listener window `>=` the server window. **For a real gate, raise both to comfortably exceed how long a vehicle lingers in the read zone** — otherwise a stationary vehicle whose reads keep landing outside the window gets toggled `entry` → `exit` → `entry`.

---

## Security Notes

- **Never expose the reader's control port (49152) to the public internet** — it has no authentication in the MM protocol
- **Use HTTPS in production** for the Laravel API endpoint to protect the HMAC-signed payloads in transit
- **Rotate `api_secret` periodically** — update the `rfid_readers.api_secret_hash` column and sync to the Listener's config

---

## Troubleshooting

| Symptom | Likely Cause | Fix |
|---------|--------------|-----|
| `discover-reader.ps1` finds nothing | Reader not powered, wrong subnet, or firewall blocking UDP 48899 | Check reader power LED, confirm PC and reader are on 192.168.1.0/24, allow UDP 48899 in Windows Firewall |
| RFIDDemo.exe "Connect" fails | Wrong IP/port, or reader is in serial-only mode | Verify reader's DIP switches are set for network mode; confirm IP via discovery script |
| Laravel API returns `401 Unauthorized` | Invalid HMAC signature or missing headers | Check signature computation logic in Listener (see `HmacSignatureVerifierTest.php` for reference) |
| Laravel API returns `409 Conflict` | Replayed nonce | Ensure each POST uses a fresh UUID; check `api_request_nonces` table |
| Detections recorded but no `access_logs` | All detections marked `is_duplicate=true` | Check `RFID_DEBOUNCE_SECONDS` in `.env` (currently 20s); move tag away from reader antenna |
| A config change in `.env` seems to have no effect | **Another copy of the project is serving port 8000.** A stale `Desktop\Vehicle Access Monitoring System\` copy was found doing exactly this on 2026-09-09, answering API requests with its own older `.env` | `Get-NetTCPConnection -LocalPort 8000 -State Listen` then look up the owning PID's command line; stop it and start the `Downloads\` copy |
| Reader pings but `rfid:doctor` says port 49152 did not answer | **Most likely: the module's TCP server has wedged.** Observed 2026-09-09 — ping fine, all 65535 ports closed, recovered by itself | **Power-cycle the reader first.** If that does not fix it, open RFIDDemo → Network tab → NET SETTINGS → "Get Settings" to confirm the module still holds Static IP `192.168.1.116` / port `49152` / Server Type `TCP Server` |
| `rfid:doctor` reports "No UDP discovery reply on port 48899" | **Expected — ignore it.** This module does not implement the `HF-A11ASSISTHREAD` discovery protocol | Nothing to fix. This line is a `~` note, not a failure, and does not affect the exit code |
| Reader connects intermittently, or `rfid:listen` cannot connect while RFIDDemo is open | The module accepts only a small number of TCP sockets (`AT+MAXSK`) | Close RFIDDemo before running `rfid:listen` — the two compete for the same socket |
| `rfid:listen` connects but decodes no frames | Connected to something that is not the reader (port 49152 is also Windows' RPC endpoint mapper on ordinary PCs), or the wrong control port | Confirm with RFIDDemo.exe on the same IP/port; `rfid:doctor` reports "accepted the connection but sent no valid protocol frames" for exactly this case |
| Same stationary vehicle flips `entry` → `exit` repeatedly | Its reads keep landing outside the debounce window | Raise `RFID_LISTENER_DEBOUNCE_SECONDS` **and** `RFID_DEBOUNCE_SECONDS` above the time a vehicle lingers in the read zone |
| `rfid:listen` reports "Cannot reach the ingestion API" | The web server is not running | Start it with `start.bat`, which starts both halves |
| **Reader lights up but nothing appears on the dashboard** | **The listener is not running.** The reader powers its LEDs and reads tags regardless; `rfid:listen` is what carries those reads to the website | Check for it: `Get-CimInstance Win32_Process -Filter "Name='php.exe'" \| Where-Object { $_.CommandLine -like '*rfid:listen*' }`. Start everything with `start.bat`. Note the Scheduled Task only fires **at logon** — registering it does not start it in the current session |
| `rfid:doctor` says the secret does not match / cannot be decrypted | `.env` credentials are stale, or `APP_KEY` changed since the reader row was created | Regenerate credentials on the RFID Readers page and copy them into `RFID_LISTENER_API_KEY`/`RFID_LISTENER_API_SECRET` |

---

## References

- **Vendor SDK:** `rfid reader sdk/sdk_UHF Reader/900MHz UHF Reader SDK(MM)/`
- **Discovery Script:** `rfid reader sdk/discover-reader.ps1`
- **Laravel API Contract:** `context/SCHEMA.md` §6
- **HMAC Implementation:** `app/Services/Rfid/HmacSignatureVerifier.php`
- **Listener Service:** `app/Console/Commands/RfidListenCommand.php`, `app/Console/Commands/RfidDoctorCommand.php`, `app/Services/Rfid/{MmProtocolCodec,ReaderTransport,DetectionForwarder}.php`
- **Test Suite:** `tests/Feature/RfidIngestionApiTest.php`, `tests/Feature/RfidListenerForwardingTest.php`, `tests/Unit/HmacSignatureVerifierTest.php`, `tests/Unit/MmProtocolCodecTest.php`
