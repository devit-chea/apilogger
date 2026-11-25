<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>API Logger Dashboard</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #0f1117;
            --surface: #1a1d27;
            --surface2: #22263a;
            --border: #2e3250;
            --accent: #6c63ff;
            --accent-hover: #7d75ff;
            --text: #e2e8f0;
            --text-muted: #8892a4;
            --green: #22c55e;
            --yellow: #eab308;
            --red: #ef4444;
            --blue: #3b82f6;
            --orange: #f97316;
            --radius: 8px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            font-size: 14px;
            line-height: 1.5;
        }

        /* ── NAV ── */
        nav {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            height: 56px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        nav .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: 15px;
            color: var(--text);
            text-decoration: none;
        }
        nav .logo svg { color: var(--accent); }
        nav .spacer { flex: 1; }
        nav .badge {
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 12px;
        }

        /* ── LAYOUT ── */
        .container { max-width: 1280px; margin: 0 auto; padding: 24px 24px; }

        /* ── ALERTS ── */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            border: 1px solid;
        }
        .alert-success { background: rgba(34,197,94,.1); border-color: rgba(34,197,94,.3); color: var(--green); }
        .alert-danger  { background: rgba(239,68,68,.1); border-color: rgba(239,68,68,.3); color: var(--red); }

        /* ── CARDS ── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .card-header h2 { font-size: 14px; font-weight: 600; }
        .card-body { padding: 20px; }

        /* ── FILTERS ── */
        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
        }
        .filters label { display: block; font-size: 11px; color: var(--text-muted); margin-bottom: 4px; text-transform: uppercase; letter-spacing: .05em; }
        .filters input, .filters select {
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 8px 12px;
            border-radius: var(--radius);
            font-size: 13px;
            outline: none;
            transition: border-color .15s;
        }
        .filters input:focus, .filters select:focus { border-color: var(--accent); }
        .filters input { min-width: 200px; }

        /* ── BUTTONS ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: background .15s, opacity .15s;
        }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-ghost { background: var(--surface2); color: var(--text); border: 1px solid var(--border); }
        .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
        .btn-danger { background: rgba(239,68,68,.15); color: var(--red); border: 1px solid rgba(239,68,68,.3); }
        .btn-danger:hover { background: rgba(239,68,68,.25); }
        .btn-sm { padding: 5px 10px; font-size: 12px; }

        /* ── TABLE ── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left;
            padding: 10px 14px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            color: var(--text);
            vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(108,99,255,.04); }
        .url-cell { max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* ── METHOD BADGES ── */
        .method {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
            min-width: 48px;
        }
        .method-GET    { background: rgba(34,197,94,.15);  color: var(--green); }
        .method-POST   { background: rgba(59,130,246,.15); color: var(--blue); }
        .method-PUT    { background: rgba(249,115,22,.15); color: var(--orange); }
        .method-PATCH  { background: rgba(234,179,8,.15);  color: var(--yellow); }
        .method-DELETE { background: rgba(239,68,68,.15);  color: var(--red); }

        /* ── STATUS BADGES ── */
        .status {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-2xx { background: rgba(34,197,94,.12); color: var(--green); }
        .status-3xx { background: rgba(59,130,246,.12); color: var(--blue); }
        .status-4xx { background: rgba(234,179,8,.12);  color: var(--yellow); }
        .status-5xx { background: rgba(239,68,68,.12);  color: var(--red); }
        .status-null { background: var(--surface2); color: var(--text-muted); }

        /* ── DURATION ── */
        .duration { font-variant-numeric: tabular-nums; font-size: 12px; }
        .duration-fast   { color: var(--green); }
        .duration-medium { color: var(--yellow); }
        .duration-slow   { color: var(--red); }

        /* ── EMPTY STATE ── */
        .empty {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .empty svg { margin: 0 auto 16px; display: block; opacity: .4; }
        .empty p { font-size: 15px; }

        /* ── PAGINATION ── */
        .pagination { display: flex; gap: 4px; align-items: center; padding: 16px 20px; border-top: 1px solid var(--border); }
        .pagination a, .pagination span {
            padding: 6px 10px;
            border-radius: var(--radius);
            font-size: 13px;
            text-decoration: none;
            color: var(--text-muted);
            border: 1px solid transparent;
        }
        .pagination a:hover { background: var(--surface2); color: var(--text); }
        .pagination .active span { background: var(--accent); color: #fff; border-color: var(--accent); }
        .pagination .disabled span { opacity: .4; }

        /* ── DETAIL PAGE ── */
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .detail-grid { grid-template-columns: 1fr; } }
        .detail-item label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em; display: block; margin-bottom: 4px; }
        .detail-item .value { color: var(--text); font-size: 14px; word-break: break-all; }
        .code-block {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 14px;
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-all;
            overflow-y: auto;
            max-height: 400px;
            color: #a6b0cf;
        }
        .back-link { color: var(--text-muted); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 20px; font-size: 13px; }
        .back-link:hover { color: var(--accent); }

        /* ── MISC ── */
        .text-muted { color: var(--text-muted); }
        .mb-4 { margin-bottom: 16px; }
        .mb-6 { margin-bottom: 24px; }
        .flex { display: flex; }
        .gap-3 { gap: 12px; }
        .items-center { align-items: center; }
    </style>
</head>
<body>

<nav>
    <a class="logo" href="{{ route('apilogger.index') }}">
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        API Logger
    </a>
    <a class="btn btn-ghost btn-sm" href="{{ route('apilogger.index') }}">Logs</a>
    <a class="btn btn-ghost btn-sm" href="{{ route('apilogger.rules.index') }}">Rules</a>
    <span class="spacer"></span>
    <span class="badge">{{ config('apilogger.driver', 'file') }} driver</span>
</nav>

<div class="container">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @yield('content')
</div>

</body>
</html>
