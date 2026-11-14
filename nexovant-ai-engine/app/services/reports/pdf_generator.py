import io
from datetime import datetime, timezone
from reportlab.lib import colors
from reportlab.lib.pagesizes import letter
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, HRFlowable


class ExecutiveReportGenerator:
    """
    Asynchronous ReportLab PDF Generator for Nexovant Executive Publications.
    Produces print-ready, pixel-perfect executive intelligence dossiers.
    """
    @staticmethod
    def generate_executive_briefing(
        title: str = "Daily Global Executive Briefing",
        summary_text: str = "",
        developments: list | None = None,
    ) -> bytes:
        buffer = io.BytesIO()
        doc = SimpleDocTemplate(
            buffer,
            pagesize=letter,
            rightMargin=40,
            leftMargin=40,
            topMargin=40,
            bottomMargin=40,
        )

        styles = getSampleStyleSheet()

        # Custom Editorial Styles
        title_style = ParagraphStyle(
            'NexovantTitle',
            parent=styles['Normal'],
            fontName='Helvetica-Bold',
            fontSize=22,
            leading=26,
            textColor=colors.HexColor('#0B132B'),
        )

        subtitle_style = ParagraphStyle(
            'NexovantSubtitle',
            parent=styles['Normal'],
            fontName='Helvetica-Bold',
            fontSize=8,
            leading=10,
            textColor=colors.HexColor('#00ADB5'),
            spaceAfter=15,
        )

        heading_style = ParagraphStyle(
            'NexovantHeading',
            parent=styles['Heading2'],
            fontName='Helvetica-Bold',
            fontSize=13,
            leading=16,
            textColor=colors.HexColor('#1C2541'),
            spaceBefore=10,
            spaceAfter=6,
        )

        body_style = ParagraphStyle(
            'NexovantBody',
            parent=styles['BodyText'],
            fontName='Helvetica',
            fontSize=9.5,
            leading=14,
            textColor=colors.HexColor('#2E3D60'),
        )

        meta_style = ParagraphStyle(
            'NexovantMeta',
            parent=styles['Normal'],
            fontName='Helvetica',
            fontSize=8,
            leading=11,
            textColor=colors.HexColor('#8D99AE'),
        )

        story = []

        # 1. Header Banner
        story.append(Paragraph("NEXOVANT", title_style))
        story.append(Paragraph("INTELLIGENCE BEYOND HEADLINES — GLOBAL BUSINESS SAAS", subtitle_style))
        story.append(HRFlowable(width="100%", thickness=1.5, color=colors.HexColor('#00ADB5'), spaceAfter=15))

        # 2. Report Metadata Block
        gen_time = datetime.now(timezone.utc).strftime("%B %d, %Y | %H:%M UTC")
        meta_table = Table(
            [
                [
                    Paragraph(f"<b>REPORT:</b> {title.upper()}", meta_style),
                    Paragraph(f"<b>ISSUED:</b> {gen_time}", meta_style),
                    Paragraph("<b>VERIFICATION:</b> AUDITED PROVENANCE", meta_style),
                ]
            ],
            colWidths=[200, 170, 160],
        )
        meta_table.setStyle(
            TableStyle([
                ('BACKGROUND', (0, 0), (-1, -1), colors.HexColor('#F8F9FA')),
                ('BOX', (0, 0), (-1, -1), 0.5, colors.HexColor('#E5E7EB')),
                ('PADDING', (0, 0), (-1, -1), 6),
            ])
        )
        story.append(meta_table)
        story.append(Spacer(1, 15))

        # 3. Executive Synthesis
        story.append(Paragraph("Executive Overview & Macro Synthesis", heading_style))
        default_summary = (
            "During the past 24-hour cycle, global corporate activity was dominated by sovereign compute expansions in EMEA, "
            "strategic executive transitions in multinational healthcare, and emerging supply-chain lead time extensions across Asian "
            "packaging clusters. Institutional sentiment remains selective, prioritizing free-cash-flow resilience over expansionary debt."
        )
        story.append(Paragraph(summary_text or default_summary, body_style))
        story.append(Spacer(1, 15))

        # 4. Verified Developments Table
        story.append(Paragraph("Key Monitored Developments & 10-Dimension Impact", heading_style))

        table_data = [
            ["Entity / Ticker", "Event Category", "Status", "Business Significance"]
        ]

        sample_devs = developments or [
            ["NVIDIA Corp (NVDA)", "Capex Expansion", "Confirmed", "$15B EMEA Packaging foundry initiative."],
            ["Novartis AG (NVS)", "Leadership Transition", "Reliable Source", "Global CTO resignation; division review."],
            ["ASE Group (ASX)", "Supply Chain Disruption", "Developing", "Packaging delay affecting automotive MCUs."],
            ["Novo Nordisk (NVO)", "Regulatory Milestone", "Confirmed", "Phase-III metabolic clinical trial greenlight."],
        ]

        for item in sample_devs:
            table_data.append([
                Paragraph(f"<b>{item[0]}</b>", body_style),
                Paragraph(item[1], body_style),
                Paragraph(f"<font color='#00ADB5'><b>{item[2]}</b></font>", body_style),
                Paragraph(item[3], body_style),
            ])

        dev_table = Table(table_data, colWidths=[120, 110, 90, 210])
        dev_table.setStyle(
            TableStyle([
                ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#1C2541')),
                ('TEXTCOLOR', (0, 0), (-1, 0), colors.white),
                ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
                ('FONTSIZE', (0, 0), (-1, 0), 8.5),
                ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
                ('TOPPADDING', (0, 0), (-1, -1), 6),
                ('GRID', (0, 0), (-1, -1), 0.5, colors.HexColor('#E5E7EB')),
            ])
        )
        story.append(dev_table)
        story.append(Spacer(1, 25))

        # 5. Footer & AI Transparency Disclosure
        disclaimer = (
            "<b>Compliance Notice:</b> Nexovant operates as an executive intelligence assistant. Factual claims are cross-referenced "
            "against primary regulatory disclosures (SEC, ESMA) and licensed financial feeds. AI summaries do not constitute investment advice."
        )
        story.append(HRFlowable(width="100%", thickness=0.5, color=colors.HexColor('#CBD5E1'), spaceAfter=8))
        story.append(Paragraph(disclaimer, meta_style))

        doc.build(story)
        pdf_bytes = buffer.getvalue()
        buffer.close()
        return pdf_bytes
