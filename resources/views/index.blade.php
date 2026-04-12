@extends('apilogger::layouts.app')

@php
    function methodClass(string $method): string {
        return 'method method-' . strtoupper($method);
    }
    function statusClass(?int $code): string {
        if (!$code) return 'status status-null';
        return match(true) {
            $code < 300 => 'status status-2xx',
            $code < 400 => 'status status-3xx',
            $code < 500 => 'status status-4xx',
            default     => 'status status-5xx',
        };
    }
    function durationClass(?float $ms): string {
        if ($ms === null) return 'duration';
        return 'duration ' . match(true) {
            $ms < 200  => 'duration-fast',
            $ms < 1000 => 'duration-medium',
            default    => 'duration-slow',
        };
    }
@endphp

@section('content')

{{-- Header row --}}
<div class="flex items-center gap-3 mb-6" style="justify-content:space-between;">
    <div>
        <h1 style="font-size:20px;font-weight:700;">API Logs</h1>
        <p class="text-muted" style="font-size:13px;margin-top:2px;">{{ $logs->total() }} total entries</p>
    </div>
    <form method="POST" action="{{ route('apilogger.clear') }}" onsubmit="return confirm('Clear ALL logs permanently?')">
        @csrf
        <button type="submit" class="btn btn-danger btn-sm">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m2 0H7m4-3h2a1 1 0 011 1v1H9V5a1 1 0 011-1z"/>
            </svg>
            Clear Logs
        </button>
    </form>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('apilogger.index') }}" class="filters">
            <div>
                <label>Method</label>
                <select name="method">
                    <option value="">All Methods</option>
                    @foreach(['GET','POST','PUT','PATCH','DELETE'] as $m)
                        <option value="{{ $m }}" @selected(($filters['method'] ?? '') === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Status Code</label>
                <select name="status_code">
                    <option value="">All Statuses</option>
                    @foreach([200,201,204,400,401,403,404,422,500,503] as $s)
                        <option value="{{ $s }}" @selected(($filters['status_code'] ?? '') == $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>URL contains</label>
                <input type="text" name="url" value="{{ $filters['url'] ?? '' }}" placeholder="/api/...">
            </div>
            <div>
                <label>&nbsp;</label>
                <div class="flex gap-3">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('apilogger.index') }}" class="btn btn-ghost">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card">
    @if($logs->isEmpty())
        <div class="empty">
            <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p>No log entries found.</p>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Method</th>
                        <th>Status</th>
                        <th>URL</th>
                        <th>Duration</th>
                        <th>Controller</th>
                        <th>Models</th>
                        <th>User</th>
                        <th>Time</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                    @php
                        $entry = is_array($log) ? $log : $log->toArray();
                        $id    = $entry['log_id'] ?? $entry['id'] ?? null;
                    @endphp
                    <tr>
                        <td><span class="{{ methodClass($entry['method'] ?? 'GET') }}">{{ $entry['method'] ?? '?' }}</span></td>
                        <td><span class="{{ statusClass($entry['status_code'] ?? null) }}">{{ $entry['status_code'] ?? '—' }}</span></td>
                        <td class="url-cell" title="{{ $entry['url'] ?? '' }}">{{ $entry['url'] ?? '' }}</td>
                        <td><span class="{{ durationClass($entry['duration'] ?? null) }}">{{ isset($entry['duration']) ? $entry['duration'] . ' ms' : '—' }}</span></td>
                        <td class="text-muted">{{ $entry['controller'] ?? '—' }}{{ isset($entry['action']) ? '@'.$entry['action'] : '' }}</td>
                        <td class="text-muted" style="font-size:12px;">{{ $entry['models'] ?? '—' }}</td>
                        <td class="text-muted">{{ $entry['user_id'] ?? '—' }}</td>
                        <td class="text-muted" style="font-size:12px;white-space:nowrap;">{{ $entry['logged_at'] ?? '' }}</td>
                        <td>
                            @if($id)
                                <a href="{{ route('apilogger.show', $id) }}" class="btn btn-ghost btn-sm">View</a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($logs->hasPages())
        <div class="pagination">
            {{ $logs->appends($filters)->links() }}
        </div>
        @endif
    @endif
</div>

@endsection
