import os
import unittest
from unittest.mock import patch

from fastapi.testclient import TestClient

from app import main


class ApiKeyMiddlewareTest(unittest.TestCase):
    def setUp(self):
        self.client = TestClient(main.app)

    def test_health_is_public_without_a_configured_key(self):
        with patch.dict(os.environ, {"MATCHING_SERVICE_API_KEY": ""}):
            response = self.client.get("/api/v1/health")

        self.assertEqual(response.status_code, 200)

    def test_missing_key_fails_closed_in_debug(self):
        with patch.object(main, "DEBUG", True), patch.dict(os.environ, {
            "MATCHING_SERVICE_API_KEY": "generate-a-long-random-value",
        }):
            response = self.client.get("/api/v1/personality/not-found")

        self.assertEqual(response.status_code, 401)

    def test_valid_configured_and_supplied_keys_reach_the_router(self):
        api_key = "test-only-valid-internal-api-key"
        with patch.dict(os.environ, {"MATCHING_SERVICE_API_KEY": api_key}):
            response = self.client.get(
                "/api/v1/personality/not-found",
                headers={"X-Internal-API-Key": api_key},
            )

        self.assertEqual(response.status_code, 404)

    def test_legacy_local_key_name_remains_fail_closed_and_supported(self):
        api_key = "test-only-legacy-local-api-key"
        with patch.dict(os.environ, {"MATCHING_API_KEY": api_key}, clear=True):
            unauthorized = self.client.get("/api/v1/personality/not-found")
            authorized = self.client.get(
                "/api/v1/personality/not-found",
                headers={"X-Internal-API-Key": api_key},
            )

        self.assertEqual(unauthorized.status_code, 401)
        self.assertEqual(authorized.status_code, 404)


if __name__ == "__main__":
    unittest.main()
