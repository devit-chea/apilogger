@extends('apilogger::layouts.app')

@php
    $actionLabels = ['keep' => 'Keep keys', 'all' => 'Store everything', 'none' => 'Store nothing'];
    $configValue = function ($keys) {
        if ($keys === false) return 'Store nothing';
        if ($keys === null || $keys === [] || $keys === ['*']) return 'Store everything';
        return implode(', ', (array) $keys);
    };
@endphp

@section('content')

<a href="{{ route('apilogger.index') }}" class="back-link">
    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Back to logs
</a>

<div class="flex items-center gap-3 mb-6" style="justify-content:space-between;flex-wrap:wrap;">
    <div>
        <h1 style="font-size:20px;font-weight:700;">Storage Rules</h1>
        <p class="text-muted" style="font-size:13px;margin-top:2px;">
            Choose which payload and response keys are stored for each route. The first matching rule wins.
        </p>
    </div>
    @if($databaseEnabled)
        <div class="flex gap-3">
            <form method="POST" action="{{ route('apilogger.rules.import') }}"
                  onsubmit="return confirm('Copy the rules from config/apilogger.php into the database? Existing rules with the same pattern are kept.')">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm">Import from config</button>
            </form>
            <a href="{{ route('apilogger.rules.create') }}" class="btn btn-primary btn-sm">+ Add rule</a>
        </div>
    @endif
</div>

@unless($databaseEnabled)
    <div class="alert alert-danger">
        Database rules are turned off, so this page is read-only. Set <code>APILOGGER_DB_RULES=true</code>
        (config <code>apilogger.rules.database</code>) and run the migrations to manage rules here.
    </div>
@endunless

@foreach(['response' => 'Response body', 'payload' => 'Request payload'] as $type => $title)
    <div class="card mb-6">
        <div class="card-header">
            <h2>{{ $title }} rules</h2>
            @if($databaseEnabled)
                <a href="{{ route('apilogger.rules.create', ['type' => $type]) }}" class="btn btn-ghost btn-sm">+ Add {{ $type }} rule</a>
            @endif
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Priority</th>
                        <th>Route name / path</th>
                        <th>Action</th>
                        <th>Keys kept</th>
                        <th>Status</th>
                        <th>Note</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($rules[$type] ?? collect()) as $rule)
                        <tr style="{{ $rule->enabled ? '' : 'opacity:.5;' }}">
                            <td class="text-muted">{{ $rule->priority }}</td>
                            <td><code>{{ $rule->pattern }}</code></td>
                            <td>{{ $actionLabels[$rule->action] ?? $rule->action }}</td>
                            <td class="url-cell" title="{{ implode(', ', $rule->keys ?? []) }}">
                                {{ $rule->action === 'keep' ? implode(', ', $rule->keys ?? []) : '—' }}
                            </td>
                            <td>
                                <span class="status {{ $rule->enabled ? 'status-2xx' : 'status-null' }}">{{ $rule->enabled ? 'Enabled' : 'Disabled' }}</span>
                            </td>
                            <td class="text-muted url-cell">{{ $rule->note }}</td>
                            <td style="white-space:nowrap;text-align:right;">
                                @if($databaseEnabled)
                                    <a href="{{ route('apilogger.rules.edit', $rule) }}" class="btn btn-ghost btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('apilogger.rules.destroy', $rule) }}" style="display:inline;"
                                          onsubmit="return confirm('Delete this rule?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach

                    @foreach($configRules[$type] as $pattern => $keys)
                        <tr>
                            <td class="text-muted">config</td>
                            <td><code>{{ $pattern }}</code></td>
                            <td colspan="2" class="url-cell">{{ $configValue($keys) }}</td>
                            <td><span class="status status-null">Config file</span></td>
                            <td class="text-muted">Edit in config/apilogger.php</td>
                            <td></td>
                        </tr>
                    @endforeach

                    <tr>
                        <td class="text-muted">default</td>
                        <td><code>all other routes</code></td>
                        <td colspan="2" class="url-cell">{{ $configValue($globalKeys[$type] === [] ? null : $globalKeys[$type]) }}</td>
                        <td><span class="status status-null">Config file</span></td>
                        <td class="text-muted">{{ $type }}_keys in config/apilogger.php</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endforeach

<div class="card">
    <div class="card-body text-muted" style="font-size:13px;">
        <p class="mb-4"><strong style="color:var(--text);">How matching works.</strong>
            Database rules are checked by priority (lowest first), then rules from the config file, then the default.
            A pattern matches the route name (e.g. <code>endpoint.getDataByCode</code>) or the path (e.g. <code>api/v3/user*</code>); <code>*</code> is a wildcard.
            Use pattern <code>*</code> with a high priority number (e.g. 1000) as a default for all routes.</p>
        <p><strong style="color:var(--text);">Keys</strong> use dot notation: <code>data.status</code>, and <code>data.*.id</code> keeps the id of every item.
            Hidden fields stay masked and excluded response keys are still removed. Changes apply to new log entries within a few seconds.</p>
    </div>
</div>

@endsection
