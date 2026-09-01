# Mentor Workspace API Assumptions

The frontend uses the following contract for the confirmed mentor workspace. Plan, subscription, and workspace routes are implemented by the corresponding API feature.

| Method | Endpoint | Frontend expectation |
| --- | --- | --- |
| `GET` | `/mentor/users` | Existing paginated assigned-user response with assignment plans. |
| `GET` | `/mentor/users/{id}/profile` | Safe user profile, selected assignment, active plan/subscription, and optional `entitlements`. |
| `GET` | `/mentor/users/{id}/neurocognitive` | `self_report`/`mentee_self_report` and `mentor_observation` attention objects. |
| `PUT` | `/mentor/users/{id}/neurocognitive` | Accepts `relationship_id` and `mentor_observation`. |
| `GET` / `PUT` | `/mentor/users/{id}/systemic-map` | Returns and persists `{nodes, edges}`. The map is mentor-only. |
| `GET` | `/mentor/users/{id}/challenges` | Assigned challenge collection. |
| `POST` | `/mentor/users/{id}/challenges` | Assigns `{challenge_id, relationship_id, due_at?}`. |
| `GET` / `POST` | `/mentor/challenges` | Lists or creates reusable challenges. Create uses multipart `content_file`. |
| `GET` | `/mentor/challenges/{id}?relationship_id=...` | Returns an assigned challenge with mentor-visible `check_ins` and `submissions`. |
| `GET` / `POST` | `/mentor/feedback` | Lists or creates feedback. Create uses multipart `feedback_file` plus recipient fields. |
| `GET` | `/mentor/users/{id}/emotional-check-ins` | Lists mentor-visible emotional check-ins. |

The generic `/challenges/{id}/check-ins` and `/challenges/{id}/submissions` routes are mentee-owned and are not used by the mentor workspace. Collection responses may be either a JSON array or Laravel-style `{ "data": [] }`; object responses may be direct or `{ "data": {} }`. The UI intentionally has no browser recording or rich-text challenge-content editor: challenge content and audio/video feedback are existing file uploads only.
