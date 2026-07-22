from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field
from typing import Dict, Optional
from app.services.big_five import BigFiveScorer

router = APIRouter()
scorer = BigFiveScorer()


class ScoreRequest(BaseModel):
    answers: Dict[int, int]
    test_version: str = Field(default="ipip-neo-120", pattern="^(ipip-neo-120|ipip-neo-300)$")
    age: int = Field(default=30, ge=12, le=120)
    sex: str = Field(default="N", pattern="^[MFN]$")


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
