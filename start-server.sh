#!/usr/bin/env bash
# LifeRoute Unified Server Startup Script for Linux / Ubuntu VPS

echo "======================================================================"
echo "   LifeRoute Emergency Dispatch - Unified Backend & WebSocket Server"
echo "   Serving: User App, Driver App, and Admin Command Center Website"
echo "======================================================================"

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd "$DIR"

if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
    php artisan key:generate
fi

echo "Running Database Migrations..."
php artisan migrate --force

echo "Starting WebSocket Daemon on port 8080..."
php artisan websocket:serve --host=0.0.0.0 --port=8080 --internal-port=8081 &
WS_PID=$!
echo "WebSocket started (PID: $WS_PID)"

echo "Starting HTTP REST API on port 8000..."
echo "HTTP REST API:  http://0.0.0.0:8000"
echo "Live WebSocket: ws://0.0.0.0:8080"
php artisan serve --host=0.0.0.0 --port=8000
