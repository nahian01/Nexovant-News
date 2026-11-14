from contextlib import asynccontextmanager
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from app.core.config import settings
from app.api.v1.endpoints import health, router_admin, rag, reports


@asynccontextmanager
async def lifespan(app: FastAPI):
    # Initialize background registries & health trackers
    yield


app = FastAPI(
    title=settings.APP_NAME,
    description="Nexovant Multi-Provider AI Gateway, RAG Pipeline & Executive Report Engine",
    version="1.0.0",
    docs_url=f"{settings.API_V1_PREFIX}/docs" if settings.DEBUG else None,
    redoc_url=None,
    openapi_url=f"{settings.API_V1_PREFIX}/openapi.json" if settings.DEBUG else None,
    lifespan=lifespan,
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE"],
    allow_headers=["*"],
)

# Register Subsystem Endpoints
app.include_router(health.router, prefix=settings.API_V1_PREFIX, tags=["Health"])
app.include_router(router_admin.router, prefix=settings.API_V1_PREFIX, tags=["AI Router"])
app.include_router(rag.router, prefix=settings.API_V1_PREFIX, tags=["RAG"])
app.include_router(reports.router, prefix=settings.API_V1_PREFIX, tags=["Executive Reports"])


@app.get("/")
async def root():
    return {
        "service": settings.APP_NAME,
        "status": "operational",
        "documentation": "Protected internal executive intelligence API.",
    }
