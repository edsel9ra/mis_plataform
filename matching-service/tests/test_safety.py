import os
import unittest
from unittest.mock import patch

from fastapi.testclient import TestClient
from pydantic import ValidationError

from app import main
from app.config import load_settings


class SafetyControlsTest(unittest.TestCase):
    def setUp(self):
        self.client = TestClient(main.app)
        self.api_key = "test-only-safety-api-key"

    def test_rate_limit_returns_429_after_configured_budget(self):
        limiter = main.InMemoryRateLimiter(limit=1, window_seconds=60)
        with patch.object(main, "rate_limiter", limiter), patch.dict(
            os.environ, {"MATCHING_SERVICE_API_KEY": self.api_key}
        ):
            headers = {"X-Internal-API-Key": self.api_key}
            first = self.client.get("/api/v1/personality/not-found", headers=headers)
            second = self.client.get("/api/v1/personality/not-found", headers=headers)

        self.assertEqual(first.status_code, 404)
        self.assertEqual(second.status_code, 429)
        self.assertEqual(second.headers["retry-after"], "60")

    def test_content_length_is_rejected_before_router_processing(self):
        settings = main.SETTINGS.model_copy(update={"max_request_body_bytes": 10})
        with patch.object(main, "SETTINGS", settings), patch.dict(
            os.environ, {"MATCHING_SERVICE_API_KEY": self.api_key}
        ):
            response = self.client.post(
                "/api/v1/personality/not-found",
                content=b"01234567890",
                headers={"X-Internal-API-Key": self.api_key},
            )

        self.assertEqual(response.status_code, 413)

    def test_production_configuration_rejects_missing_internal_key(self):
        with patch.dict(
            os.environ,
            {"MATCHING_REQUIRE_API_KEY": "true", "MATCHING_SERVICE_API_KEY": ""},
            clear=True,
        ):
            with self.assertRaises((ValidationError, ValueError)):
                load_settings()

    def test_debug_allows_placeholder_key_for_local_requests(self):
        settings = main.SETTINGS.model_copy(
            update={
                "debug": True,
                "matching_service_api_key": "",
                "require_api_key": False,
            }
        )
        with patch.object(main, "SETTINGS", settings), patch.dict(
            os.environ, {"MATCHING_SERVICE_API_KEY": ""}
        ):
            response = self.client.get("/api/v1/personality/not-found")

        self.assertEqual(response.status_code, 404)

    def test_matching_endpoints_remain_explicitly_unimplemented(self):
        payload = {"user_id": "user-1", "mentor_id": "mentor-1", "client_type": "personal"}
        with patch.dict(os.environ, {"MATCHING_SERVICE_API_KEY": self.api_key}):
            calculate = self.client.post(
                "/api/v1/matching/calculate",
                json=payload,
                headers={"X-Internal-API-Key": self.api_key},
            )
            suggestions = self.client.post(
                "/api/v1/matching/suggestions",
                json={"user_id": "user-1", "client_type": "personal"},
                headers={"X-Internal-API-Key": self.api_key},
            )

        self.assertEqual(calculate.status_code, 501)
        self.assertEqual(suggestions.status_code, 501)


if __name__ == "__main__":
    unittest.main()
