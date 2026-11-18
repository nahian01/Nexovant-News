<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — Nexovant Executive Report</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #0A0E17;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #E2E8F0;
            -webkit-font-smoothing: antialiased;
        }
        .wrapper {
            width: 100%;
            background-color: #0A0E17;
            padding: 32px 16px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #111827;
            border: 1px solid #1F2937;
            border-radius: 8px;
            overflow: hidden;
        }
        .header {
            padding: 28px 32px;
            border-bottom: 1px solid #1F2937;
            background: linear-gradient(180deg, #161F30 0%, #111827 100%);
        }
        .logo {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.15em;
            color: #FFFFFF;
            text-decoration: none;
            display: inline-block;
        }
        .logo-accent {
            color: #00E5FF;
        }
        .tagline {
            display: block;
            font-size: 11px;
            color: #9CA3AF;
            letter-spacing: 0.08em;
            margin-top: 4px;
            text-transform: uppercase;
            font-family: "Courier New", Courier, monospace;
        }
        .content {
            padding: 32px;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-radius: 4px;
            background-color: rgba(16, 185, 129, 0.15);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.3);
            margin-bottom: 16px;
        }
        h1 {
            font-size: 22px;
            line-height: 1.35;
            font-weight: 700;
            color: #FFFFFF;
            margin: 0 0 16px 0;
            font-family: Georgia, serif;
        }
        p {
            font-size: 14px;
            line-height: 1.6;
            color: #D1D5DB;
            margin: 0 0 16px 0;
        }
        .dossier-card {
            background-color: #1A2234;
            border: 1px solid #27354A;
            border-radius: 6px;
            padding: 18px 20px;
            margin: 20px 0;
        }
        .detail-label {
            color: #9CA3AF;
            font-family: "Courier New", Courier, monospace;
            text-transform: uppercase;
            font-size: 11px;
        }
        .detail-value {
            color: #FFFFFF;
            font-weight: 600;
            font-size: 13px;
        }
        .button-wrap {
            margin: 28px 0;
            text-align: center;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: #FFFFFF !important;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .footer {
            padding: 24px 32px;
            border-top: 1px solid #1F2937;
            background-color: #0D131F;
            font-size: 12px;
            color: #6B7280;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <a href="{{ $appUrl }}" class="logo">
                    NEXO<span class="logo-accent">VANT</span>
                </a>
                <span class="tagline">Executive Intelligence Dossier Dispatch</span>
            </div>

            <div class="content">
                <div class="badge">Verified Executive Publication</div>
                <h1>{{ $title }}</h1>

                <div class="dossier-card">
                    <table width="100%" cellpadding="4" cellspacing="0" style="border-collapse: collapse;">
                        <tr>
                            <td class="detail-label" width="35%">Series:</td>
                            <td class="detail-value">{{ $seriesName }}</td>
                        </tr>
                        <tr>
                            <td class="detail-label">Publication Date:</td>
                            <td class="detail-value">{{ $publicationDate }}</td>
                        </tr>
                        <tr>
                            <td class="detail-label">Classification:</td>
                            <td class="detail-value">{{ $visibility }}</td>
                        </tr>
                        <tr>
                            <td class="detail-label">Recipient:</td>
                            <td class="detail-value">{{ $recipientName }} ({{ $recipientEmail }})</td>
                        </tr>
                    </table>
                </div>

                <p>
                    {{ $summary ?? 'The requested executive research synthesis and verified intelligence dossier has been compiled and is ready for download.' }}
                </p>

                <div class="button-wrap">
                    <a href="{{ $downloadUrl }}" class="button">Download Intelligence Dossier &rarr;</a>
                </div>

                <p style="font-size: 12px; color: #9CA3AF;">
                    This briefing is cryptographically signed and tracked for proprietary compliance. An authentic PDF document is also attached directly to this dispatch if permitted by your mail server limits.
                </p>
            </div>

            <div class="footer">
                <div>© {{ date('Y') }} Nexovant Intelligence Systems. All rights reserved.</div>
                <div style="margin-top: 4px;">Sent by Brevo Relay from <code>noreply@nexvant.si</code>.</div>
            </div>
        </div>
    </div>
</body>
</html>
