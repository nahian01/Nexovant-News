import pytest
from httpx import ASGITransport, AsyncClient
from app.main import app
from app.core.config import settings
from app.services.router.circuit_breaker import CircuitBreaker
from app.services.router.quota_tracker import QuotaTracker
from app.services.router.provider_registry import registry
from app.services.router.model_router import router as model_router


@pytest.mark.asyncio
async def test_router_lists_active_connections():
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as client:
        response = await client.get(
            "/api/internal/v1/router/connections",
            headers={"X-Internal-Service-Secret": settings.INTERNAL_SERVICE_SECRET},
        )
        assert response.status_code == 200
        data = response.json()
        assert data["success"] is True
        assert data["total"] >= 4


@pytest.mark.asyncio
async def test_circuit_breaker_lifecycle():
    cb = CircuitBreaker(failure_threshold=2, cooldown_seconds=60)
    assert cb.can_execute() is True
    assert cb.state == "closed"

    # Strike 1
    cb.record_failure()
    assert cb.state == "closed"
    assert cb.can_execute() is True

    # Strike 2 -> Tripped to open
    cb.record_failure()
    assert cb.state == "open"
    assert cb.can_execute() is False

    # Reset
    cb.record_success()
    assert cb.state == "closed"
    assert cb.can_execute() is True


@pytest.mark.asyncio
async def test_quota_tracker_rpm_saturation():
    tracker = QuotaTracker(rpm_limit=3, tpm_limit=50000)
    assert tracker.can_consume(1000) is True

    tracker.record_usage(500, 500)
    tracker.record_usage(500, 500)
    tracker.record_usage(500, 500)

    # 4th request in same minute should be rejected
    assert tracker.can_consume(1000) is False
    assert tracker.rpm_saturation == 1.0


@pytest.mark.asyncio
async def test_model_router_executes_task_and_records_tokens():
    result = await model_router.execute_task(
        task="summarization",
        prompt="Analyze NVIDIA semiconductor packaging capex expansion.",
    )
    assert result["success"] is True
    assert "usage" in result
    assert result["usage"]["input_tokens"] > 0
    assert result["usage"]["output_tokens"] > 0
    assert result["usage"]["estimated_cost_usd"] > 0


@pytest.mark.asyncio
async def test_rag_query_endpoint():
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as client:
        response = await client.post(
            "/api/internal/v1/rag/query",
            json={"query": "What are NVIDIA's latest expansion plans?"},
            headers={"X-Internal-Service-Secret": settings.INTERNAL_SERVICE_SECRET},
        )
        assert response.status_code == 200
        data = response.json()
        assert data["success"] is True
        assert len(data["source_citations"]) > 0
        assert "response" in data


@pytest.mark.asyncio
async def test_reportlab_pdf_generation_endpoint():
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as client:
        response = await client.post(
            "/api/internal/v1/reports/generate-pdf",
            json={"title": "Weekly Market Review"},
            headers={"X-Internal-Service-Secret": settings.INTERNAL_SERVICE_SECRET},
        )
        assert response.status_code == 200
        assert response.headers["content-type"] == "application/pdf"
        # Validate PDF magic header
        assert response.content.startswith(b"%PDF")
