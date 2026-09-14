<#
.SYNOPSIS
  Discovers S4A UHF RFID readers on a SPECIFIC network adapter (direct Ethernet connection).

.DESCRIPTION
  Modified version that binds to a specific local IP instead of broadcasting on all adapters.
  Use this when the reader is connected via direct Ethernet cable (no router/switch).

.USAGE
  powershell -ExecutionPolicy Bypass -File .\discover-reader-direct.ps1 -LocalIP "192.168.1.100"
  
.NOTES
  This script binds to your PC's Ethernet adapter IP (192.168.1.100) and broadcasts on that
  subnet, bypassing the default route which might go out the Wi-Fi adapter instead.
#>

param(
    [Parameter(Mandatory=$true)]
    [string]$LocalIP,  # Your PC's IP on the Ethernet adapter, e.g. "192.168.1.100"
    
    [int]$TimeoutSeconds = 4,
    [int]$Port = 48899
)

$magic = [System.Text.Encoding]::ASCII.GetBytes("HF-A11ASSISTHREAD")

$udp = New-Object System.Net.Sockets.UdpClient
$udp.EnableBroadcast = $true
$udp.Client.SetSocketOption([System.Net.Sockets.SocketOptionLevel]::Socket, [System.Net.Sockets.SocketOptionName]::ReuseAddress, $true)

# Bind to the specific local adapter IP instead of Any
$localEndpoint = New-Object System.Net.IPEndPoint ([System.Net.IPAddress]::Parse($LocalIP), 0)
$udp.Client.Bind($localEndpoint)
$udp.Client.ReceiveTimeout = $TimeoutSeconds * 1000

# Broadcast to 255.255.255.255 (will go out the bound adapter)
$broadcastEndpoint = New-Object System.Net.IPEndPoint ([System.Net.IPAddress]::Broadcast, $Port)

Write-Host "Broadcasting discovery packet from $LocalIP to 255.255.255.255:$Port ..." -ForegroundColor Cyan
$udp.Send($magic, $magic.Length, $broadcastEndpoint) | Out-Null

$found = @()
$deadline = (Get-Date).AddSeconds($TimeoutSeconds)

while ((Get-Date) -lt $deadline) {
    try {
        $remoteEndpoint = New-Object System.Net.IPEndPoint ([System.Net.IPAddress]::Any, 0)
        $bytes = $udp.Receive([ref]$remoteEndpoint)
        $text = [System.Text.Encoding]::ASCII.GetString($bytes)
        $entry = [PSCustomObject]@{
            ReplyFromIP = $remoteEndpoint.Address.ToString()
            ReplyFromPort = $remoteEndpoint.Port
            RawResponse = $text
        }
        $found += $entry
        Write-Host ("Reply from {0}:{1} -> {2}" -f $entry.ReplyFromIP, $entry.ReplyFromPort, $entry.RawResponse) -ForegroundColor Green
    } catch [System.Net.Sockets.SocketException] {
        break
    }
}

$udp.Close()

if ($found.Count -eq 0) {
    Write-Host ""
    Write-Host "No reader responded within $TimeoutSeconds seconds on the $LocalIP adapter." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Since you have a direct Ethernet connection, the reader might not support UDP discovery" -ForegroundColor Yellow
    Write-Host "or may require configuration via RFIDDemo.exe. Try these steps:" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  1. Open RFIDDemo.exe (Network tab)" -ForegroundColor Cyan
    Write-Host "  2. Manually enter IP 192.168.1.116, Port 49152" -ForegroundColor Cyan
    Write-Host "  3. Click Connect (bypasses UDP discovery)" -ForegroundColor Cyan
    Write-Host "  4. If it connects, click Inventory to test tag reads" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "If RFIDDemo can't connect either, the reader might be on a different static IP." -ForegroundColor Yellow
    Write-Host "Common factory defaults: 192.168.1.100, 192.168.0.100, 192.168.1.200" -ForegroundColor Yellow
} else {
    Write-Host ""
    Write-Host "Found $($found.Count) responding device(s)!" -ForegroundColor Cyan
}
