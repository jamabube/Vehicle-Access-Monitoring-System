<#
.SYNOPSIS
  Discovers S4A UHF-202415 (and compatible "AD-" series) UHF RFID readers on the local
  network by broadcasting the vendor's UDP discovery magic string ("HF-A11ASSISTHREAD")
  on port 48899, exactly like the bundled RFIDDemo.exe "Search Network Device" button does.

.DESCRIPTION
  This is a read-only network probe — it does NOT modify any reader settings. It only
  listens for replies to the discovery broadcast and prints whatever the reader(s) send
  back (typically IP / subnet mask / MAC address in a comma or colon separated string).

.USAGE
  powershell -ExecutionPolicy Bypass -File .\discover-reader.ps1
  powershell -ExecutionPolicy Bypass -File .\discover-reader.ps1 -TimeoutSeconds 5

.NOTES
  - Run this from the same PC that is on the same LAN/subnet as the reader (the reader
    must already be powered on and connected via Ethernet, or already joined to your WiFi).
  - If nothing responds, double-check the reader is powered, its link light is on, and
    that this PC's firewall allows outbound/inbound UDP on port 48899 (Windows Defender
    Firewall may prompt the first time you run this — allow it for Private networks).
#>

param(
    [int]$TimeoutSeconds = 4,
    [int]$Port = 48899
)

$magic = [System.Text.Encoding]::ASCII.GetBytes("HF-A11ASSISTHREAD")

$udp = New-Object System.Net.Sockets.UdpClient
$udp.EnableBroadcast = $true
$udp.Client.SetSocketOption([System.Net.Sockets.SocketOptionLevel]::Socket, [System.Net.Sockets.SocketOptionName]::ReuseAddress, $true)
$localEndpoint = New-Object System.Net.IPEndPoint ([System.Net.IPAddress]::Any, 0)
$udp.Client.Bind($localEndpoint)
$udp.Client.ReceiveTimeout = $TimeoutSeconds * 1000

$broadcastEndpoint = New-Object System.Net.IPEndPoint ([System.Net.IPAddress]::Broadcast, $Port)

Write-Host "Broadcasting discovery packet ('HF-A11ASSISTHREAD') to 255.255.255.255:$Port ..." -ForegroundColor Cyan
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
        # timeout hit, loop will exit on next check
        break
    }
}

$udp.Close()

if ($found.Count -eq 0) {
    Write-Host ""
    Write-Host "No reader responded within $TimeoutSeconds seconds." -ForegroundColor Yellow
    Write-Host "Checklist:" -ForegroundColor Yellow
    Write-Host "  1. Is the reader powered on (check its power LED)?"
    Write-Host "  2. Is it connected via Ethernet to the SAME router/switch as this PC (or joined the same WiFi)?"
    Write-Host "  3. Run 'ipconfig' here and confirm this PC has an IP on the same subnet you expect the reader to use."
    Write-Host "  4. Some readers ship with a fixed default static IP (commonly 192.168.1.100) instead of DHCP"
    Write-Host "     -- if your PC is not already on the 192.168.1.0/24 subnet, temporarily set a static IP in"
    Write-Host "     that range on this PCs network adapter and re-run this script."
    Write-Host "  5. Try running the bundled RFIDDemo.exe instead and use its Search Network Device button,"
    Write-Host "     which uses the identical discovery packet and may render replies more reliably."
} else {
    Write-Host ""
    Write-Host "Found $($found.Count) responding device(s). Use the ReplyFromIP above to connect in RFIDDemo.exe" -ForegroundColor Cyan
    Write-Host "(Network tab -> enter IP -> Connect) or in any custom TCP client you build later." -ForegroundColor Cyan
}
