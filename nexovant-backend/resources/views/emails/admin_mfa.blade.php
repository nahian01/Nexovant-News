<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexovant Administrator Security Authentication</title>
    <style>
        body { margin: 0; padding: 0; background-color: #0A0E17; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #E2E8F0; }
        .wrapper { width: 100%; background-color: #0A0E17; padding: 32px 16px; }
        .container { max-width: 580px; margin: 0 auto; background-color: #111827; border: 1px solid #1F2937; border-radius: 8px; overflow: hidden; }
        .header { padding: 28px 32px; border-bottom: 1px solid #1F2937; background: linear-gradient(180deg, #161F30 0%, #111827 100%); }
        .logo { font-size: 20px; font-weight: 800; letter-spacing: 0.15em; color: #FFFFFF; text-decoration: none; }
        .logo-accent { color: #D4AF37; }
        .tagline { display: block; font-size: 11px; color: #9CA3AF; letter-spacing: 0.08em; margin-top: 4px; text-transform: uppercase; font-family: monospace; }
        .content { padding: 32px; }
        .badge { display: inline-block; padding: 4px 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; border-radius: 4px; background-color: rgba(212, 175, 55, 0.15); color: #D4AF37; border: 1px solid rgba(212, 175, 55, 0.3); margin-bottom: 16px; }
        .code-box { background-color: #0A0E17; border: 2px dashed #D4AF37; border-radius: 8px; padding: 20px; text-align: center; margin: 24px 0; }
        .code-number { font-size: 36px; font-weight: 800; letter-spacing: 0.25em; color: #FFFFFF; font-family: monospace; margin: 0; }
        .security-note { background-color: rgba(16, 185, 129, 0.1); border-left: 4px solid #10B981; padding: 12px 16px; border-radius: 4px; font-size: 12px; line-height: 1.5; color: #A7F3D0; margin-top: 20px; }
        .footer { padding: 20px 32px; background-color: #0B0F19; border-top: 1px solid #1F2937; font-size: 11px; color: #6B7280; text-align: center; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <span class="logo">NEXO<span class="logo-accent">VANT</span></span>
                <span class="tagline">Restricted Executive Control Room</span>
            </div>
            <div class="content">
                <div class="badge">MFA Operator Verification</div>
                <h2 style="margin: 0 0 12px 0; color: #FFFFFF;">One-Time Administrator Login Password</h2>
                <p style="font-size: 14px; line-height: 1.6; color: #CBD5E1; margin: 0 0 16px 0;">
                    Hello <strong>{{ $recipientName }}</strong>, a login request for the Nexovant Administrative Control Center was initiated from IP <code>{{ $ipAddress }}</code>.
                </p>
                <div class="code-box">
                    <p style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #9CA3AF; margin: 0 0 8px 0;">Your Dynamic Security Password / Code</p>
                    <div class="code-number">{{ $mfaCode }}</div>
                    <p style="font-size: 11px; color: #9CA3AF; margin: 8px 0 0 0;">Valid for <strong>{{ $expiresInMinutes }} minutes</strong> • Single use only</p>
                </div>
                <div class="security-note">
                    <strong>🛡️ Zero Database Storage Protection:</strong><br>
                    To protect your platform from credential dumps and data breaches, administrative passwords are <em>never stored in the database</em>. This code exists exclusively in encrypted volatile cache and expires automatically upon use or timeout.
                </div>
            </div>
            <div class="footer">
                If you did not initiate this administrator login, please disregard this email or review your firewall access logs immediately.
            </div>
        </div>
    </div>
</body>
</html>
