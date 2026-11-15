<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — Nexovant Intelligence</title>
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
            padding: 24px 32px;
            border-bottom: 1px solid #1F2937;
            background: linear-gradient(180deg, #161F30 0%, #111827 100%);
        }
        .logo {
            font-size: 18px;
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
            letter-spacing: 0.05em;
            margin-top: 4px;
            text-transform: uppercase;
        }
        .content {
            padding: 32px;
        }
        .badge-row {
            margin-bottom: 16px;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-radius: 4px;
            margin-right: 8px;
        }
        .badge-urgent {
            background-color: rgba(239, 68, 68, 0.15);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .badge-high {
            background-color: rgba(245, 158, 11, 0.15);
            color: #F59E0B;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .badge-medium {
            background-color: rgba(0, 229, 255, 0.15);
            color: #00E5FF;
            border: 1px solid rgba(0, 229, 255, 0.3);
        }
        .badge-low {
            background-color: rgba(156, 163, 175, 0.15);
            color: #9CA3AF;
            border: 1px solid rgba(156, 163, 175, 0.3);
        }
        .badge-category {
            background-color: #1F2937;
            color: #E2E8F0;
            border: 1px solid #374151;
        }
        h1 {
            font-size: 20px;
            line-height: 1.4;
            font-weight: 700;
            color: #FFFFFF;
            margin: 0 0 16px 0;
        }
        p {
            font-size: 14px;
            line-height: 1.6;
            color: #D1D5DB;
            margin: 0 0 24px 0;
        }
        .action-button {
            display: inline-block;
            background-color: #00ADB5;
            color: #FFFFFF !important;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .metadata-box {
            background-color: #0D131F;
            border: 1px solid #1F2937;
            border-radius: 6px;
            padding: 16px;
            margin: 24px 0;
            font-size: 12px;
            color: #9CA3AF;
        }
        .footer {
            padding: 24px 32px;
            border-top: 1px solid #1F2937;
            background-color: #0A0E17;
            font-size: 11px;
            line-height: 1.6;
            color: #6B7280;
            text-align: center;
        }
        .footer a {
            color: #9CA3AF;
            text-decoration: underline;
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
                <span class="tagline">Intelligence Beyond Headlines</span>
            </div>

            <div class="content">
                <div class="badge-row">
                    @php
                        $priorityClass = match($priority) {
                            'urgent' => 'badge-urgent',
                            'high' => 'badge-high',
                            'low' => 'badge-low',
                            default => 'badge-medium',
                        };
                    @endphp
                    <span class="badge {{ $priorityClass }}">{{ strtoupper($priority) }}</span>
                    @if(!empty($category))
                        <span class="badge badge-category">{{ strtoupper(str_replace('_', ' ', $category)) }}</span>
                    @endif
                </div>

                <h1>{{ $title }}</h1>
                <p>{{ $notificationMessage }}</p>

                @if(!empty($metadata) && is_array($metadata))
                    <div class="metadata-box">
                        @foreach($metadata as $k => $v)
                            @if(is_scalar($v))
                                <div><strong style="color: #D1D5DB;">{{ ucwords(str_replace('_', ' ', (string) $k)) }}:</strong> {{ (string) $v }}</div>
                            @endif
                        @endforeach
                    </div>
                @endif

                @if(!empty($actionUrl))
                    <div style="margin-top: 28px;">
                        <a href="{{ $actionUrl }}" class="action-button">View Intelligence &rarr;</a>
                    </div>
                @endif
            </div>

            <div class="footer">
                <div>This confidential alert was dispatched to <strong>{{ $recipientEmail }}</strong> via Nexovant Communication Engine.</div>
                <div style="margin-top: 8px;">
                    <a href="{{ $preferencesUrl }}">Notification Preferences</a> &bull;
                    <a href="{{ $appUrl }}/workspace">Subscriber Workspace</a> &bull;
                    <a href="{{ $unsubscribeUrl }}">One-Click Unsubscribe</a>
                </div>
                <div style="margin-top: 8px; color: #4B5563;">
                    &copy; {{ date('Y') }} Nexovant Technologies Ltd. All rights reserved.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
