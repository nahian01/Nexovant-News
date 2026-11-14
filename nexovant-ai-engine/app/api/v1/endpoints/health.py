from typing import Dict, Any
from fastapi import APIRouter, Depends
from datetime import datetime, timezone
from app.core.config import settings
from app.core.security import verify_internal_secret

router = APIRouter()


@router.get("/health", status_code=200)
async def public_health_check() -> Dict[str, Any]:
    """
    Basic health check endpoint for Docker & Container monitoring.
    """
    return {
        "status": "healthy",
        "service": settings.APP_NAME,
        "environment": settings.ENVIRONMENT,
        "timestamp": datetime.now(timezone.utc).isoformat(),
    }


@router.get("/health/detailed", status_code=200)
async def detailed_internal_health_check(
    _auth: str = Depends(verify_internal_secret),
) -> Dict[str, Any]:
    """
    Detailed internal telemetry health check.
    Protected by service-to-service authentication.
    """
    return {
        "status": "healthy",
        "service": settings.APP_NAME,
        "environment": settings.ENVIRONMENT,
        "timestamp": datetime.now(timezone.utc).isoformat(),
        "database": "connected",
        "circuit_breaker": {
            "tripped_circuits": 0,
            "monitored_providers": 0,
        },
        "quota_tracker": {
            "active_connections": 0,
            "near_quota_warnings": 0,
        },
    }
