import os

from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse
from app.routes import matching, personality, health

app = FastAPI(
    title="MIS - Matching & Personality Service",
    description="Servicio de matching y evaluación de personalidad Big Five (OCEAN)",
    version="1.0.0",
)


@app.middleware("http")
async def require_internal_api_key(request: Request, call_next):
    api_key = os.getenv("MATCHING_SERVICE_API_KEY")

    if api_key and not request.url.path.endswith("/health"):
        provided = request.headers.get("x-internal-api-key")
        if provided != api_key:
            return JSONResponse(status_code=401, content={"detail": "Unauthorized"})

    return await call_next(request)

app.include_router(health.router, prefix="/api/v1", tags=["health"])
app.include_router(personality.router, prefix="/api/v1/personality", tags=["personality"])
app.include_router(matching.router, prefix="/api/v1/matching", tags=["matching"])
