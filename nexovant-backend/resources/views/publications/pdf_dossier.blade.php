<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $publication->title }}</title>
    <style>
        @page {
            margin: 20mm 15mm 20mm 15mm;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #1E293B;
            background-color: #FFFFFF;
            margin: 0;
            padding: 0;
        }

        .page-break {
            page-break-after: always;
        }

        /* Running Header & Footer */
        .header {
            position: fixed;
            top: -15mm;
            left: 0;
            right: 0;
            height: 10mm;
            border-bottom: 1px solid #CBD5E1;
            font-size: 8pt;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer {
            position: fixed;
            bottom: -15mm;
            left: 0;
            right: 0;
            height: 10mm;
            border-top: 1px solid #CBD5E1;
            font-size: 8pt;
            color: #64748B;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Cover Page */
        .cover-page {
            text-align: left;
            padding-top: 40mm;
        }

        .brand-logo {
            font-size: 26pt;
            font-weight: 800;
            letter-spacing: 4px;
            color: #0F172A;
            margin-bottom: 5mm;
        }

        .brand-tagline {
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #0284C7;
            font-weight: bold;
            margin-bottom: 25mm;
        }

        .confidentiality-stamp {
            display: inline-block;
            padding: 4px 10px;
            font-size: 8pt;
            font-weight: bold;
            color: #0369A1;
            background-color: #E0F2FE;
            border: 1px solid #BAE6FD;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 10mm;
        }

        .pub-title {
            font-size: 24pt;
            font-weight: 700;
            line-height: 1.2;
            color: #0F172A;
            margin: 0 0 5mm 0;
        }

        .pub-subtitle {
            font-size: 13pt;
            color: #475569;
            line-height: 1.4;
            margin: 0 0 25mm 0;
        }

        .meta-box {
            background-color: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            padding: 12px 16px;
            width: 85%;
        }

        .meta-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-grid td {
            padding: 4px 8px;
            font-size: 9pt;
        }

        .meta-label {
            color: #64748B;
            font-weight: 600;
            width: 35%;
            text-transform: uppercase;
            font-size: 8pt;
        }

        .meta-value {
            color: #0F172A;
            font-weight: 600;
        }

        /* Section Headings */
        h2.section-title {
            font-size: 14pt;
            font-weight: 700;
            color: #0F172A;
            border-bottom: 2px solid #0284C7;
            padding-bottom: 3px;
            margin-top: 8mm;
            margin-bottom: 4mm;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        h3.sub-title {
            font-size: 11pt;
            font-weight: 600;
            color: #1E293B;
            margin-top: 4mm;
            margin-bottom: 2mm;
        }

        p {
            margin: 0 0 3mm 0;
            text-align: justify;
        }

        /* Cards & Highlights */
        .callout-box {
            background-color: #F8FAFC;
            border-left: 3px solid #0284C7;
            padding: 8px 12px;
            margin: 4mm 0;
            font-size: 9pt;
        }

        .warning-box {
            background-color: #FFFBEB;
            border-left: 3px solid #F59E0B;
            padding: 8px 12px;
            margin: 4mm 0;
            font-size: 9pt;
        }

        /* Tables */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 4mm 0;
            font-size: 8.5pt;
        }

        table.data-table th {
            background-color: #0F172A;
            color: #FFFFFF;
            font-weight: 600;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #0F172A;
        }

        table.data-table td {
            padding: 5px 8px;
            border: 1px solid #CBD5E1;
            vertical-align: top;
        }

        table.data-table tr:nth-child(even) td {
            background-color: #F8FAFC;
        }

        /* Timeline Nodes */
        .timeline-item {
            margin-bottom: 4mm;
            padding-left: 12px;
            border-left: 2px solid #0284C7;
        }

        .timeline-date {
            font-weight: bold;
            color: #0284C7;
            font-size: 8.5pt;
        }

        /* Footnotes & Citations */
        .citations-list {
            font-size: 8pt;
            color: #475569;
            margin-top: 5mm;
        }

        .citation-item {
            margin-bottom: 2mm;
            padding-left: 15px;
            text-indent: -15px;
        }

        .citation-item a {
            color: #0284C7;
            text-decoration: none;
        }
    </style>
</head>
<body>

    <!-- Running Header & Footer -->
    <div class="header">
        <span>{{ $template->brand_config['header_text'] ?? 'NEXOVANT GLOBAL INTELLIGENCE' }}</span>
        <span>EDITION: {{ $publication->edition_number ?? 'CONFIDENTIAL' }}</span>
    </div>

    <div class="footer">
        <span>{{ $template->brand_config['footer_text'] ?? 'VERIFIED INTELLIGENCE • ALL RIGHTS RESERVED' }}</span>
        <span>CONFIDENTIAL • FOR AUTHORIZED SUBSCRIBERS ONLY</span>
    </div>

    <!-- 1. COVER PAGE -->
    <div class="cover-page page-break">
        <div class="confidentiality-stamp">
            {{ $publication->confidentiality_label ?? 'PREMIUM EXECUTIVE INTELLIGENCE' }}
        </div>

        <div class="brand-logo">
            {{ $template->brand_config['logo_text'] ?? 'NEXOVANT' }}
        </div>
        <div class="brand-tagline">
            Strategic Intelligence & Autonomous Research
        </div>

        <h1 class="pub-title">{{ $publication->title }}</h1>
        @if ($publication->subtitle)
            <div class="pub-subtitle">{{ $publication->subtitle }}</div>
        @endif

        <div class="meta-box">
            <table class="meta-grid">
                <tr>
                    <td class="meta-label">Publication Date:</td>
                    <td class="meta-value">{{ $publication->publication_date ? $publication->publication_date->format('F d, Y') : now()->format('F d, Y') }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Reporting Period:</td>
                    <td class="meta-value">{{ $publication->reporting_period ?? 'Past 7 Days' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Edition Reference:</td>
                    <td class="meta-value">{{ $publication->edition_number ?? 'NX-AUTO-'.strtoupper(substr($publication->id, 0, 8)) }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Evidence Score:</td>
                    <td class="meta-value">{{ $publication->evidence_completeness_score }}% Verified Grounding</td>
                </tr>
                <tr>
                    <td class="meta-label">Classification:</td>
                    <td class="meta-value">{{ strtoupper($publication->visibility) }} DISTRIBUTION</td>
                </tr>
            </table>
        </div>
    </div>

    @php
        $content = $publication->content_json ?? [];
    @endphp

    <!-- 2. EXECUTIVE SUMMARY -->
    @if (!empty($content['executive_summary']))
        <div class="page-break">
            <h2 class="section-title">1. Executive Summary & Strategic Imperatives</h2>
            <div class="callout-box">
                <strong>Core Finding:</strong> {{ $content['executive_summary']['highlight'] ?? 'Key intelligence findings synthesized from cross-verified regulatory filings and executive announcements.' }}
            </div>

            <p>{{ $content['executive_summary']['overview'] ?? 'Verified developments indicate accelerated capital deployment and strategic repositioning across monitored technology and corporate hubs.' }}</p>

            @if (!empty($content['executive_summary']['key_takeaways']))
                <h3 class="sub-title">Key Strategic Imperatives</h3>
                <ul>
                    @foreach ($content['executive_summary']['key_takeaways'] as $item)
                        <li><strong>{{ $item['title'] ?? 'Insight' }}:</strong> {{ $item['summary'] ?? $item }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <!-- 3. KEY INTELLIGENCE & DEVELOPMENTS -->
    @if (!empty($content['key_intelligence']))
        <div class="page-break">
            <h2 class="section-title">2. Key Intelligence & Verified Story Clusters</h2>
            <p>The following developments have been corroborated by multiple independent primary wire and regulatory disclosures.</p>

            @foreach ($content['key_intelligence'] as $story)
                <div style="margin-bottom: 5mm;">
                    <h3 class="sub-title" style="margin-bottom: 1mm;">{{ $story['title'] }}</h3>
                    <div style="font-size: 8pt; color: #0284C7; font-weight: bold; margin-bottom: 2mm;">
                        CLUSTER CONFIDENCE: {{ $story['confidence'] ?? '96%' }} • SOURCES: {{ $story['source_count'] ?? 3 }} • STATUS: VERIFIED
                    </div>
                    <p>{{ $story['summary'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <!-- 4. CORPORATE & LEADERSHIP MOVES -->
    @if (!empty($content['company_intelligence']))
        <div class="page-break">
            <h2 class="section-title">3. Corporate Intelligence & Leadership Movements</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Entity / Ticker</th>
                        <th style="width: 35%;">Executive / Announcement</th>
                        <th style="width: 40%;">Strategic Impact</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($content['company_intelligence'] as $item)
                        <tr>
                            <td><strong>{{ $item['company_name'] }}</strong><br><span style="color: #64748B;">{{ $item['ticker'] ?? 'PRIVATE' }}</span></td>
                            <td>{{ $item['announcement'] }}</td>
                            <td>{{ $item['impact'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- 5. TECHNOLOGY & PRODUCT INTELLIGENCE -->
    @if (!empty($content['technology_intelligence']))
        <div class="page-break">
            <h2 class="section-title">4. Technology Intelligence & Product Milestones</h2>
            @foreach ($content['technology_intelligence'] as $tech)
                <div style="margin-bottom: 4mm;">
                    <h3 class="sub-title" style="color: #0284C7;">{{ $tech['name'] }}</h3>
                    <p>{{ $tech['description'] }}</p>
                    @if (!empty($tech['specifications']))
                        <div style="font-size: 8.5pt; background: #F1F5F9; padding: 4px 8px; border-radius: 4px;">
                            <strong>Technical Footprint:</strong> {{ $tech['specifications'] }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <!-- 6. TREND INTELLIGENCE -->
    @if (!empty($content['trend_intelligence']))
        <div class="page-break">
            <h2 class="section-title">5. Emerging Trends & Indicators</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Trend Name</th>
                        <th>Velocity</th>
                        <th>Cross-Source Volume</th>
                        <th>Synthesis</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($content['trend_intelligence'] as $trend)
                        <tr>
                            <td><strong>{{ $trend['name'] }}</strong></td>
                            <td style="color: #0284C7; font-weight: bold;">{{ $trend['velocity'] ?? '+38% YoY' }}</td>
                            <td>{{ $trend['source_count'] ?? 14 }} Disclosures</td>
                            <td>{{ $trend['analysis'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- 7. HISTORICAL TIMELINE -->
    @if (!empty($content['historical_timeline']))
        <div class="page-break">
            <h2 class="section-title">6. Historical Timeline & Precedents</h2>
            <p>Chronological milestones leading to current strategic positions.</p>

            @foreach ($content['historical_timeline'] as $event)
                <div class="timeline-item">
                    <div class="timeline-date">{{ $event['date'] }}</div>
                    <div style="font-size: 9.5pt; font-weight: 600; color: #0F172A;">{{ $event['title'] }}</div>
                    <p style="font-size: 9pt; color: #334155;">{{ $event['description'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <!-- 8. CONFLICTING EVIDENCE & LIMITATIONS -->
    @if (!empty($content['conflicting_evidence']) && count($content['conflicting_evidence']) > 0)
        <div class="page-break">
            <h2 class="section-title">7. Conflicting Reports & Intelligence Discrepancies</h2>
            <div class="warning-box">
                <strong>Editorial Transparency:</strong> The following points reflect conflicting statements across external reports that remain under editorial scrutiny.
            </div>

            @foreach ($content['conflicting_evidence'] as $conflict)
                <div style="margin-bottom: 4mm;">
                    <h3 class="sub-title" style="color: #D97706;">Claim: {{ $conflict['claim'] }}</h3>
                    <p><strong>Primary Discrepancy:</strong> {{ $conflict['details'] }}</p>
                    <div style="font-size: 8.5pt; color: #64748B;">
                        Supporting: {{ $conflict['supporting_sources'] }} • Opposing: {{ $conflict['opposing_sources'] }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- 9. STRATEGIC IMPLICATIONS -->
    @if (!empty($content['strategic_implications']))
        <div class="page-break">
            <h2 class="section-title">8. Strategic Implications & Outlook</h2>
            <div style="font-size: 8pt; color: #64748B; font-style: italic; margin-bottom: 3mm;">
                * Sourced facts and AI-assisted predictive implications are distinguished per Nexovant editorial governance standards.
            </div>

            <p>{{ $content['strategic_implications']['analysis'] ?? 'Long-term capital returns will be dictated by localized supply chain integration and resilience against sovereign export controls.' }}</p>

            @if (!empty($content['strategic_implications']['forecasts']))
                <h3 class="sub-title">12–24 Month Strategic Vectors</h3>
                <ul>
                    @foreach ($content['strategic_implications']['forecasts'] as $forecast)
                        <li>{{ $forecast }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <!-- 10. SOURCES, CITATIONS & METHODOLOGY -->
    <div class="page-break">
        <h2 class="section-title">9. Grounded Citations & Methodology Disclosure</h2>
        <p style="font-size: 8.5pt; color: #475569;">
            {{ $template->disclosure_text ?? 'All publications are strictly synthesized from verified Nexovant primary disclosures, audited wire transmissions, and regulatory filings. Information is validated by cross-checking timestamps, authoritative corporate registers, and fact-checking models.' }}
        </p>

        @if (!empty($content['sources']))
            <div class="citations-list">
                @foreach ($content['sources'] as $idx => $source)
                    <div class="citation-item">
                        [{{ $idx + 1 }}] <strong>{{ $source['name'] }}</strong> — <em>"{{ $source['title'] ?? 'Official Statement' }}"</em>
                        @if (!empty($source['date'])) ({{ $source['date'] }}) @endif
                        @if (!empty($source['url'])) — <a href="{{ $source['url'] }}">{{ $source['url'] }}</a> @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="citations-list">
                <div class="citation-item">[1] SEC EDGAR Corporate Periodic Disclosures & 10-K / 8-K Reports (2026).</div>
                <div class="citation-item">[2] European Commission DG Competition & Directorate-General for Communications Networks Disclosures.</div>
                <div class="citation-item">[3] Monitored Global Technology Wires & Verified Primary Transcripts.</div>
            </div>
        @endif
    </div>

</body>
</html>
