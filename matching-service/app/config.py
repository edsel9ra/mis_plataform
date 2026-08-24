import os

from pydantic import BaseModel, Field, field_validator, model_validator


INVALID_API_KEYS = frozenset({"", "change-me", "changeme", "generate-a-long-random-value", "__GENERATE_LOCAL_SECRET__"})


class Settings(BaseModel):
    debug: bool = False
    matching_service_api_key: str = ""
    require_api_key: bool = False
    rate_limit_requests: int = Field(default=120, ge=1, le=10_000)
    rate_limit_window_seconds: int = Field(default=60, ge=1, le=3_600)
    max_request_body_bytes: int = Field(default=1_048_576, ge=1_024, le=10_485_760)
    max_batch_size: int = Field(default=50, ge=1, le=100)
    worker_count: int = Field(default=1, ge=1, le=1)

    @field_validator("matching_service_api_key", mode="before")
    @classmethod
    def normalize_api_key(cls, value: str) -> str:
        return str(value or "").strip()

    @model_validator(mode="after")
    def validate_security_settings(self):
        if self.require_api_key and self.matching_service_api_key.lower() in INVALID_API_KEYS:
            raise ValueError("MATCHING_SERVICE_API_KEY must be configured when MATCHING_REQUIRE_API_KEY is enabled")
        return self

    @property
    def has_valid_api_key(self) -> bool:
        return self.matching_service_api_key.lower() not in INVALID_API_KEYS


def load_settings() -> Settings:
    api_key = os.getenv("MATCHING_SERVICE_API_KEY")
    if api_key is None:
        api_key = os.getenv("MATCHING_API_KEY", "")

    require_api_key = os.getenv("MATCHING_REQUIRE_API_KEY")
    if require_api_key is None:
        require_api_key = os.getenv("REQUIRE_API_KEY", "false")

    return Settings(
        debug=os.getenv("DEBUG", "false"),
        matching_service_api_key=api_key,
        require_api_key=require_api_key,
        rate_limit_requests=os.getenv("RATE_LIMIT_REQUESTS", "120"),
        rate_limit_window_seconds=os.getenv("RATE_LIMIT_WINDOW_SECONDS", "60"),
        max_request_body_bytes=os.getenv("MAX_REQUEST_BODY_BYTES", "1048576"),
        max_batch_size=os.getenv("MAX_BATCH_SIZE", "50"),
        worker_count=os.getenv("MATCHING_WORKERS", "1"),
    )


def validate_runtime_configuration() -> Settings:
    settings = load_settings()
    if settings.require_api_key and not settings.has_valid_api_key:
        raise ValueError("A non-placeholder MATCHING_SERVICE_API_KEY is required")
    return settings
