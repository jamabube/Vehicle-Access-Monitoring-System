<#
.SYNOPSIS
  Test TCP connection to the S4A UHF RFID reader control port.

.DESCRIPTION
  Attempts to open a TCP socket to the reader's control port (49152) to verify
  the reader is listening and accepting connections before using RFIDDemo.exe.

.USAGE
  powershell -ExecutionPolicy Bypass -File .\test-reader-tcp.ps1
  powershell -ExecutionPolicy Bypass -File .\test-reader-tcp.ps1 -ReaderIP "192.168.1.116" -Port 49152
#>

param(
    [string]$ReaderIP = "192.168.1.116",
    [int]$Port = 49152,
    [int]$TimeoutSeconds = 5
)

Write-Host "Testing TCP connection to $ReaderIP`:$Port ..." -ForegroundColor Cyan

try {
    $tcpClient = New-Object System.Net.Sockets.TcpClient
    $connection = $tcpClient.BeginConnect($ReaderIP, $Port, $null, $null)
    $wait = $connection.AsyncWaitHandle.WaitOne($TimeoutSeconds * 1000, $false)
    
    if ($wait) {
        try {
            $tcpClient.EndConnect($connection)
            Write-Host "SUCCESS: TCP port $Port is OPEN and accepting connections!" -ForegroundColor Green
            Write-Host ""
            Write-Host "Next steps:" -ForegroundColor Cyan
            Write-Host "  1. Open: rfid reader sdk\RFIDDemo3410(1)(1)\RFIDDemo.exe" -ForegroundColor White
            Write-Host "  2. Click the Network tab at the top" -ForegroundColor White
            Write-Host "  3. Enter IP: $ReaderIP, Port: $Port" -ForegroundColor White
            Write-Host "  4. Click Connect button" -ForegroundColor White
            Write-Host "  5. Click Inventory to scan for RFID tags" -ForegroundColor White
            Write-Host ""
            Write-Host "If you see tags appear, the reader is working correctly!" -ForegroundColor Green
            $tcpClient.Close()
        } catch {
            Write-Host "Port is reachable but connection handshake failed: $($_.Exception.Message)" -ForegroundColor Red
        }
    } else {
        Write-Host "TIMEOUT: No response on port $Port after $TimeoutSeconds seconds" -ForegroundColor Red
        Write-Host ""
        Write-Host "Possible causes:" -ForegroundColor Yellow
        Write-Host "  - Reader is not powered on (but ping worked, so unlikely)" -ForegroundColor Yellow
        Write-Host "  - Reader is using a different control port" -ForegroundColor Yellow
        Write-Host "  - Reader firmware requires different initialization" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "Try RFIDDemo.exe anyway - it may have better protocol negotiation." -ForegroundColor Cyan
        $tcpClient.Close()
    }
} catch {
    Write-Host "CONNECTION FAILED: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Write-Host "The reader at $ReaderIP is responding to ping but not accepting TCP on port $Port." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Troubleshooting:" -ForegroundColor Yellow
    Write-Host "  1. Verify the reader actual control port (check any DIP switches or labels)" -ForegroundColor White
    Write-Host "  2. Try RFIDDemo.exe Search Network Device button" -ForegroundColor White
    Write-Host "  3. Check if the reader has a web interface at http://$ReaderIP" -ForegroundColor White
    Write-Host "  4. Consult the reader manual for the correct port number" -ForegroundColor White
} finally {
    if ($tcpClient) {
        $tcpClient.Close()
    }
}
