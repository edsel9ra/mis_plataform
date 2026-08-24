import logging

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field, model_validator
from typing import Dict
from app.config import load_settings
from app.services.big_five import BigFiveScorer

router = APIRouter()
scorer = BigFiveScorer()
settings = load_settings()
logger = logging.getLogger(__name__)


class ScoreRequest(BaseModel):
    answers: Dict[int, int] = Field(min_length=1, max_length=300)
    test_version: str = Field(default="ipip-neo-120", pattern="^(ipip-neo-120|ipip-neo-300)$")
    age: int = Field(default=30, ge=12, le=110)
    sex: str = Field(default="N", pattern="^[MFN]$")

    @model_validator(mode="after")
    def validate_answers(self):
        expected = 120 if self.test_version == "ipip-neo-120" else 300

        if len(self.answers) != expected:
            raise ValueError(f"answers must contain exactly {expected} items")

        invalid_questions = [question for question in self.answers.keys() if question < 1 or question > expected]
        if invalid_questions:
            raise ValueError("answer question ids are out of range")

        invalid_answers = [answer for answer in self.answers.values() if answer < 1 or answer > 5]
        if invalid_answers:
            raise ValueError("answer values must be between 1 and 5")

        return self


class CompareRequest(BaseModel):
    user_id_1: str = Field(min_length=1, max_length=128)
    user_id_2: str = Field(min_length=1, max_length=128)


@router.post("/score")
async def calculate_score(request: ScoreRequest):
    try:
        result = scorer.compute(
            answers=request.answers,
            test_version=request.test_version,
            age=request.age,
            sex=request.sex,
        )
        return result
    except Exception:
        logger.exception("Failed to calculate personality score")
        raise HTTPException(status_code=500, detail="Internal personality scoring error")


@router.post("/batch-score")
async def batch_score(requests: list[ScoreRequest]):
    if len(requests) > settings.max_batch_size:
        raise HTTPException(status_code=422, detail="batch size exceeds the configured limit")

    results = []
    for req in requests:
        try:
            result = scorer.compute(
                answers=req.answers,
                test_version=req.test_version,
                age=req.age,
                sex=req.sex,
            )
            results.append(result)
        except Exception:
            logger.exception("Failed to score an item in a personality batch")
            results.append({"error": "Internal personality scoring error"})
    return {"results": results}


@router.get("/compare/{id1}/{id2}")
async def compare_profiles(id1: str, id2: str):
    return {
        "user_1": id1,
        "user_2": id2,
        "message": "Comparison endpoint - requires profile data from Laravel API"
    }
