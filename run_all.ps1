# Nexovant Platform Orchestrator
Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host "          NEXOVANT - GLOBAL EXECUTIVE INTELLIGENCE PLATFORM" -ForegroundColor Cyan
Write-Host "======================================================================" -ForegroundColor Cyan

$root = $PSScriptRoot

Write-Host "[1/3] Starting Laravel 12 Backend API on http://127.0.0.1:8000..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$root\nexovant-backend'; php artisan serve --host=127.0.0.1 --port=8000"

Start-Sleep -Seconds 2

Write-Host "[2/3] Starting Python Cognitive AI Engine on http://127.0.0.1:8001..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$root\nexovant-ai-engine'; python -m uvicorn app.main:app --host 127.0.0.1 --port 8001"

Start-Sleep -Seconds 2

Write-Host "[3/3] Starting Vue 3 Vite Frontend on http://127.0.0.1:3000..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$root\nexovant-frontend'; npm run dev -- --host 127.0.0.1 --port 3000"

Start-Sleep -Seconds 3

Write-Host "`nAll 3 platform services launched successfully!" -ForegroundColor Green
Write-Host "Opening http://127.0.0.1:3000 in your browser..." -ForegroundColor Green
Start-Process "http://127.0.0.1:3000"
