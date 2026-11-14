import pytest
from httpx import ASGITransport, AsyncClient
from app.main import app
from app.core.config import settings


@pytest.mark.asyncio
async def test_public_health_endpoint():
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as client:
        response = await client.get("/api/internal/v1/health")
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "healthy"
        assert data["service"] == settings.APP_NAME


@pytest.mark.asyncio
async def test_detailed_health_requires_auth():
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as client:
        # Request without header should fail 403
        unauth_response = await client.get("/api/internal/v1/health/detailed")
        assert unauth_response.status_code == 403

        # Request with valid internal service secret header should succeed 200
        auth_response = await client.get(
            "/api/internal/v1/health/detailed",
            headers={"X-Internal-Service-Secret": settings.INTERNAL_SERVICE_SECRET},
        )
        assert auth_response.status_code == 200
        detailed_data = auth_response.json()
        assert detailed_data["database"] == "connected"
