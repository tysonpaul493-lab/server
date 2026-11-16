@echo off
title LifeRoute Unified Backend Server
echo ======================================================================
echo    LifeRoute Emergency Dispatch - Unified Backend & WebSocket Server
echo    Serving: User App, Driver App, and Admin Command Center Website
echo ======================================================================
echo.

cd /d "%~dp0"

echo [1/4] Checking environment configuration (.env)...
if not exist .env (
    echo .env file not found. Creating from .env.example...
    copy .env.example .env
    php artisan key:generate
)

echo [2/4] Testing MySQL Database Connection...
php artisan migrate --force
echo Database migrated successfully.
echo.

echo [3/4] Starting Laravel WebSocket Daemon on port 8080...
start "LifeRoute WebSocket (8080)" cmd /k "php artisan websocket:serve --host=0.0.0.0 --port=8080 --internal-port=8081"

echo [4/4] Starting Laravel REST API Server on port 8000...
echo.
echo ======================================================================
echo   HTTP REST API:    http://localhost:8000
echo   Live WebSocket:   ws://localhost:8080
echo ======================================================================
echo.
php artisan serve --host=0.0.0.0 --port=8000
pause
