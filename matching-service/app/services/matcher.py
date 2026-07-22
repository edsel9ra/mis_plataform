import random
from typing import Optional


class MentorshipMatcher:

    WEIGHTS = {
        "personal": {"personality": 0.30, "skills": 0.25, "interests": 0.20, "availability": 0.15, "history": 0.10},
        "familiar": {"personality": 0.20, "skills": 0.20, "interests": 0.25, "availability": 0.20, "history": 0.15},
        "grupal": {"personality": 0.15, "skills": 0.20, "interests": 0.15, "availability": 0.30, "history": 0.20},
        "corporate": {"personality": 0.15, "skills": 0.30, "interests": 0.15, "availability": 0.20, "history": 0.20},
    }

    def calculate(self, user_id: str, mentor_id: str, client_type: str, context_id: Optional[str] = None) -> dict:
        weights = self.WEIGHTS.get(client_type, self.WEIGHTS["personal"])

        personality_score = random.uniform(60, 95)
        skills_score = random.uniform(55, 90)
        interests_score = random.uniform(50, 95)
        availability_score = random.uniform(40, 100)
        history_score = random.uniform(50, 90)

        total = (
            personality_score * weights["personality"]
            + skills_score * weights["skills"]
            + interests_score * weights["interests"]
            + availability_score * weights["availability"]
            + history_score * weights["history"]
        )

        return {
            "user_id": user_id,
            "mentor_id": mentor_id,
            "client_type": client_type,
            "context_id": context_id,
            "total_score": round(total, 2),
            "breakdown": {
                "personality": {"score": round(personality_score, 2), "weight": weights["personality"]},
                "skills": {"score": round(skills_score, 2), "weight": weights["skills"]},
                "interests": {"score": round(interests_score, 2), "weight": weights["interests"]},
                "availability": {"score": round(availability_score, 2), "weight": weights["availability"]},
                "history": {"score": round(history_score, 2), "weight": weights["history"]},
            },
        }

    def suggest(self, user_id: str, client_type: str, context_id: Optional[str] = None, limit: int = 10) -> dict:
        suggestions = []
        for i in range(limit):
            mentor_id = f"mentor_{random.randint(1000, 9999)}"
            result = self.calculate(user_id, mentor_id, client_type, context_id)
            suggestions.append(result)

        suggestions.sort(key=lambda x: x["total_score"], reverse=True)

        return {
            "user_id": user_id,
            "client_type": client_type,
            "context_id": context_id,
            "suggestions": suggestions[:limit],
        }
