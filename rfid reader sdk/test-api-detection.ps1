<#
.SYNOPSIS
  Test the VAMS RFID ingestion API with a simulated tag detection.

.DESCRIPTION
  Simulates what the Windows Listener service will do: read an EPC from RFIDDemo,
  compute HMAC signature, and POST to the Laravel API.

.USAGE
  powershell -ExecutionPolicy Bypass -File .\test-api-detection.ps1 -EPC "E2000019760801234567890A"

.NOTES
  Update API_KEY and API_SECRET below with the values from the RfidReaderSeeder output.
#>

param(
    [Parameter(Mandatory=$true)]
    [string]$EPC,
    
    [string]$ApiUrl = "http://192.168.1.21:8000/api/rfid/detections",
    
    # REPLACE THESE with the values from: php artisan db:seed --class=RfidReaderSeeder
    [string]$ApiKey = "reader_WWkx671JWFHitwx0yofbLOx0milkxryJ",
    [string]$ApiSecret = "rmL1iEWbFvwY5SatWkLvFI6EYHFg2YPZwtt6QXyPOLqv8Ma8McTdCD8cJ3vA4LNd"
)

# Generate request metadata
$timestamp = [int][double]::Parse((Get-Date -UFormat %s))
$nonce = [guid]::NewGuid().ToString()
$eventUuid = [guid]::NewGuid().ToString()
$detectedAt = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")

# Build JSON body
$body = @{
    event_uuid = $eventUuid
    epc = $EPC
    rssi = -42
    antenna = "1"
    detected_at = $detectedAt
} | ConvertTo-Json -Compress

# Compute HMAC signature
$payload = "$ApiKey.$timestamp.$nonce.$body"
$hmac = New-Object System.Security.Cryptography.HMACSHA256
$hmac.Key = [System.Text.Encoding]::UTF8.GetBytes($ApiSecret)
$hash = $hmac.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($payload))
$signature = [System.BitConverter]::ToString($hash).Replace('-', '').ToLower()

# Build headers
$headers = @{
    "Content-Type" = "application/json"
    "X-Rfid-Api-Key" = $ApiKey
    "X-Rfid-Timestamp" = $timestamp
    "X-Rfid-Nonce" = $nonce
    "X-Rfid-Signature" = $signature
}

Write-Host "═══════════════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  Testing RFID Detection API" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""
Write-Host "  EPC:         $EPC" -ForegroundColor White
Write-Host "  Event UUID:  $eventUuid" -ForegroundColor White
Write-Host "  Timestamp:   $timestamp" -ForegroundColor White
Write-Host "  Nonce:       $nonce" -ForegroundColor White
Write-Host "  Signature:   $signature" -ForegroundColor DarkGray
Write-Host ""
Write-Host "  Posting to:  $ApiUrl" -ForegroundColor Yellow
Write-Host ""

try {
    $response = Invoke-WebRequest -Uri $ApiUrl -Method POST -Headers $headers -Body $body -UseBasicParsing
    
    $statusCode = $response.StatusCode
    $responseBody = $response.Content | ConvertFrom-Json
    
    Write-Host "✓ SUCCESS: HTTP $statusCode" -ForegroundColor Green
    Write-Host ""
    Write-Host "  Response:" -ForegroundColor Cyan
    Write-Host "    Event UUID:     $($responseBody.event_uuid)" -ForegroundColor White
    Write-Host "    Is Duplicate:   $($responseBody.is_duplicate)" -ForegroundColor White
    Write-Host "    Decision:       $($responseBody.decision)" -ForegroundColor $(if ($responseBody.decision -eq 'authorized') { 'Green' } else { 'Red' })
    Write-Host "    Denial Reason:  $($responseBody.denial_reason)" -ForegroundColor Yellow
    Write-Host "    Direction:      $($responseBody.direction)" -ForegroundColor White
    Write-Host ""
    
    if ($responseBody.decision -eq 'authorized') {
        Write-Host "✓ Tag was AUTHORIZED! Check access_logs table for the entry." -ForegroundColor Green
    } else {
        Write-Host "✗ Tag was DENIED: $($responseBody.denial_reason)" -ForegroundColor Red
        Write-Host ""
        Write-Host "  Common reasons:" -ForegroundColor Yellow
        Write-Host "    - unknown_credential: EPC not in rfid_tags table" -ForegroundColor Yellow
        Write-Host "    - unassigned_credential: Tag exists but has no active rfid_assignments" -ForegroundColor Yellow
        Write-Host "    - credential_inactive: Tag status is not 'active'" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "  Next step: Register this EPC via web UI (RFID Tags → Create)" -ForegroundColor Cyan
    }
    
} catch {
    $statusCode = $_.Exception.Response.StatusCode.Value__
    $errorBody = $_.Exception.Response.Content | ConvertFrom-Json -ErrorAction SilentlyContinue
    
    Write-Host "✗ FAILED: HTTP $statusCode" -ForegroundColor Red
    Write-Host ""
    
    if ($errorBody) {
        Write-Host "  Error Message: $($errorBody.message)" -ForegroundColor Red
        if ($errorBody.errors) {
            Write-Host "  Validation Errors:" -ForegroundColor Red
            $errorBody.errors.PSObject.Properties | ForEach-Object {
                Write-Host "    - $($_.Name): $($_.Value -join ', ')" -ForegroundColor Red
            }
        }
    } else {
        Write-Host "  Raw Error: $($_.Exception.Message)" -ForegroundColor Red
    }
    
    Write-Host ""
    Write-Host "  Troubleshooting:" -ForegroundColor Yellow
    Write-Host "    - 401: Check API_KEY and API_SECRET match the database" -ForegroundColor Yellow
    Write-Host "    - 409: Nonce was replayed (wait 60s or use different nonce)" -ForegroundColor Yellow
    Write-Host "    - 422: Invalid request body format" -ForegroundColor Yellow
    Write-Host "    - 500: Laravel app error (check storage/logs/laravel.log)" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "═══════════════════════════════════════════════════════════════════════" -ForegroundColor Cyan
