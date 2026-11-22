<#
.SYNOPSIS
    Automated PostgreSQL Database Setup, Migration & Cloudflare Hyperdrive Connector
.DESCRIPTION
    Verifies PostgreSQL connection, applies schema migrations safely, seeds initial data,
    and formats connection strings for Cloudflare Hyperdrive / Cloudflare Worker.
#>

param(
    [string]$DbHost = "127.0.0.1",
    [string]$DbPort = "5432",
    [string]$DbName = "nexovant_production",
    [string]$DbUser = "nexovant_app",
    [string]$DbPassword = "nexovant_secret",
    [switch]$Seed
)

$ErrorActionPreference = "Stop"

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "  NEXOVANT PRODUCTION DATABASE SETUP & CLOUDFLARE BINDING" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

$backendDir = Join-Path $PSScriptRoot "..\..\nexovant-backend"
Set-Location $backendDir

Write-Host "--> Checking PHP runtime..." -ForegroundColor Yellow
php -v | Select-Object -First 1

Write-Host "--> Configuring environment variables for PostgreSQL..." -ForegroundColor Yellow
$env:DB_CONNECTION = "pgsql"
$env:DB_HOST = $DbHost
$env:DB_PORT = $DbPort
$env:DB_DATABASE = $DbName
$env:DB_USERNAME = $DbUser
$env:DB_PASSWORD = $DbPassword

Write-Host "--> Testing database connection & running migrations..." -ForegroundColor Yellow
php artisan migrate --force

if ($Seed) {
    Write-Host "--> Running database seeders..." -ForegroundColor Yellow
    php artisan db:seed --force
}

Write-Host "--> Optimizing Laravel configuration caches..." -ForegroundColor Yellow
php artisan config:cache
php artisan route:cache

Write-Host "`n[SUCCESS] Database setup and migrations completed!" -ForegroundColor Green
Write-Host "`n=== Cloudflare Hyperdrive Configuration ===" -ForegroundColor Cyan
Write-Host "To connect your Cloudflare Worker directly to this PostgreSQL database with connection pooling:"
Write-Host "Run the following Wrangler command:" -ForegroundColor Yellow
$connString = "postgresql://${DbUser}:${DbPassword}@${DbHost}:${DbPort}/${DbName}?sslmode=require"
Write-Host "npx wrangler hyperdrive create nexovant-db --connection-string=`"$connString`"" -ForegroundColor White
Write-Host "Then bind HYPERDRIVE in your cloudflare-worker/wrangler.json:" -ForegroundColor Yellow
Write-Host @"
  "hyperdrive": [
    {
      "binding": "HYPERDRIVE",
      "id": "<HYPERDRIVE_ID_FROM_ABOVE>"
    }
  ]
"@ -ForegroundColor Gray
