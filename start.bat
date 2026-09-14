@echo off
REM ============================================================
REM  VAMS - Vehicle Access Monitoring System
REM  One-click dev launcher for vams-webapp (Laravel 12 + Vite)
REM ============================================================

setlocal EnableDelayedExpansion
title VAMS Dev Server

set "VAMS_ROOT=%~dp0"
set "VAMS_APP_DIR=%VAMS_ROOT%vams-webapp"
set "VAMS_TOOLS_DIR=%VAMS_ROOT%tools"

if not exist "%VAMS_APP_DIR%\artisan" (
    echo [ERROR] Could not find "%VAMS_APP_DIR%\artisan".
    echo Make sure start.bat stays at the workspace root next to the vams-webapp folder.
    pause
    exit /b 1
)

cd /d "%VAMS_APP_DIR%"

REM --- Clear stray APP_*/DB_* env vars that can silently override .env ---
REM (see context\RULES.md - "Environment & Local Dev Gotchas")
REM NOTE: this script deliberately uses VAMS_*-prefixed variable names above
REM so this cleanup loop (which targets APP_*/DB_* prefixes) cannot wipe
REM out this script's own working variables.
for /f "delims== tokens=1" %%v in ('set APP_ 2^>nul') do set "%%v="
for /f "delims== tokens=1" %%v in ('set DB_ 2^>nul') do set "%%v="

REM --- Locate PHP: use PATH if present, otherwise fall back to the bundled ---
REM --- XAMPP install and prepend it to PATH for this session only, so    ---
REM --- both this script and any "php ..." calls composer runs internally ---
REM --- (e.g. via "composer dev") resolve correctly.                      ---
where php >nul 2>nul
if errorlevel 1 (
    if exist "C:\xampp\php\php.exe" (
        set "PATH=C:\xampp\php;%PATH%"
    ) else (
        echo [ERROR] PHP was not found on PATH or at C:\xampp\php\php.exe.
        echo Install PHP 8.2+ / XAMPP, or add php.exe to PATH.
        pause
        exit /b 1
    )
)

REM --- Locate Composer: use PATH if present, otherwise fall back to the ---
REM --- workspace-local tools\composer.phar bootstrap (run via php).     ---
set "COMPOSER_CMD=composer"
where composer >nul 2>nul
if errorlevel 1 (
    if exist "%VAMS_TOOLS_DIR%\composer.phar" (
        set "COMPOSER_CMD=php "%VAMS_TOOLS_DIR%\composer.phar""
    ) else (
        echo [ERROR] Composer was not found on PATH or at "%VAMS_TOOLS_DIR%\composer.phar".
        pause
        exit /b 1
    )
)

where npm >nul 2>nul
if errorlevel 1 (
    echo [ERROR] "npm" was not found on PATH. Install Node.js or add it to PATH.
    pause
    exit /b 1
)

echo Using Composer: %COMPOSER_CMD%
echo.

REM --- First-run bootstrap: PHP deps ---
if not exist "%VAMS_APP_DIR%\vendor\autoload.php" (
    echo [SETUP] vendor\ not found - running "composer install"...
    call %COMPOSER_CMD% install
    if errorlevel 1 (
        echo [ERROR] composer install failed.
        pause
        exit /b 1
    )
)

REM --- First-run bootstrap: .env + app key ---
if not exist "%VAMS_APP_DIR%\.env" (
    echo [SETUP] .env not found - copying .env.example...
    copy /y "%VAMS_APP_DIR%\.env.example" "%VAMS_APP_DIR%\.env" >nul
    echo [SETUP] Generating application key...
    call php artisan key:generate
)

REM --- First-run bootstrap: JS deps ---
if not exist "%VAMS_APP_DIR%\node_modules" (
    echo [SETUP] node_modules\ not found - running "npm install"...
    call npm install
    if errorlevel 1 (
        echo [ERROR] npm install failed.
        pause
        exit /b 1
    )
)

REM --- Remove any stale Vite "hot" file -------------------------------------
REM While the Vite dev server runs it writes public\hot, and Blade then loads
REM every stylesheet from that dev server instead of the built files. If Vite
REM dies (or moves to another port) without cleaning up, public\hot is left
REM pointing at nothing and EVERY page renders with no CSS at all - a blank or
REM giant-logo screen that looks like the app is broken. This launcher serves
REM the built assets instead, so that failure cannot happen. Use "composer dev"
REM when you actually want hot-reloading while editing CSS/JS.
if exist "%VAMS_APP_DIR%\public\hot" (
    echo [SETUP] Removing stale Vite hot-reload marker...
    del /q "%VAMS_APP_DIR%\public\hot"
)

REM --- Make sure compiled CSS/JS exist --------------------------------------
if not exist "%VAMS_APP_DIR%\public\build\manifest.json" (
    echo [SETUP] Compiled assets not found - running "npm run build"...
    call npm run build
    if errorlevel 1 (
        echo [ERROR] npm run build failed.
        pause
        exit /b 1
    )
)

echo.
echo ============================================================
echo  Starting VAMS
echo    - web server + queue (serving compiled assets)
echo    - Reverb WebSocket server (real-time dashboard)
echo    - RFID listener (opens in its own window)
echo  App URL: http://127.0.0.1:8000
echo  Press Ctrl+C here to stop the web server.
echo ============================================================
echo.

REM Open the browser shortly after launch (non-blocking, gives the server time to boot)
start "" cmd /c "timeout /t 4 >nul & start "" http://127.0.0.1:8000"

REM --- Start the RFID listener in its own window ---
REM Without this, tags are read by the hardware and go nowhere: the reader's
REM lights come on but nothing reaches the website. The wrapper waits for the
REM web server to come up, restarts itself if it stops, and backs off if a
REM listener is already running (e.g. started by the logon Scheduled Task).
if exist "%VAMS_ROOT%start-listener-auto.bat" (
    start "VAMS RFID Listener" cmd /c ""%VAMS_ROOT%start-listener-auto.bat" 10"
) else (
    echo [WARN] start-listener-auto.bat not found - the RFID listener was NOT started.
)

REM Runs: php artisan serve + queue:listen + reverb (concurrently), per the
REM composer.json "start" script. Deliberately NOT "composer dev": that also
REM runs the Vite dev server, and if Vite dies or changes port the leftover
REM public\hot file makes every page load with no CSS. Serving the built
REM assets removes that failure mode entirely. Use "composer dev" by hand when
REM you want hot-reloading while editing CSS/JS.
call %COMPOSER_CMD% start

endlocal
