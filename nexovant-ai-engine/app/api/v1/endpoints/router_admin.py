from typing import Dict, Any, List
from fastapi import APIRouter, Depends, HTTPException, Body
from app.core.security import verify_internal_secret
from app.services.router.provider_registry import registry
from app.services.router.model_router import router as model_router

router = APIRouter()


@router.get("/router/connections", status_code=200)
async def list_connections(_auth: str = Depends(verify_internal_secret)) -> Dict[str, Any]:
    connections = [conn.to_dict() for conn in registry.list_all()]
    return {
        "success": True,
        "total": len(connections),
        "connections": connections,
    }


@router.post("/router/execute", status_code=200)
async def execute_task(
    payload: Dict[str, Any] = Body(...),
    _auth: str = Depends(verify_internal_secret),
) -> Dict[str, Any]:
    task = payload.get("task", "general")
    prompt = payload.get("prompt", "")
    max_tokens = payload.get("max_tokens", 1000)

    try:
        result = await model_router.execute_task(
            task=task,
            prompt=prompt,
            max_tokens=max_tokens,
        )
        return result
    except Exception as e:
        raise HTTPException(status_code=502, detail=str(e))
