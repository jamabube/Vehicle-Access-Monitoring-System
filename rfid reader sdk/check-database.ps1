<#
.SYNOPSIS
  Quick database checker for RFID detections and access logs.

.USAGE
  powershell -ExecutionPolicy Bypass -File .\check-database.ps1
#>

param(
    [string]$DBHost = "127.0.0.1",
    [string]$DBName = "vams_laravel",
    [string]$DBUser = "root",
    [string]$DBPass = ""
)

Write-Host "═══════════════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  VAMS Database Quick Check" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

try {
    # Load MySQL .NET connector (if available)
    Add-Type -Path "C:\xampp\mysql\bin\MySql.Data.dll" -ErrorAction Stop
    
    $connString = "Server=$DBHost;Database=$DBName;Uid=$DBUser;Pwd=$DBPass;"
    $conn = New-Object MySql.Data.MySqlClient.MySqlConnection($connString)
    $conn.Open()
    
    # Check RFID Readers
    $cmd = $conn.CreateCommand()
    $cmd.CommandText = "SELECT device_code, device_name, ip_address, status FROM rfid_readers"
    $reader = $cmd.ExecuteReader()
    
    Write-Host "RFID Readers:" -ForegroundColor Yellow
    while ($reader.Read()) {
        Write-Host ("  {0} - {1} ({2}) - {3}" -f $reader["device_code"], $reader["device_name"], $reader["ip_address"], $reader["status"]) -ForegroundColor White
    }
    $reader.Close()
    Write-Host ""
    
    # Check RFID Tags
    $cmd.CommandText = "SELECT tag_code, epc, credential_type, status FROM rfid_tags LIMIT 5"
    $reader = $cmd.ExecuteReader()
    
    Write-Host "RFID Tags (last 5):" -ForegroundColor Yellow
    $count = 0
    while ($reader.Read()) {
        Write-Host ("  {0} - {1} ({2}) - {3}" -f $reader["tag_code"], $reader["epc"], $reader["credential_type"], $reader["status"]) -ForegroundColor White
        $count++
    }
    if ($count -eq 0) {
        Write-Host "  (none yet - create via web UI)" -ForegroundColor DarkGray
    }
    $reader.Close()
    Write-Host ""
    
    # Check Recent Detections
    $cmd.CommandText = "SELECT event_uuid, epc, is_duplicate, created_at FROM rfid_detections ORDER BY created_at DESC LIMIT 5"
    $reader = $cmd.ExecuteReader()
    
    Write-Host "Recent RFID Detections (last 5):" -ForegroundColor Yellow
    $count = 0
    while ($reader.Read()) {
        $dup = if ($reader["is_duplicate"]) { "(DUPLICATE)" } else { "" }
        Write-Host ("  {0} - {1} {2}" -f $reader["epc"], $reader["created_at"], $dup) -ForegroundColor White
        $count++
    }
    if ($count -eq 0) {
        Write-Host "  (none yet - run test-api-detection.ps1 first)" -ForegroundColor DarkGray
    }
    $reader.Close()
    Write-Host ""
    
    # Check Access Logs
    $cmd.CommandText = "SELECT decision, direction, denial_reason, created_at FROM access_logs ORDER BY created_at DESC LIMIT 5"
    $reader = $cmd.ExecuteReader()
    
    Write-Host "Recent Access Logs (last 5):" -ForegroundColor Yellow
    $count = 0
    while ($reader.Read()) {
        $color = if ($reader["decision"] -eq "authorized") { "Green" } else { "Red" }
        $dir = $reader["direction"]
        $reason = if ($reader["denial_reason"]) { "($($reader["denial_reason"]))" } else { "" }
        Write-Host ("  {0} {1} {2} - {3}" -f $reader["decision"], $dir, $reason, $reader["created_at"]) -ForegroundColor $color
        $count++
    }
    if ($count -eq 0) {
        Write-Host "  (none yet - detections must be authorized to create logs)" -ForegroundColor DarkGray
    }
    $reader.Close()
    
    $conn.Close()
    
} catch {
    Write-Host "Could not connect to database or MySQL.Data.dll not found." -ForegroundColor Red
    Write-Host "Run this instead from the vams-webapp folder:" -ForegroundColor Yellow
    Write-Host "  php artisan tinker --execute='DB::table(\"rfid_detections\")->latest()->limit(5)->get()'" -ForegroundColor Cyan
}

Write-Host ""
Write-Host "═══════════════════════════════════════════════════════════════════════" -ForegroundColor Cyan
