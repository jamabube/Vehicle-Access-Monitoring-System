@echo off
REM ============================================================
REM  VAMS - RFID Listener / Device Service
REM  Connects to the S4A UHF-202415 reader and forwards every tag
REM  read to the VAMS ingestion API.
REM
REM  Run start.bat FIRST (the web server must be up), then run this
REM  in a second window.
REM
REM  Usage:
REM    start-listener.bat              normal operation
REM    start-listener.bat --dry-run    show tag reads without posting them
REM    start-listener.bat check        run connectivity diagnostics only
REM ============================================================

setlocal EnableDelayedExpansion
title VAMS RFID Listener

set "VAMS_ROOT=%~dp0"
set "VAMS_APP_DIR=%VAMS_ROOT%vams-webapp"

if not exist "%VAMS_APP_DIR%\artisan" (
    echo [ERROR] Could not find "%VAMS_APP_DIR%\artisan".
    echo Make sure start-listener.bat stays at the workspace root next to the vams-webapp folder.
    pause
    exit /b 1
)

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
        echo [ERROR] PHP was not found on PATH or at C:\xampp\php\php.exe.
        pause
        exit /b 1
    )
)

if /i "%~1"=="check" (
    echo.
    echo Running RFID connectivity diagnostics...
    echo.
    php artisan rfid:doctor
    echo.
    pause
    exit /b 0
)

echo.
echo ============================================================
echo  Starting the VAMS RFID Listener
echo  Press Ctrl+C to stop.
echo ============================================================
echo.

php artisan rfid:listen %*

endlocal
