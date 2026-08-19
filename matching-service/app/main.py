import os
import hmac

from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse
from app.routes import matching, personality, health

DEBUG = os.getenv("DEBUG", "false").lower() == "true"
INVALID_API_KEYS = {"", "change-me", "changeme", "generate-a-long-random-value"}


def configured_api_key() -> str:
    return os.getenv("MATCHING_SERVICE_API_KEY", os.getenv("MATCHING_API_KEY", "")).strip()


def has_valid_api_key() -> bool:
    return configured_api_key().lower() not in INVALID_API_KEYS

app = FastAPI(
    title="MIS - Matching & Personality Service",
    description="Servicio de matching y evaluación de personalidad Big Five (OCEAN)",
    version="1.0.0",
    docs_url="/docs" if DEBUG else None,
    redoc_url="/redoc" if DEBUG else None,
    openapi_url="/openapi.json" if DEBUG else None,
)


@app.middleware("http")
async def require_internal_api_key(request: Request, call_next):
    if request.url.path != "/api/v1/health":
        api_key = configured_api_key()
        provided = request.headers.get("x-internal-api-key")
        if not has_valid_api_key() or not provided or not hmac.compare_digest(provided, api_key):
            return JSONResponse(status_code=401, content={"detail": "Unauthorized"})

    return await call_next(request)

app.include_router(health.router, prefix="/api/v1", tags=["health"])
app.include_router(personality.router, prefix="/api/v1/personality", tags=["personality"])
app.include_router(matching.router, prefix="/api/v1/matching", tags=["matching"])
