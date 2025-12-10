@echo off
cd /d "C:\xampp\htdocs\prs_be"

REM Start Reverb WebSocket Server
start "Reverb Server" cmd /k "php artisan reverb:start --host=0.0.0.0 --port=8080"
timeout /t 3 /nobreak >nul

REM Start Queue Worker
start "Queue Worker" cmd /k "php artisan queue:work"
