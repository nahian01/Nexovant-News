from typing import Dict, Any
from fastapi import APIRouter, Depends, Body, Response
from app.core.security import verify_internal_secret
from app.services.reports.pdf_generator import ExecutiveReportGenerator

router = APIRouter()


@router.post("/reports/generate-pdf", status_code=200)
async def generate_pdf_report(
    payload: Dict[str, Any] = Body(...),
    _auth: str = Depends(verify_internal_secret),
) -> Response:
    title = payload.get("title", "Daily Global Executive Briefing")
    summary = payload.get("summary", "")
    developments = payload.get("developments", None)

    pdf_bytes = ExecutiveReportGenerator.generate_executive_briefing(
        title=title,
        summary_text=summary,
        developments=developments,
    )

    return Response(
        content=pdf_bytes,
        media_type="application/pdf",
        headers={
            "Content-Disposition": f"attachment; filename=Nexovant_Briefing_{title.replace(' ', '_')}.pdf"
        }
    )
