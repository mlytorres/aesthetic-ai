<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Your Beauty Roadmap — {{ $report['clinic_name'] }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #2c2c2c;
            background: #fff;
            line-height: 1.6;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 0;
            display: flex;
            flex-direction: column;
        }

        /* ── Cover stripe ── */
        .cover-stripe {
            background: #1a1a24;
            padding: 22px 20mm 18px 20mm;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .cover-eyebrow {
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 1.8px;
            color: #0E9E8E;
            margin-bottom: 6px;
        }

        .cover-title {
            font-size: 22px;
            font-weight: 700;
            color: #F5F0E8;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .cover-subtitle {
            font-size: 11px;
            color: #9B9B8E;
            margin-top: 4px;
        }

        .cover-meta {
            display: flex;
            gap: 24px;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid rgba(14, 158, 142, 0.25);
        }

        .cover-meta-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .cover-meta-label {
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #6b6b5e;
        }

        .cover-meta-value {
            font-size: 10px;
            color: #F5F0E8;
            font-weight: 500;
        }

        /* ── Body ── */
        .body {
            padding: 14px 20mm 16px 20mm;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* ── Greeting ── */
        .greeting {
            padding: 10px 14px;
            background: #fffdf7;
            border-left: 3px solid #0E9E8E;
            border-radius: 0 4px 4px 0;
        }

        .greeting p {
            font-size: 11px;
            color: #444;
            line-height: 1.65;
        }

        /* ── Section ── */
        .section {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .section-title {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #B8912B;
            padding-bottom: 4px;
            border-bottom: 1px solid #f0e8d4;
        }

        /* ── Score card ── */
        .score-card {
            background: linear-gradient(135deg, #fffdf7 0%, #fef9ec 100%);
            border: 1px solid #e8d9a8;
            border-radius: 6px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .score-circle {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #1a1a24;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .score-circle .score-num {
            font-size: 18px;
            font-weight: 700;
            color: #0E9E8E;
            line-height: 1;
        }

        .score-circle .score-denom {
            font-size: 7px;
            color: #6b6b5e;
            margin-top: 1px;
        }

        .score-body {
            flex: 1;
        }

        .score-label-row {
            display: flex;
            align-items: baseline;
            gap: 6px;
            margin-bottom: 3px;
        }

        .score-main-label {
            font-size: 12px;
            font-weight: 700;
            color: #2c2c2c;
        }

        .score-tier {
            font-size: 8.5px;
            background: #0E9E8E;
            color: #fff;
            padding: 1px 7px;
            border-radius: 999px;
            font-weight: 600;
        }

        .score-summary {
            font-size: 10px;
            color: #666;
            line-height: 1.55;
        }

        /* ── No score fallback ── */
        .no-score-card {
            background: #f9f9f9;
            border: 1px solid #e5e5e5;
            border-radius: 6px;
            padding: 10px 14px;
        }

        .no-score-card p {
            font-size: 10.5px;
            color: #666;
        }

        /* ── Proportion pills ── */
        .proportion-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .proportion-pill {
            background: #f5f5f5;
            border: 1px solid #e5e5e5;
            border-radius: 5px;
            padding: 5px 10px;
            flex: 1;
            min-width: 120px;
        }

        .proportion-pill .pill-label {
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #999;
            margin-bottom: 2px;
        }

        .proportion-pill .pill-value {
            font-size: 12px;
            font-weight: 700;
            color: #2c2c2c;
        }

        .proportion-pill .pill-note {
            font-size: 8.5px;
            color: #888;
            margin-top: 1px;
            line-height: 1.4;
        }

        /* ── Insights list ── */
        .insights-list {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .insight-item {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            padding: 6px 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }

        .insight-dot {
            width: 5px;
            height: 5px;
            background: #0E9E8E;
            border-radius: 50%;
            margin-top: 4px;
            flex-shrink: 0;
        }

        .insight-text {
            font-size: 10.5px;
            color: #333;
            line-height: 1.55;
        }

        /* ── FAQ ── */
        .faq-list {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .faq-item {
            border: 1px solid #eee;
            border-radius: 5px;
            overflow: hidden;
        }

        .faq-q {
            background: #f7f7f7;
            padding: 5px 10px;
            font-size: 10px;
            font-weight: 600;
            color: #333;
        }

        .faq-a {
            padding: 5px 10px;
            font-size: 10px;
            color: #555;
            line-height: 1.6;
        }

        /* ── Next steps ── */
        .steps-list {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .step-item {
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .step-num {
            width: 18px;
            height: 18px;
            background: #1a1a24;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            color: #0E9E8E;
            font-weight: 700;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .step-text {
            font-size: 10.5px;
            color: #333;
            line-height: 1.55;
        }

        /* ── Disclaimer ── */
        .disclaimer {
            margin-top: 6px;
            padding: 7px 10px;
            background: #f5f5f5;
            border-radius: 4px;
            font-size: 8px;
            color: #aaa;
            line-height: 1.5;
        }

        /* ── Footer ── */
        .footer {
            background: #f9f9f9;
            border-top: 1px solid #eee;
            padding: 8px 20mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-clinic {
            font-size: 8.5px;
            color: #888;
            font-weight: 600;
        }

        .footer-tagline {
            font-size: 7.5px;
            color: #bbb;
            font-style: italic;
        }

        .footer-date {
            font-size: 7.5px;
            color: #ccc;
        }
    </style>
</head>
<body>
<div class="page">

    {{-- ── COVER STRIPE ─────────────────────────────────────────────────────── --}}
    <div class="cover-stripe">
        <div class="cover-eyebrow">{{ $report['clinic_name'] }} &mdash; Personalised Report</div>
        <div class="cover-title">Your Beauty Roadmap</div>
        <div class="cover-subtitle">{{ $report['procedure_label'] }}</div>

        <div class="cover-meta">
            <div class="cover-meta-item">
                <div class="cover-meta-label">Prepared for</div>
                <div class="cover-meta-value">{{ ucfirst($report['first_name']) }}</div>
            </div>
            <div class="cover-meta-item">
                <div class="cover-meta-label">Report Date</div>
                <div class="cover-meta-value">{{ $report['generated_at'] }}</div>
            </div>
            <div class="cover-meta-item">
                <div class="cover-meta-label">Reference</div>
                <div class="cover-meta-value" style="font-family: monospace; font-size:9px; color: #6b6b5e;">{{ substr($report['secure_token'], 0, 12) }}...</div>
            </div>
        </div>
    </div>

    <div class="body">

        {{-- ── GREETING ──────────────────────────────────────────────────────── --}}
        <div class="greeting">
            <p>
                Hi {{ ucfirst($report['first_name']) }}, thank you for completing your aesthetic evaluation with
                <strong>{{ $report['clinic_name'] }}</strong>. Our AI system has reviewed your submission and prepared
                this personalised report to help you understand your results and feel confident heading into your
                consultation.
            </p>
        </div>

        {{-- ── AI HARMONY SCORE ──────────────────────────────────────────────── --}}
        <div class="section">
            <div class="section-title">Your AI Analysis Result</div>

            @if ($report['harmony_score'] !== null)
                <div class="score-card">
                    <div class="score-circle">
                        <div class="score-num">{{ $report['harmony_score'] }}</div>
                        <div class="score-denom">/ 100</div>
                    </div>
                    <div class="score-body">
                        <div class="score-label-row">
                            <div class="score-main-label">Harmony Score</div>
                            <div class="score-tier">{{ $report['harmony_label'] }}</div>
                        </div>
                        <div class="score-summary">{{ $report['harmony_summary'] }}</div>
                    </div>
                </div>
            @else
                <div class="no-score-card">
                    <p>{{ $report['harmony_summary'] }}</p>
                </div>
            @endif
        </div>

        {{-- ── PROPORTION HIGHLIGHTS ─────────────────────────────────────────── --}}
        @if (!empty($report['proportion_highlights']))
            <div class="section">
                <div class="section-title">Measurement Highlights</div>
                <div class="proportion-grid">
                    @foreach ($report['proportion_highlights'] as $highlight)
                        <div class="proportion-pill">
                            <div class="pill-label">{{ $highlight['label'] }}</div>
                            <div class="pill-value">{{ $highlight['value'] }}</div>
                            <div class="pill-note">{{ $highlight['note'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ── KEY INSIGHTS ──────────────────────────────────────────────────── --}}
        @if (!empty($report['key_insights']))
            <div class="section">
                <div class="section-title">What This Means for You</div>
                <div class="insights-list">
                    @foreach ($report['key_insights'] as $insight)
                        <div class="insight-item">
                            <div class="insight-dot"></div>
                            <div class="insight-text">{{ $insight }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ── FAQs ─────────────────────────────────────────────────────────── --}}
        @if (!empty($report['faqs']))
            <div class="section">
                <div class="section-title">Frequently Asked Questions</div>
                <div class="faq-list">
                    @foreach ($report['faqs'] as $faq)
                        <div class="faq-item">
                            <div class="faq-q">{{ $faq['q'] }}</div>
                            <div class="faq-a">{{ $faq['a'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ── NEXT STEPS ────────────────────────────────────────────────────── --}}
        <div class="section">
            <div class="section-title">Next Steps</div>
            <div class="steps-list">
                @foreach ($report['next_steps'] as $i => $step)
                    <div class="step-item">
                        <div class="step-num">{{ $i + 1 }}</div>
                        <div class="step-text">{{ $step }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ── DISCLAIMER ─────────────────────────────────────────────────────── --}}
        <div class="disclaimer">
            <strong>Important Notice:</strong> This report is generated by an AI system for informational and
            educational purposes only. It is not a medical diagnosis, clinical assessment, or treatment recommendation.
            All findings must be reviewed by a qualified, board-certified surgeon before any clinical decisions are made.
            Individual results vary. AI visualisations and scores are analytical tools only and do not guarantee
            any specific surgical outcome.
        </div>

    </div>

    {{-- ── FOOTER ──────────────────────────────────────────────────────────── --}}
    <div class="footer">
        <div class="footer-clinic">{{ $report['clinic_name'] }}</div>
        <div class="footer-tagline">Powered by SymetriHealth — Clinical Intelligence Platform</div>
        <div class="footer-date">{{ $report['generated_at'] }}</div>
    </div>

</div>
</body>
</html>
