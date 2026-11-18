<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password — Nexovant</title>
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
        .badge-warning {
            display: inline-block;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-radius: 4px;
            background-color: rgba(245, 158, 11, 0.15);
            color: #F59E0B;
            border: 1px solid rgba(245, 158, 11, 0.3);
            margin-bottom: 16px;
        }
        h1 {
            font-size: 22px;
            line-height: 1.4;
            font-weight: 700;
            color: #FFFFFF;
            margin: 0 0 16px 0;
        }
        p {
            font-size: 14px;
            line-height: 1.6;
            color: #D1D5DB;
            margin: 0 0 16px 0;
        }
        .button-wrap {
            margin: 28px 0;
            text-align: center;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
            color: #FFFFFF !important;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .notice-box {
            background-color: #1A2234;
            border-left: 3px solid #F59E0B;
            padding: 14px 16px;
            border-radius: 0 4px 4px 0;
            margin: 20px 0;
            font-size: 12px;
            color: #D1D5DB;
            line-height: 1.5;
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
                <a href="{{ $frontendUrl }}" class="logo">
                    NEXO<span class="logo-accent">VANT</span>
                </a>
                <span class="tagline">Executive Security & Credential Protection</span>
            </div>

            <div class="content">
                <div class="badge-warning">Security Action Requested</div>
                <h1>Reset Your Password, {{ $recipientName }}</h1>
                <p>
                    We received a request to reset the password for your Nexovant executive account (<code>{{ $recipientEmail }}</code>).
                </p>
                <p>
                    Click the secure link below to set a new password. This link is single-use and will expire in <strong>{{ $expiresInMinutes }} minutes</strong>.
                </p>

                <div class="button-wrap">
                    <a href="{{ $resetUrl }}" class="button">Reset Password &rarr;</a>
                </div>

                <div class="notice-box">
                    <strong>Notice:</strong> If you did not initiate this request, no action is needed. Your existing password remains encrypted and secure.
                </div>

                <p style="font-size: 11px; color: #9CA3AF; word-break: break-all;">
                    Or paste this URL into your browser: <br>
                    <a href="{{ $resetUrl }}" style="color: #00E5FF; text-decoration: underline;">{{ $resetUrl }}</a>
                </p>
            </div>

            <div class="footer">
                <div>© {{ date('Y') }} Nexovant Intelligence Systems. Cryptographically secured.</div>
                <div style="margin-top: 4px;">Sent by Brevo Relay from <code>noreply@nexvant.si</code>.</div>
            </div>
        </div>
    </div>
</body>
</html>
