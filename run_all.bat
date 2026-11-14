@echo off
title Nexovant Platform Orchestrator
echo ======================================================================
echo           NEXOVANT - GLOBAL EXECUTIVE INTELLIGENCE PLATFORM
echo ======================================================================
echo Starting all 3 decoupled platform services...
echo.

echo [1/3] Starting Laravel 12 Backend API (Port 8000)...
start "Nexovant Backend API" cmd /k "cd /d "%~dp0nexovant-backend" && php artisan serve --host=127.0.0.1 --port=8000"

timeout /t 2 >nul

echo [2/3] Starting Python Cognitive AI Engine (Port 8001)...
start "Nexovant AI Engine" cmd /k "cd /d "%~dp0nexovant-ai-engine" && python -m uvicorn app.main:app --host 127.0.0.1 --port 8001"

timeout /t 2 >nul

echo [3/3] Starting Vue 3 Vite Frontend SPA (Port 3000)...
start "Nexovant Frontend" cmd /k "cd /d "%~dp0nexovant-frontend" && npm run dev -- --host 127.0.0.1 --port 3000"

timeout /t 3 >nul

echo.
echo ======================================================================
echo All platform services running!
echo - Web Application:   http://127.0.0.1:3000
echo - Backend API:       http://127.0.0.1:8000/api/v1/health
echo - AI Engine Internal:http://127.0.0.1:8001/api/internal/v1/health
echo ======================================================================
start http://127.0.0.1:3000
exit
