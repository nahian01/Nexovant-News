<#
.SYNOPSIS
    One-Click Full Platform Deployment to Cloudflare
.DESCRIPTION
    Builds the Vue 3 SPA frontend, deploys it to Cloudflare Pages,
    and deploys the Cloudflare Worker Edge Gateway.
#>

param(
    [string]$PagesProjectName = "nexovant-frontend",
    [switch]$SkipFrontendBuild
)

$ErrorActionPreference = "Stop"

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "     NEXOVANT FULL PLATFORM CLOUDFLARE DEPLOYMENT        " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

$rootDir = (Get-Item $PSScriptRoot).Parent.Parent.FullName
$frontendDir = Join-Path $rootDir "nexovant-frontend"
$workerDir = Join-Path $rootDir "cloudflare-worker"

# 1. Build and Deploy Frontend to Cloudflare Pages
if (-not $SkipFrontendBuild) {
    Write-Host "`n[1/3] Building Vue 3 Frontend SPA for Cloudflare Pages..." -ForegroundColor Yellow
    Set-Location $frontendDir
    npm run build
}

Write-Host "`n[2/3] Deploying Frontend to Cloudflare Pages..." -ForegroundColor Yellow
Set-Location $frontendDir
npx wrangler pages deploy dist --project-name=$PagesProjectName

# 2. Deploy Cloudflare Worker Edge Gateway
Write-Host "`n[3/3] Deploying Cloudflare Worker Edge Gateway..." -ForegroundColor Yellow
Set-Location $workerDir
npx wrangler deploy

Write-Host "`n==========================================================" -ForegroundColor Green
Write-Host " [SUCCESS] Nexovant platform deployed to Cloudflare! " -ForegroundColor Green
Write-Host "==========================================================" -ForegroundColor Green
Write-Host "Next steps:"
Write-Host "1. Configure your backend origin in cloudflare-worker/wrangler.json (BACKEND_API_URL)"
Write-Host "2. If using Cloudflare Tunnel, run: cloudflared tunnel run nexovant-tunnel"
Write-Host "3. To connect PostgreSQL with pooled edge queries, run: npx wrangler hyperdrive create nexovant-db"
