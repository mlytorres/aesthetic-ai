<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Clinical Brief — {{ $evaluation->procedure_slug }}</title>
    <style>
        /* ── Reset & base ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1a1a1a;
            background: #fff;
            line-height: 1.4;
        }

        /* ── Page layout ── */
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 10mm 12mm 10mm 12mm;
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        /* ── Header / branding ── */
        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            padding-bottom: 8px;
            border-bottom: 2px solid #C9A84C;
            margin-bottom: 8px;
        }

        .header-left .clinic-name {
            font-size: 16px;
            font-weight: 700;
            color: #111;
            letter-spacing: -0.3px;
        }

        .header-left .doc-type {
            font-size: 10px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 2px;
        }

        .header-right {
            text-align: right;
        }

        .header-right .generated {
            font-size: 9px;
            color: #aaa;
        }

        .header-right .eval-id {
            font-size: 9px;
            color: #bbb;
            font-family: monospace;
            margin-top: 2px;
        }

        /* ── Score banner ── */
        .score-banner {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
        }

        .score-box {
            flex: 1;
            border-radius: 6px;
            padding: 8px 12px;
            text-align: center;
        }

        .score-box.lead {
            background: #fdf8ee;
            border: 1px solid #C9A84C;
        }

        .score-box.priority {
            background: #f8f8f8;
            border: 1px solid #ddd;
        }

        .score-box.status {
            background: #f8f8f8;
            border: 1px solid #ddd;
        }

        .score-box .label {
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #999;
            margin-bottom: 2px;
        }

        .score-box .value {
            font-size: 18px;
            font-weight: 700;
            color: #111;
        }

        .score-box.lead .value { color: #B8912B; }

        .score-box .sub {
            font-size: 8px;
            color: #bbb;
            margin-top: 1px;
        }

        /* ── Section card ── */
        .card {
            border: 1px solid #e5e5e5;
            border-radius: 6px;
            margin-bottom: 8px;
            overflow: hidden;
            page-break-inside: avoid;
        }

        .card-header {
            background: #f5f5f5;
            padding: 4px 8px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #555;
            border-bottom: 1px solid #e5e5e5;
        }

        .card-body {
            padding: 6px 8px;
        }

        /* ── Grid layouts ── */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 14px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px 10px; }

        .field-label {
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #999;
            margin-bottom: 1px;
        }

        .field-value {
            font-size: 11px;
            color: #111;
            font-weight: 500;
        }

        .field-value.muted { color: #aaa; font-weight: 400; font-style: italic; }

        /* ── Priority badge ── */
        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 999px;
            font-size: 9px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .badge-urgent  { background: #fee2e2; color: #b91c1c; }
        .badge-high    { background: #fef3c7; color: #92400e; }
        .badge-medium  { background: #dbeafe; color: #1e40af; }
        .badge-standard{ background: #f3f4f6; color: #374151; }
        .badge-status  { background: #f3f4f6; color: #374151; }

        /* ── Quiz answers table ── */
        .qa-table {
            width: 100%;
            border-collapse: collapse;
        }

        .qa-table tr:not(:last-child) td {
            border-bottom: 1px solid #f0f0f0;
        }

        .qa-table td {
            padding: 4px 0;
            vertical-align: top;
        }

        .qa-table .q-col {
            width: 42%;
            color: #666;
            font-size: 10px;
            padding-right: 12px;
        }

        .qa-table .a-col {
            color: #111;
            font-size: 10.5px;
            font-weight: 500;
        }

        /* ── AI analysis ── */
        .analysis-text {
            font-size: 10.5px;
            color: #333;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .analysis-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 6px;
        }

        .pill {
            background: #f5f5f5;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 2px 7px;
            font-size: 9.5px;
            color: #444;
        }

        /* ── Photos grid ── */
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
        }

        .photo-item { text-align: center; }

        .photo-item img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #e5e5e5;
            display: block;
        }

        .photo-label {
            font-size: 8px;
            color: #aaa;
            margin-top: 3px;
            text-transform: capitalize;
        }

        /* ── Notes ── */
        .notes-text {
            font-size: 10.5px;
            color: #333;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        /* ── Footer ── */
        .footer {
            margin-top: auto;
            padding-top: 8px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-left {
            font-size: 8px;
            color: #ccc;
        }

        .footer-right {
            font-size: 8px;
            color: #ccc;
            font-style: italic;
        }

        .confidential {
            font-size: 8px;
            color: #bbb;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
    </style>
</head>
<body>
<div class="page">

    {{-- ── HEADER ─────────────────────────────────────────────────────────── --}}
    <div class="header">
        <div class="header-left">
            <div class="clinic-name">{{ $evaluation->tenant?->name ?? config('app.name') }}</div>
            <div class="doc-type">Clinical Brief &mdash; Confidential</div>
        </div>
        <div class="header-right">
            <div class="generated">Generated {{ now()->format('M j, Y \a\t g:i A T') }}</div>
            <div class="eval-id">ID: {{ $evaluation->id }}</div>
        </div>
    </div>

    {{-- ── SCORE BANNER ─────────────────────────────────────────────────── --}}
    <div class="score-banner">
        <div class="score-box lead">
            <div class="label">Lead Score</div>
            <div class="value">{{ $evaluation->lead_score ?? '—' }}</div>
            <div class="sub">out of 100</div>
        </div>
        <div class="score-box priority">
            <div class="label">Priority</div>
            <div class="value" style="font-size:13px; margin-top: 3px;">
                <span class="badge badge-{{ $evaluation->priority }}">
                    {{ $evaluation->priority }}
                </span>
            </div>
        </div>
        <div class="score-box status">
            <div class="label">Status</div>
            <div class="value" style="font-size:13px; margin-top: 3px;">
                <span class="badge badge-status">
                    {{ str_replace('_', ' ', $evaluation->status) }}
                </span>
            </div>
        </div>
        <div class="score-box" style="background:#f8f8f8; border:1px solid #ddd;">
            <div class="label">Procedure</div>
            <div class="value" style="font-size:12px; margin-top:4px; text-transform: capitalize;">
                {{ str_replace('-', ' ', $evaluation->procedure_slug) }}
            </div>
        </div>
    </div>

    {{-- ── PATIENT INFORMATION ──────────────────────────────────────────── --}}
    <div class="card">
        <div class="card-header">Patient Information</div>
        <div class="card-body">
            @if ($evaluation->patient)
                <div class="grid-3">
                    <div>
                        <div class="field-label">Full Name</div>
                        {{-- Patient PHI is stored encrypted; the 'encrypted' cast auto-decrypts on access --}}
                        <div class="field-value">{{ $evaluation->patient->name_encrypted ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="field-label">Email</div>
                        <div class="field-value">{{ $evaluation->patient->email_encrypted ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="field-label">Phone</div>
                        <div class="field-value">{{ $evaluation->patient->phone_encrypted ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="field-label">Submitted</div>
                        <div class="field-value">
                            {{ $evaluation->completed_at
                                ? $evaluation->completed_at->format('M j, Y')
                                : $evaluation->created_at->format('M j, Y') }}
                        </div>
                    </div>
                    @if ($evaluation->follow_up_at)
                        <div>
                            <div class="field-label">Follow-up Date</div>
                            <div class="field-value">{{ $evaluation->follow_up_at->format('M j, Y') }}</div>
                        </div>
                    @endif
                </div>
            @else
                <span class="field-value muted">Patient record not available.</span>
            @endif
        </div>
    </div>

    {{-- ── PHOTOS ───────────────────────────────────────────────────────── --}}
    @if (!empty($photoData))
        <div class="card">
            <div class="card-header">Patient Photos ({{ count($photoData) }})</div>
            <div class="card-body">
                <div class="photos-grid">
                    @foreach (array_slice($photoData, 0, 8) as $photo)
                        <div class="photo-item">
                            <img
                                src="{{ $photo['signed_url'] }}"
                                alt="{{ $photo['type'] }}"
                                onerror="this.style.display='none'"
                            />
                            <div class="photo-label">{{ str_replace('_', ' ', $photo['type']) }}</div>
                            @if (!empty($photo['quality_score']))
                                <div class="photo-label">Q: {{ $photo['quality_score'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- ── QUIZ ANSWERS ─────────────────────────────────────────────────── --}}
    @if ($evaluation->quiz_answers && count($evaluation->quiz_answers))
        @php
            $answers = collect($evaluation->quiz_answers)
                ->reject(fn ($v, $k) => str_starts_with((string) $k, '_'))
                ->all();
        @endphp
        @if (count($answers))
            <div class="card">
                <div class="card-header">Quiz Answers</div>
                <div class="card-body">
                    <table class="qa-table">
                        @foreach ($answers as $question => $answer)
                            <tr>
                                <td class="q-col">{{ ucwords(str_replace('_', ' ', $question)) }}</td>
                                <td class="a-col">
                                    @if (is_array($answer))
                                        {{ implode(', ', $answer) }}
                                    @else
                                        {{ $answer ?? '—' }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        @endif
    @endif

    {{-- ── AI ANALYSIS ─────────────────────────────────────────────────── --}}
    @if ($evaluation->analysis_data && count($evaluation->analysis_data))
        @php
            $analysis    = $evaluation->analysis_data;
            $proportions = $analysis['proportions'] ?? [];
            $recs        = $analysis['recommendations'] ?? [];
        @endphp

        {{-- Proportion scores --}}
        @if (!empty($proportions))
            <div class="card">
                <div class="card-header">Facial Proportion Analysis</div>
                <div class="card-body">
                    <div class="grid-3">
                        @if (isset($proportions['overall_harmony']))
                            <div>
                                <div class="field-label">Overall Harmony</div>
                                <div class="field-value">{{ $proportions['overall_harmony'] }} / 100</div>
                            </div>
                        @endif
                        @if (isset($proportions['nasal_symmetry']['score']))
                            <div>
                                <div class="field-label">Nasal Symmetry</div>
                                <div class="field-value">{{ $proportions['nasal_symmetry']['score'] }} / 100</div>
                            </div>
                        @endif
                        @if (isset($proportions['eye_symmetry']['score']))
                            <div>
                                <div class="field-label">Eye Symmetry</div>
                                <div class="field-value">{{ $proportions['eye_symmetry']['score'] }} / 100</div>
                            </div>
                        @endif
                        @if (isset($proportions['nasal_projection']['goodes_ratio']))
                            <div>
                                <div class="field-label">Goode's Ratio</div>
                                <div class="field-value">{{ number_format($proportions['nasal_projection']['goodes_ratio'], 2) }}</div>
                            </div>
                        @endif
                        @if (isset($proportions['nasal_width_ratio']['ratio']))
                            <div>
                                <div class="field-label">Width / Intercanthal</div>
                                <div class="field-value">{{ number_format($proportions['nasal_width_ratio']['ratio'], 2) }}</div>
                            </div>
                        @endif
                        @if (isset($analysis['_face_attributes']['age_range']['midpoint']))
                            <div>
                                <div class="field-label">Est. Age</div>
                                <div class="field-value">~{{ $analysis['_face_attributes']['age_range']['midpoint'] }} yrs</div>
                            </div>
                        @endif
                        @if (isset($proportions['_avg_photo_quality']))
                            <div>
                                <div class="field-label">Avg Photo Quality</div>
                                <div class="field-value">{{ $proportions['_avg_photo_quality'] }} / 100</div>
                            </div>
                        @endif
                    </div>

                    @if (isset($proportions['facial_thirds']) && is_array($proportions['facial_thirds']))
                        @php $thirds = $proportions['facial_thirds']; @endphp
                        <div style="margin-top:8px; border-top:1px solid #f0f0f0; padding-top:6px;">
                            <div class="field-label" style="margin-bottom:4px;">Facial Thirds</div>
                            <div class="grid-3">
                                <div>
                                    <div class="field-label">Upper</div>
                                    <div class="field-value">{{ number_format($thirds['upper'] ?? 0, 1) }}%</div>
                                </div>
                                <div>
                                    <div class="field-label">Middle</div>
                                    <div class="field-value">{{ number_format($thirds['middle'] ?? 0, 1) }}%</div>
                                </div>
                                <div>
                                    <div class="field-label">Lower</div>
                                    <div class="field-value">{{ number_format($thirds['lower'] ?? 0, 1) }}%</div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Recommendations --}}
        @if (!empty($recs))
            <div class="card">
                <div class="card-header">AI Recommendations</div>
                <div class="card-body">
                    @foreach ($recs as $rec)
                        @php
                            $category   = is_array($rec) ? ($rec['category']   ?? null) : null;
                            $confidence = is_array($rec) ? ($rec['confidence']  ?? null) : null;
                            $note       = is_array($rec) ? ($rec['note'] ?? $rec['text'] ?? null) : (is_string($rec) ? $rec : null);
                        @endphp
                        <div style="padding:4px 0; border-bottom:1px solid #f5f5f5;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                                @if ($category)
                                    <span style="font-size:10px; font-weight:600; color:#333; text-transform:capitalize;">
                                        {{ str_replace('_', ' ', $category) }}
                                    </span>
                                @endif
                                @if ($confidence)
                                    <span class="pill" style="font-size:8.5px;">{{ ucfirst($confidence) }} confidence</span>
                                @endif
                            </div>
                            @if ($note)
                                <div style="font-size:10px; color:#555; line-height:1.5;">{{ $note }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    {{-- ── COORDINATOR NOTES ────────────────────────────────────────────── --}}
    @if ($evaluation->coordinator_notes)
        <div class="card">
            <div class="card-header">Coordinator Notes</div>
            <div class="card-body">
                <p class="notes-text">{{ $evaluation->coordinator_notes }}</p>
            </div>
        </div>
    @endif

    {{-- ── FOOTER ──────────────────────────────────────────────────────── --}}
    <div class="footer">
        <div class="footer-left">
            <span class="confidential">&#9632; Confidential — HIPAA Protected Health Information</span><br />
            This document is intended solely for authorized clinic staff. Unauthorized disclosure is prohibited.
        </div>
        <div class="footer-right">
            {{ $evaluation->tenant?->name ?? config('app.name') }} &bull; SymetriHealth Platform
        </div>
    </div>

</div>
</body>
</html>
