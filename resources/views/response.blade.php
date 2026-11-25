@extends('apilogger::layouts.app')

@section('content')

<a href="{{ route('apilogger.show', $log['log_id'] ?? $log['id'] ?? '') }}" class="back-link">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
    </svg>
    Back to Log Details
</a>

@php
    $response = $log['response'] ?? null;
    $prettyResponse = null;
    if ($response) {
        $decoded = json_decode($response, true);
        $prettyResponse = $decoded !== null ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $response;
    }
@endphp

<div class="card mb-4">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <div>
            <h1 style="font-size:20px;font-weight:700;margin:0;">Response Only</h1>
            <p class="text-muted" style="font-size:13px;margin-top:4px;">Log ID: {{ $log['log_id'] ?? $log['id'] ?? '—' }}</p>
        </div>
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
