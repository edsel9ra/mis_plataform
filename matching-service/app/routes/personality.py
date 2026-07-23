from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field, model_validator
from typing import Dict, Optional
from app.services.big_five import BigFiveScorer

router = APIRouter()
scorer = BigFiveScorer()


class ScoreRequest(BaseModel):
    answers: Dict[int, int]
    test_version: str = Field(default="ipip-neo-120", pattern="^(ipip-neo-120|ipip-neo-300)$")
    age: int = Field(default=30, ge=12, le=120)
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
    user_id_1: str
    user_id_2: str


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
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/batch-score")
async def batch_score(requests: list[ScoreRequest]):
    if len(requests) > 50:
        raise HTTPException(status_code=422, detail="batch size cannot exceed 50")

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
        except Exception as e:
            results.append({"error": str(e)})
    return {"results": results}


@router.get("/compare/{id1}/{id2}")
async def compare_profiles(id1: str, id2: str):
    return {
        "user_1": id1,
        "user_2": id2,
        "message": "Comparison endpoint - requires profile data from Laravel API"
    }
