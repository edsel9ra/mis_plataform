import asyncio
import hmac
import logging
import os
import time
from contextlib import asynccontextmanager

from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse

from app.config import INVALID_API_KEYS, load_settings, validate_runtime_configuration
from app.routes import health, matching, personality


logger = logging.getLogger(__name__)
DEBUG = os.getenv("DEBUG", "false").lower() == "true"
SETTINGS = load_settings()


def configured_api_key() -> str:
    api_key = os.getenv("MATCHING_SERVICE_API_KEY")
    if api_key is None:
        api_key = os.getenv("MATCHING_API_KEY", "")
    return api_key.strip()


def has_valid_api_key() -> bool:
    return configured_api_key().lower() not in INVALID_API_KEYS


class InMemoryRateLimiter:
    """Bounded, per-process limiter; the service intentionally runs one worker."""

    def __init__(self, limit: int, window_seconds: int):
        self.limit = limit
        self.window_seconds = window_seconds
        self._buckets: dict[str, tuple[float, int]] = {}
        self._lock = asyncio.Lock()

    async def allow(self, key: str) -> bool:
        now = time.monotonic()
        async with self._lock:
            if len(self._buckets) > 10_000:
                self._buckets = {
                    bucket_key: bucket
                    for bucket_key, bucket in self._buckets.items()
                    if now - bucket[0] < self.window_seconds
                }

            started_at, count = self._buckets.get(key, (now, 0))
            if now - started_at >= self.window_seconds:
                started_at, count = now, 0
            if count >= self.limit:
                self._buckets[key] = (started_at, count)
                return False

            self._buckets[key] = (started_at, count + 1)
            return True


class RequestTooLarge(Exception):
    pass


rate_limiter = InMemoryRateLimiter(
    limit=SETTINGS.rate_limit_requests,
    window_seconds=SETTINGS.rate_limit_window_seconds,
)


@asynccontextmanager
async def lifespan(_app: FastAPI):
    validate_runtime_configuration()
    yield


app = FastAPI(
    title="MIS - Matching & Personality Service",
    description="Servicio de matching y evaluación de personalidad Big Five (OCEAN)",
    version="1.0.0",
    docs_url="/docs" if DEBUG else None,
    redoc_url="/redoc" if DEBUG else None,
    openapi_url="/openapi.json" if DEBUG else None,
    lifespan=lifespan,
)


@app.middleware("http")
async def security_controls(request: Request, call_next):
    if request.url.path == "/api/v1/health":
        return await call_next(request)

    content_length = request.headers.get("content-length")
    if content_length is not None:
        try:
            if int(content_length) > SETTINGS.max_request_body_bytes:
                return JSONResponse(status_code=413, content={"detail": "Request body is too large"})
        except ValueError:
            return JSONResponse(status_code=400, content={"detail": "Invalid Content-Length"})

    allow_debug_without_key = SETTINGS.debug and not SETTINGS.has_valid_api_key
    if not allow_debug_without_key:
        api_key = configured_api_key()
        provided = request.headers.get("x-internal-api-key")
        if not SETTINGS.has_valid_api_key or not provided or not hmac.compare_digest(provided, api_key):
            return JSONResponse(status_code=401, content={"detail": "Unauthorized"})

    client_key = request.client.host if request.client else "unknown"
    if not await rate_limiter.allow(client_key):
        return JSONResponse(
            status_code=429,
            content={"detail": "Rate limit exceeded"},
            headers={
                "Retry-After": str(SETTINGS.rate_limit_window_seconds),
                "X-RateLimit-Limit": str(SETTINGS.rate_limit_requests),
            },
        )

    received_bytes = 0
    original_receive = request.receive

    async def limited_receive():
        nonlocal received_bytes
        message = await original_receive()
        if message.get("type") == "http.request":
            received_bytes += len(message.get("body", b""))
            if received_bytes > SETTINGS.max_request_body_bytes:
                raise RequestTooLarge
        return message

    limited_request = Request(request.scope, limited_receive)
    try:
        return await call_next(limited_request)
    except RequestTooLarge:
        return JSONResponse(status_code=413, content={"detail": "Request body is too large"})


app.include_router(health.router, prefix="/api/v1", tags=["health"])
app.include_router(personality.router, prefix="/api/v1/personality", tags=["personality"])
app.include_router(matching.router, prefix="/api/v1/matching", tags=["matching"])
