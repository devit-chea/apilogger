@extends('apilogger::layouts.app')

@php
    $editing  = $rule->exists;
    $keysText = old('keys_text', implode("\n", $rule->keys ?? []));
    $action   = old('action', $rule->action);
@endphp

@section('content')

<a href="{{ route('apilogger.rules.index') }}" class="back-link">
    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Back to rules
</a>

<h1 style="font-size:20px;font-weight:700;" class="mb-6">{{ $editing ? 'Edit rule' : 'Add rule' }}</h1>

@if($errors->any())
    <div class="alert alert-danger">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="card" style="max-width:720px;">
    <form method="POST" action="{{ $editing ? route('apilogger.rules.update', $rule) : route('apilogger.rules.store') }}" class="card-body rule-form">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="field">
            <label for="type">Applies to</label>
            <select name="type" id="type">
                <option value="response" @selected(old('type', $rule->type) === 'response')>Response body</option>
                <option value="payload"  @selected(old('type', $rule->type) === 'payload')>Request payload</option>
            </select>
        </div>

        <div class="field">
            <label for="pattern">Route name or path</label>
            <input type="text" name="pattern" id="pattern" value="{{ old('pattern', $rule->pattern) }}" required maxlength="255"
                   placeholder="endpoint.getDataByCode  or  api/v3/user*">
            <p class="hint">Matches the route name or the request path. <code>*</code> is a wildcard; <code>*</code> alone matches every route.</p>
        </div>

        <div class="field">
            <label>What to store</label>
            <div class="radio-group">
                <label><input type="radio" name="action" value="keep" @checked($action === 'keep')> Keep only these keys</label>
                <label><input type="radio" name="action" value="all"  @checked($action === 'all')> Store everything</label>
                <label><input type="radio" name="action" value="none" @checked($action === 'none')> Store nothing</label>
            </div>
        </div>

        <div class="field" id="keys-field">
            <label for="keys_text">Keys to keep</label>
            <textarea name="keys_text" id="keys_text" rows="6" placeholder="response&#10;uuid&#10;message&#10;data.status&#10;data.*.id">{{ $keysText }}</textarea>
            <p class="hint">One key per line, dot notation. <code>data.*.id</code> keeps the id of every item in <code>data</code>.</p>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="priority">Priority</label>
                <input type="number" name="priority" id="priority" value="{{ old('priority', $rule->priority) }}" min="0" max="100000" required>
                <p class="hint">Lower runs first. Use 1000 for a catch-all <code>*</code> rule.</p>
            </div>
            <div class="field">
                <label>&nbsp;</label>
                <div class="radio-group">
                    <label><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $rule->enabled))> Enabled</label>
                </div>
            </div>
        </div>

        <div class="field">
            <label for="note">Note</label>
            <input type="text" name="note" id="note" value="{{ old('note', $rule->note) }}" maxlength="255" placeholder="Why this rule exists">
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create rule' }}</button>
            <a href="{{ route('apilogger.rules.index') }}" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<style>
    .rule-form .field { margin-bottom: 18px; flex: 1; }
    .rule-form .field-row { display: flex; gap: 16px; flex-wrap: wrap; }
    .rule-form label { display: block; font-size: 11px; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .05em; }
    .rule-form input[type=text], .rule-form input[type=number], .rule-form select, .rule-form textarea {
        width: 100%; background: var(--surface2); border: 1px solid var(--border); color: var(--text);
        padding: 8px 12px; border-radius: var(--radius); font-size: 13px; outline: none; font-family: inherit;
    }
    .rule-form textarea { font-family: 'JetBrains Mono', 'Fira Code', monospace; }
    .rule-form input:focus, .rule-form select:focus, .rule-form textarea:focus { border-color: var(--accent); }
    .rule-form .radio-group { display: flex; gap: 18px; flex-wrap: wrap; padding-top: 6px; }
    .rule-form .radio-group label { text-transform: none; letter-spacing: 0; font-size: 13px; color: var(--text); display: inline-flex; align-items: center; gap: 6px; margin: 0; cursor: pointer; }
    .rule-form .hint { font-size: 12px; color: var(--text-muted); margin-top: 6px; }
</style>

<script>
    (function () {
        var field = document.getElementById('keys-field');
        function sync() {
            var checked = document.querySelector('input[name=action]:checked');
            field.style.display = (!checked || checked.value === 'keep') ? '' : 'none';
        }
        document.querySelectorAll('input[name=action]').forEach(function (el) { el.addEventListener('change', sync); });
        sync();
    })();
</script>

@endsection
