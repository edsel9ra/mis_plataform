from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field
from typing import Optional
from app.services.matcher import MentorshipMatcher

router = APIRouter()
matcher = MentorshipMatcher()


class CalculateRequest(BaseModel):
    user_id: str
    mentor_id: str
    client_type: str = Field(pattern="^(personal|familiar|grupal|empresa)$")
    context_id: Optional[str] = None


class SuggestionsRequest(BaseModel):
    user_id: str
    client_type: str = Field(pattern="^(personal|familiar|grupal|empresa)$")
    context_id: Optional[str] = None
    limit: int = Field(default=10, ge=1, le=50)


@router.post("/calculate")
async def calculate_match(request: CalculateRequest):
    try:
        result = matcher.calculate(
            user_id=request.user_id,
            mentor_id=request.mentor_id,
            client_type=request.client_type,
            context_id=request.context_id,
        )
        return result
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/suggestions")
async def get_suggestions(request: SuggestionsRequest):
    try:
        result = matcher.suggest(
            user_id=request.user_id,
            client_type=request.client_type,
            context_id=request.context_id,
            limit=request.limit,
        )
        return result
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/analyze-profile")
async def analyze_profile(user_id: str):
    return {
        "user_id": user_id,
        "message": "Profile analysis requires data from Laravel API"
    }
