from typing import Optional


class MentorshipMatcher:

    WEIGHTS = {
        "personal": {"personality": 0.30, "skills": 0.25, "interests": 0.20, "availability": 0.15, "history": 0.10},
        "familiar": {"personality": 0.20, "skills": 0.20, "interests": 0.25, "availability": 0.20, "history": 0.15},
        "grupal": {"personality": 0.15, "skills": 0.20, "interests": 0.15, "availability": 0.30, "history": 0.20},
        "empresa": {"personality": 0.15, "skills": 0.30, "interests": 0.15, "availability": 0.20, "history": 0.20},
    }

    def calculate(self, user_id: str, mentor_id: str, client_type: str, context_id: Optional[str] = None) -> dict:
        raise NotImplementedError("Matching requires Laravel profile, skill and availability data")

    def suggest(self, user_id: str, client_type: str, context_id: Optional[str] = None, limit: int = 10) -> dict:
        raise NotImplementedError("Matching suggestions require Laravel mentor data")
