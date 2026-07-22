from fastapi import FastAPI
from app.routes import matching, personality, health

app = FastAPI(
    title="MIS - Matching & Personality Service",
    description="Servicio de matching y evaluación de personalidad Big Five (OCEAN)",
    version="1.0.0",
)

app.include_router(health.router, prefix="/api/v1", tags=["health"])
app.include_router(personality.router, prefix="/api/v1/personality", tags=["personality"])
app.include_router(matching.router, prefix="/api/v1/matching", tags=["matching"])
