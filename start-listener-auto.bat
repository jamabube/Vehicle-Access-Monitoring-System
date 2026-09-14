@echo off
REM ============================================================
REM  VAMS - RFID Listener, unattended wrapper
REM
REM  Launched two ways, and safe under both:
REM    - by start.bat, alongside the web server
REM    - by the "VAMS RFID Listener" Scheduled Task at logon
REM
REM  Keeps php artisan rfid:listen running, restarts it if it ever
REM  exits, and shows reads live while also appending them to
REM  logs\rfid-listener.log.
REM
REM  Optional first argument: seconds to wait before starting
REM  (start.bat uses this to let the web server come up first).
REM
REM  Close this window to stop the listener.
REM ============================================================

setlocal EnableDelayedExpansion
title VAMS RFID Listener

set "VAMS_ROOT=%~dp0"
set "VAMS_APP_DIR=%VAMS_ROOT%vams-webapp"
set "VAMS_LOG_DIR=%VAMS_ROOT%logs"

if not exist "%VAMS_APP_DIR%\artisan" (
    echo [ERROR] Could not find "%VAMS_APP_DIR%\artisan".
    pause
    exit /b 1
)

REM --- Optional startup delay, so the web server is listening first ---
if not "%~1"=="" (
    echo Waiting %~1 seconds for the web server to start...
    set /a VAMS_WAIT=%~1+1
    ping -n !VAMS_WAIT! 127.0.0.1 >nul
)

REM --- Only one listener at a time. The reader's network module accepts ---
REM --- very few TCP sockets, so a second listener would fight the first ---
REM --- for it. Whichever launcher gets here second simply backs off.    ---
powershell -NoProfile -Command "if (Get-CimInstance Win32_Process -Filter \"Name='php.exe'\" -ErrorAction SilentlyContinue | Where-Object { $_.CommandLine -like '*rfid:listen*' }) { exit 1 } else { exit 0 }"
if errorlevel 1 (
    echo.
    echo The RFID listener is already running in another window.
    echo Nothing to do - closing in 5 seconds.
    ping -n 6 127.0.0.1 >nul
    exit /b 0
)

if not exist "%VAMS_LOG_DIR%" mkdir "%VAMS_LOG_DIR%"
set "VAMS_LOG=%VAMS_LOG_DIR%\rfid-listener.log"

cd /d "%VAMS_APP_DIR%"

REM --- Clear stray APP_*/DB_* env vars that can silently override .env ---
REM (see context\RULES.md - "Environment & Local Dev Gotchas")
for /f "delims== tokens=1" %%v in ('set APP_ 2^>nul') do set "%%v="
for /f "delims== tokens=1" %%v in ('set DB_ 2^>nul') do set "%%v="

where php >nul 2>nul
if errorlevel 1 (
    if exist "C:\xampp\php\php.exe" (
        set "PATH=C:\xampp\php;%PATH%"
    ) else (
        echo [ERROR] PHP was not found on PATH or at C:\xampp\php\php.exe. >> "%VAMS_LOG%"
        exit /b 1
    )
)

REM The listener already reconnects to the reader on its own, and retries a
REM failed API post on the tag's next read. This loop is the outer safety net:
REM it covers the listener exiting entirely (fatal error, machine waking from
REM sleep with the socket gone, PHP crash).
:listen_loop
echo. >> "%VAMS_LOG%"
echo ============================================================ >> "%VAMS_LOG%"
echo [%date% %time%] starting rfid:listen >> "%VAMS_LOG%"
echo ============================================================ >> "%VAMS_LOG%"

REM Tee, so this window shows tag reads live AND the log keeps the history.
REM Not Tee-Object: on Windows PowerShell 5.1 it writes UTF-16, which makes the
REM log unreadable to everything else. Add-Content -Encoding UTF8 keeps it plain.
powershell -NoProfile -Command "& { php artisan rfid:listen 2>&1 | ForEach-Object { Write-Host $_; Add-Content -LiteralPath '%VAMS_LOG%' -Value $_ -Encoding UTF8 } }"

echo.
echo [%date% %time%] Listener stopped - restarting in 15 seconds. Close this window to stop for good.
echo [%date% %time%] listener exited - restarting in 15 seconds >> "%VAMS_LOG%"
ping -n 16 127.0.0.1 >nul
goto listen_loop
