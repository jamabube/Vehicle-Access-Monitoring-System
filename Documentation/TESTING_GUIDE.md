# RFID System Testing Guide

> **Quick Start:** How to test the complete VAMS RFID ingestion pipeline from physical reader to database.

_Last updated: 2026-09-09_

---

## Prerequisites

✅ **RFID Reader:** S4A UHF-202415 at `192.168.1.116` — **connected and verified** via RFIDDemo.exe  
✅ **Laravel App:** Running at `http://192.168.1.21:8000` (started via `php artisan serve --host=192.168.1.21`)  
✅ **Database:** `vams_laravel` on MySQL (XAMPP)  
✅ **Reader Credentials:** Generated via `php artisan db:seed --class=RfidReaderSeeder`

**Your Reader API Credentials (saved from seeder output):**
```
Device Code: RDR-0001
API_KEY:     reader_WWkx671JWFHitwx0yofbLOx0milkxryJ
API_SECRET:  rmL1iEWbFvwY5SatWkLvFI6EYHFg2YPZwtt6QXyPOLqv8Ma8McTdCD8cJ3vA4LNd
Endpoint:    http://192.168.1.21:8000/api/rfid/detections
```

---

## Testing Workflow

### Phase 0: Check the whole path in one command

```powershell
cd "C:\Users\jamabube\Downloads\Vehicle Access Monitoring System\vams-webapp"
php artisan rfid:doctor
```

Read-only — writes nothing, posts nothing. It reports on the reader's network path, whether the reader speaks the MM protocol (and what tags it currently sees), whether the ingestion API is up, and whether the listener's credentials match a registered reader. **If this passes, everything below is optional** — `php artisan rfid:listen` replaces the manual RFIDDemo + `test-api-detection.ps1` steps entirely.

To collect EPCs without writing anything to the database:

```powershell
php artisan rfid:listen --dry-run
```

---

### Phase 1: Read EPC from Physical Reader

1. **Open RFIDDemo.exe:**
   ```
   C:\Users\jamabube\Desktop\Vehicle Access Monitoring System\rfid reader sdk\RFIDDemo3410(1)(1)\RFIDDemo.exe
   ```

2. **Connect to Reader:**
   - Network tab → IP: `192.168.1.116`, Port: `49152` (or whatever worked for you)
   - Click **"Connect"**

3. **Scan for Tags:**
   - Click **"Inventory"** button
   - Physical tags in range will appear in the list
   - **Copy one EPC value** (e.g., `E2000019760801234567890A`)

---

### Phase 2: Test API with Unknown Tag (Expected Failure)

**Purpose:** Verify the API rejects unknown tags correctly.

```powershell
cd "C:\Users\jamabube\Desktop\Vehicle Access Monitoring System"
powershell -ExecutionPolicy Bypass -File ".\rfid reader sdk\test-api-detection.ps1" -EPC "E2000019760801234567890A"
```

**Expected Output:**
```
✓ SUCCESS: HTTP 201

Response:
  Decision:       denied
  Denial Reason:  unknown_credential
  
✗ Tag was DENIED: unknown_credential

Next step: Register this EPC via web UI (RFID Tags → Create)
```

**What Happened:**
- ✅ API received the detection
- ✅ HMAC signature verified
- ✅ Nonce accepted (not replayed)
- ✅ Detection recorded in `rfid_detections` table
- ✅ Access denied (tag not in `rfid_tags` table)
- ❌ No `access_logs` entry created (denied detections don't create logs)

---

### Phase 3: Register RFID Tag in VAMS

> **The easy way — no terminal needed.** On **RFID Tags → Create** there is a **"Scan a tag"** panel. Click **Start scanning**, hold the tag in front of the reader, and it appears in the list within a couple of seconds — click it and the EPC field fills itself. Tags already registered are shown greyed out with their tag code, so you cannot accidentally add a duplicate.
>
> This needs the RFID listener running (it starts automatically at logon — see `NETWORK_CONFIG.md`), because the panel shows what the *listener* reported, not the reader directly. The manual steps below remain valid as a fallback.

1. **Login to Web UI:**
   - URL: `http://192.168.1.21:8000`
   - Email: `admin@vams.test`
   - Password: `password`

2. **Create RFID Tag:**
   - Navigate: **RFID Tags** → **Create New RFID Tag**
   - **EPC:** `E2000019760801234567890A` (paste from RFIDDemo)
   - **Tag Code:** (auto-generated, e.g., `TAG-00001`)
   - **Credential Type:** 
     - Choose `sticker` for permanent vehicle tags
     - Choose `card` for reusable visitor cards
   - **Status:** `active`
   - **Expiry Date:** Leave blank or set far future
   - Click **"Create RFID Tag"**

---

### Phase 4: Create Test Scenario

Choose one:

#### Option A: Test with Vehicle (Permanent Sticker)

1. **Create Employee:**
   - **Employees** → **Create** → Fill in details (name, position, etc.)
   - Employee code auto-generated (e.g., `EMP-00001`)

2. **Create Vehicle:**
   - **Vehicles** → **Create**
   - **Plate Number:** Any valid format (e.g., `ABC1234`)
   - **Owner Type:** `employee`
   - **Owner:** Select the employee you created
   - **Current State:** `outside`
   - Click **"Create Vehicle"**

3. **Assign RFID Tag to Vehicle:**
   - **RFID Assignments** → **Create**
   - **RFID Tag:** Select your tag (the EPC you registered)
   - **Assignment Type:** `vehicle`
   - **Vehicle:** Select your vehicle
   - **Assigned At:** Now
   - Leave **Released At** blank (permanent assignment)
   - Click **"Create Assignment"**

#### Option B: Test with Visitor (Reusable Card)

1. **Create Visitor:**
   - **Visitors** → **Create** → Fill in name, contact, ID type/number
   - Visitor code auto-generated (e.g., `VIS-00001`)

2. **Create Visitor Visit (Check-In):**
   - **Visitor Visits** → **Create**
   - **Visitor:** Select your visitor
   - **Purpose:** "Testing RFID system"
   - **Host Name:** Any employee name
   - **Valid From:** Now
   - **Valid Until:** Tomorrow
   - **RFID Card:** Select your card tag
   - **Vehicle:** Optional (leave blank for on-foot visitor)
   - Click **"Check In Visitor"**
   - This automatically creates the `rfid_assignments` entry

---

### Phase 5: Test API with Registered Tag (Expected Success)

**Purpose:** Verify the tag is now authorized and creates access logs.

```powershell
powershell -ExecutionPolicy Bypass -File ".\rfid reader sdk\test-api-detection.ps1" -EPC "E2000019760801234567890A"
```

**Expected Output:**
```
✓ SUCCESS: HTTP 201

Response:
  Decision:       authorized
  Direction:      entry
  
✓ Tag was AUTHORIZED! Check access_logs table for the entry.
```

**What Happened:**
- ✅ Tag found in `rfid_tags`
- ✅ Active `rfid_assignments` record found
- ✅ Vehicle/visit is active
- ✅ `access_logs` entry created with `direction=entry`
- ✅ Vehicle `current_state` toggled: `outside` → `inside`

---

### Phase 6: Test Exit Detection

Run the same command again immediately:

```powershell
powershell -ExecutionPolicy Bypass -File ".\rfid reader sdk\test-api-detection.ps1" -EPC "E2000019760801234567890A"
```

**Expected Output:**
```
✓ SUCCESS: HTTP 201

Response:
  Decision:       authorized
  Direction:      exit
```

**What Happened:**
- ✅ Vehicle was `inside`, now toggled to `outside`
- ✅ Direction changed from `entry` to `exit`
- ✅ New `access_logs` entry with `direction=exit`



---

### Phase 7: Test Debounce (Duplicate Detection)

Run the command **a third time within 5 seconds**:

```powershell
powershell -ExecutionPolicy Bypass -File ".\rfid reader sdk\test-api-detection.ps1" -EPC "E2000019760801234567890A"
```

**Expected Output:**
```
✓ SUCCESS: HTTP 201

Response:
  Is Duplicate:   true
  Decision:       null
  Direction:      null
```

**What Happened:**
- ✅ Detection recorded in `rfid_detections` with `is_duplicate=true`
- ✅ No new `access_logs` entry (debounced)
- ✅ Vehicle state unchanged

Wait 6+ seconds and run again — it will be authorized as a new `entry` detection.

---

## Verification & Troubleshooting

### Database Checks

**Check RFID Detections:**
```powershell
cd "C:\Users\jamabube\Desktop\Vehicle Access Monitoring System\vams-webapp"
php artisan tinker --execute="DB::table('rfid_detections')->latest()->limit(5)->get(['epc', 'is_duplicate', 'created_at'])"
```

**Check Access Logs:**
```powershell
php artisan tinker --execute="DB::table('access_logs')->latest()->limit(5)->get(['decision', 'direction', 'denial_reason', 'created_at'])"
```

**Check Vehicle State:**
```powershell
php artisan tinker --execute="DB::table('vehicles')->get(['plate_number', 'current_state', 'last_seen_at'])"
```

### Common Issues

**HTTP 401 - Authentication Failed**
- Verify credentials in `test-api-detection.ps1` match seeder output
- Check database: `SELECT api_key FROM rfid_readers WHERE ip_address='192.168.1.116'`

**HTTP 409 - Replayed Nonce**
- Same nonce used twice within 600 seconds
- Wait 60 seconds or restart Laravel server

**Tag Authorized but No access_logs Entry**
- Detection marked as duplicate (within 5s debounce window)
- Check `is_duplicate` field in response

**Vehicle State Not Toggling**
- Only authorized entries toggle state
- Check `access_logs.direction` field
- Manually reset via web UI if needed

---

---

## Phase 8: End-to-End with the Real Listener

Everything above simulates one detection by hand. This runs the real thing.

1. **Start the web server** (in one window): `start.bat`, or `php artisan serve`
2. **Start the listener** (in a second window):

   ```powershell
   php artisan rfid:listen
   ```

3. **Walk a tag past the reader.** Each read prints its outcome live:

   ```
   14:22:07  E2003411B802011383258566  ant=0 rssi=-55dBm  AUTHORIZED (entry)
   14:22:09  E2003411B802011383258566  ant=0 rssi=-55dBm  duplicate (debounced)
   14:22:31  E2003411B802011383258566  ant=0 rssi=-55dBm  AUTHORIZED (exit)
   ```

4. **Confirm in the database** using the same `php artisan tinker` checks listed below, or in the web UI.

Stop the listener with Ctrl+C.

---

## Next Steps

Once all tests pass, you're ready to:

1. ~~**Build Windows Listener Service**~~ — done: `php artisan rfid:listen` (see Phase 8)
2. **Tune the debounce windows for real traffic** - `RFID_LISTENER_DEBOUNCE_SECONDS` and `RFID_DEBOUNCE_SECONDS` must both exceed how long a vehicle lingers in the read zone, or a stationary vehicle gets toggled entry → exit
3. **Reserve the reader's IP** in the router's DHCP settings so `RFID_READER_HOST` stops going stale
4. **Run the listener automatically** - register it as a Windows service or Scheduled Task (run at startup, restart on failure) so it does not depend on someone keeping a console window open
5. **Deploy to production** - Switch to HTTPS endpoint, set `APP_ENV=production`
6. **Build dashboard** - Real-time access log display
7. **Add reporting** - Analytics and alerts for denied access

---

## Quick Reference

| Task | Command |
|------|---------|
| Diagnose the reader link | `php artisan rfid:doctor` |
| Watch tag reads, write nothing | `php artisan rfid:listen --dry-run` |
| Run the listener for real | `php artisan rfid:listen` |
| Start dev server | `php artisan serve --host=0.0.0.0 --port=8000` |
| Test API detection | `powershell -File ".\rfid reader sdk\test-api-detection.ps1" -EPC "YOUR_EPC"` |
| Check detections | `php artisan tinker --execute="DB::table('rfid_detections')->latest()->take(5)->get()"` |
| Check access logs | `php artisan tinker --execute="DB::table('access_logs')->latest()->take(5)->get()"` |
| Re-seed credentials | `php artisan db:seed --class=RfidReaderSeeder` |
| View live logs | `php artisan pail` |
