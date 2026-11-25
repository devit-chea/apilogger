@extends('apilogger::layouts.app')

@section('content')

<div class="flex items-center gap-3" style="justify-content:space-between;flex-wrap:wrap;">
    <a href="{{ route('apilogger.index') }}" class="back-link">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to Logs
    </a>
    <a href="{{ route('apilogger.response', $log['log_id'] ?? $log['id'] ?? $log['id'] ?? '') }}" class="btn btn-primary btn-sm">Response Only</a>
</div>

@php
    $method = strtoupper($log['method'] ?? 'GET');
    $status = $log['status_code'] ?? null;
    $statusClass = match(true) {
        !$status        => 'status status-null',
        $status < 300   => 'status status-2xx',
        $status < 400   => 'status status-3xx',
        $status < 500   => 'status status-4xx',
        default         => 'status status-5xx',
    };
    $methodClass = 'method method-' . $method;

    $payload  = $log['payload'] ?? null;
    $response = $log['response'] ?? null;

    // Pretty-print JSON if possible
    $prettyPayload = null;
    if ($payload) {
        $decoded = json_decode($payload, true);
        $prettyPayload = $decoded !== null ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $payload;
    }
    $prettyResponse = null;
    if ($response) {
        $decoded = json_decode($response, true);
        $prettyResponse = $decoded !== null ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $response;
    }

    $duration = $log['duration'] ?? null;
    $durationClass = 'duration ' . match(true) {
        $duration === null => '',
        $duration < 200    => 'duration-fast',
        $duration < 1000   => 'duration-medium',
        default            => 'duration-slow',
    };
@endphp

{{-- Title row --}}
<div class="flex items-center gap-3 mb-6" style="flex-wrap:wrap;">
    <span class="{{ $methodClass }}">{{ $method }}</span>
    <span class="{{ $statusClass }}">{{ $status ?? '—' }}</span>
    <span style="font-size:16px;font-weight:600;word-break:break-all;">{{ $log['url'] ?? '' }}</span>
    <span class="text-muted" style="margin-left:auto;font-size:13px;">{{ $log['logged_at'] ?? '' }}</span>
</div>

{{-- Meta grid --}}
<div class="card mb-4">
    <div class="card-header"><h2>Request Details</h2></div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item">
                <label>Log ID</label>
                <div class="value" style="font-family:monospace;font-size:12px;">{{ $log['log_id'] ?? $log['id'] ?? '—' }}</div>
            </div>
            <div class="detail-item">
                <label>IP Address</label>
                <div class="value">{{ $log['ip'] ?? '—' }}</div>
            </div>
            <div class="detail-item">
                <label>Duration</label>
                <div class="value"><span class="{{ $durationClass }}">{{ $duration !== null ? $duration . ' ms' : '—' }}</span></div>
            </div>
            <div class="detail-item">
                <label>User ID</label>
                <div class="value">{{ $log['user_id'] ?? 'Guest' }}</div>
            </div>
            <div class="detail-item">
                <label>Controller</label>
                <div class="value" style="font-family:monospace;">{{ $log['controller'] ?? '—' }}</div>
            </div>
            <div class="detail-item">
                <label>Action / Method</label>
                <div class="value" style="font-family:monospace;">{{ $log['action'] ?? '—' }}</div>
            </div>
            <div class="detail-item" style="grid-column:1/-1;">
                <label>Models Retrieved</label>
                <div class="value">
                    @if(!empty($log['models']))
                        @foreach(explode(',', $log['models']) as $model)
                            <span style="display:inline-block;background:var(--surface2);border:1px solid var(--border);border-radius:4px;padding:1px 8px;font-size:12px;margin:2px 2px 2px 0;font-family:monospace;">{{ trim($model) }}</span>
                        @endforeach
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Payload --}}
<div class="card mb-4">
    <div class="card-header">
        <h2>Request Payload</h2>
        @if($prettyPayload)
            <button class="btn btn-ghost btn-sm" onclick="copyToClipboard('payload-block')">Copy</button>
        @endif
    </div>
    <div class="card-body" style="padding:0;">
        @if($prettyPayload)
            <pre class="code-block" id="payload-block" style="border:none;border-radius:0;">{{ $prettyPayload }}</pre>
        @else
            <div style="padding:20px;" class="text-muted">No request payload.</div>
        @endif
    </div>
</div>

{{-- Response --}}
<div class="card mb-4">
    <div class="card-header">
        <h2>Response Body</h2>
        @if($prettyResponse)
            <button class="btn btn-ghost btn-sm" onclick="copyToClipboard('response-block')">Copy</button>
        @endif
    </div>
    <div class="card-body" style="padding:0;">
        @if($prettyResponse)
            <pre class="code-block" id="response-block" style="border:none;border-radius:0;">{{ $prettyResponse }}</pre>
        @elseif(config('apilogger.log_response'))
            <div style="padding:20px;" class="text-muted">Empty response body.</div>
        @else
            <div style="padding:20px;" class="text-muted">Response logging is disabled. Set <code>APILOGGER_LOG_RESPONSE=true</code> to enable.</div>
        @endif
    </div>
</div>

<script>
function copyToClipboard(id) {
    const el = document.getElementById(id);
    if (!el) return;
    navigator.clipboard.writeText(el.textContent).then(() => {
        const btn = event.target;
        const orig = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = orig, 1500);
    });
}
</script>

@endsection
