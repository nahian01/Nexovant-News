<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexovant AI Engine Quota Alert</title>
    <style>
        body { margin: 0; padding: 0; background-color: #0A0E17; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #E2E8F0; }
        .wrapper { width: 100%; background-color: #0A0E17; padding: 32px 16px; }
        .container { max-width: 580px; margin: 0 auto; background-color: #111827; border: 1px solid #1F2937; border-radius: 8px; overflow: hidden; }
        .header { padding: 24px 32px; border-bottom: 1px solid #1F2937; background: linear-gradient(180deg, #161F30 0%, #111827 100%); }
        .logo { font-size: 20px; font-weight: 800; letter-spacing: 0.15em; color: #FFFFFF; text-decoration: none; }
        .logo-accent { color: #00E5FF; }
        .content { padding: 32px; }
        .alert-badge { display: inline-block; padding: 4px 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; border-radius: 4px; margin-bottom: 16px; }
        .alert-warning { background-color: rgba(245, 158, 11, 0.15); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3); }
        .alert-danger { background-color: rgba(239, 68, 68, 0.15); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .stats-table { width: 100%; border-collapse: collapse; margin: 20px 0; background-color: #0D1321; border-radius: 6px; overflow: hidden; }
        .stats-table td { padding: 12px 16px; border-bottom: 1px solid #1F2937; font-size: 13px; }
        .stats-table tr:last-child td { border-bottom: none; }
        .footer { padding: 18px 32px; background-color: #0B0F19; border-top: 1px solid #1F2937; font-size: 11px; color: #6B7280; text-align: center; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <span class="logo">NEXO<span class="logo-accent">VANT</span></span>
                <span style="display: block; font-size: 11px; color: #9CA3AF; margin-top: 4px; text-transform: uppercase; font-family: monospace;">AI Cognitive Engine Health Monitor</span>
            </div>
            <div class="content">
                @if($isRotated)
                    <div class="alert-badge alert-danger">Quota Reached — Key Rotated</div>
                    <h2 style="margin: 0 0 12px 0; color: #FFFFFF;">AI Engine Auto-Rotated to Next Provider</h2>
                    <p style="font-size: 14px; line-height: 1.6; color: #CBD5E1; margin: 0 0 16px 0;">
                        Provider <strong>{{ $providerName }}</strong> has reached 100% of its daily token budget ({{ number_format($currentTokens) }} tokens). The system has automatically shifted execution to <strong>{{ $nextProviderName ?? 'the next active fallback gateway' }}</strong>.
                    </p>
                @else
                    <div class="alert-badge alert-warning">Approaching Free Token Limit</div>
                    <h2 style="margin: 0 0 12px 0; color: #FFFFFF;">AI Provider Approaching Daily Token Limit</h2>
                    <p style="font-size: 14px; line-height: 1.6; color: #CBD5E1; margin: 0 0 16px 0;">
                        Provider <strong>{{ $providerName }}</strong> has consumed <strong>{{ $usagePercentage }}%</strong> of its daily token threshold. Nexovant's circuit breaker and rotation policy will switch automatically to the next available provider when the limit is reached.
                    </p>
                @endif

                <table class="stats-table">
                    <tr>
                        <td style="color: #9CA3AF; width: 40%;">Provider / Gateway</td>
                        <td style="color: #FFFFFF; font-weight: 600;">{{ $providerName }} ({{ $providerSlug }})</td>
                    </tr>
                    <tr>
                        <td style="color: #9CA3AF;">Credential Key</td>
                        <td style="color: #FFFFFF; font-family: monospace;">{{ $credentialLabel }}</td>
                    </tr>
                    <tr>
                        <td style="color: #9CA3AF;">Daily Consumption</td>
                        <td style="color: #FFFFFF; font-weight: 600;">{{ number_format($currentTokens) }} / {{ number_format($dailyTokenBudget) }} tokens</td>
                    </tr>
                    <tr>
                        <td style="color: #9CA3AF;">Current Capacity</td>
                        <td style="color: {{ $usagePercentage >= 95 ? '#EF4444' : '#F59E0B' }}; font-weight: 700;">{{ $usagePercentage }}% utilized</td>
                    </tr>
                </table>
            </div>
            <div class="footer">
                Automated alert dispatched to operator <code>abdullahalnahian0@gmail.com</code> per platform rotation policy.
            </div>
        </div>
    </div>
</body>
</html>
