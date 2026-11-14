from fastapi import Security, HTTPException, status
from fastapi.security import APIKeyHeader
from app.core.config import settings

api_key_header = APIKeyHeader(name="X-Internal-Service-Secret", auto_error=False)


async def verify_internal_secret(
    header_secret: str = Security(api_key_header),
) -> str:
    """
    Enforces internal service-to-service authentication.
    Prevents unauthorized external callers from executing AI or RAG endpoints.
    """
    if not header_secret or header_secret != settings.INTERNAL_SERVICE_SECRET:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Forbidden: Invalid or missing internal service secret.",
        )
    return header_secret
