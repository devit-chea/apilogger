<?php

namespace GDI\ApiLogger\Http\Controllers;

use GDI\ApiLogger\Models\ApiLoggerRule;
use GDI\ApiLogger\Rules\RuleRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Dashboard pages for managing payload/response storage rules.
 */
class ApiLoggerRuleController extends Controller
{
    public function __construct(protected RuleRepository $rules) {}

    public function index()
    {
        return view('apilogger::rules.index', [
            'rules'           => ApiLoggerRule::ordered()->get()->groupBy('type'),
            'databaseEnabled' => $this->rules->databaseEnabled(),
            'configRules'     => [
                'payload'  => (array) config('apilogger.payload_rules', []),
                'response' => (array) config('apilogger.response_rules', []),
            ],
            'globalKeys'      => [
                'payload'  => (array) config('apilogger.payload_keys', []),
                'response' => (array) config('apilogger.response_keys', []),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $rule = new ApiLoggerRule([
            'type'     => in_array($request->query('type'), ApiLoggerRule::TYPES, true) ? $request->query('type') : 'response',
            'action'   => 'keep',
            'priority' => 100,
            'enabled'  => true,
        ]);

        return view('apilogger::rules.form', compact('rule'));
    }

    public function store(Request $request)
    {
        ApiLoggerRule::create($this->validated($request));

        return redirect()->route('apilogger.rules.index')->with('success', 'Rule created.');
    }

    public function edit(ApiLoggerRule $rule)
    {
        return view('apilogger::rules.form', compact('rule'));
    }

    public function update(Request $request, ApiLoggerRule $rule)
    {
        $rule->update($this->validated($request));

        return redirect()->route('apilogger.rules.index')->with('success', 'Rule updated.');
    }

    public function destroy(ApiLoggerRule $rule)
    {
        $rule->delete();

        return redirect()->route('apilogger.rules.index')->with('success', 'Rule deleted.');
    }

    public function import()
    {
        $created = $this->rules->importFromConfig();

        return redirect()->route('apilogger.rules.index')
            ->with('success', "Imported {$created} rule(s) from config. Rules with the same type and pattern were skipped.");
    }

    /**
     * Validate the form. Keys come from a textarea, one per line (commas also work).
     */
    protected function validated(Request $request): array
    {
        $request->merge([
            'keys'    => collect(preg_split('/[\r\n,]+/', (string) $request->input('keys_text', '')))
                ->map(fn ($key) => trim($key))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'enabled' => $request->boolean('enabled'),
        ]);

        $data = $request->validate([
            'type'     => ['required', Rule::in(ApiLoggerRule::TYPES)],
            'pattern'  => ['required', 'string', 'max:255'],
            'action'   => ['required', Rule::in(ApiLoggerRule::ACTIONS)],
            'keys'     => ['array', 'max:100', Rule::requiredIf($request->input('action') === 'keep')],
            'keys.*'   => ['string', 'max:255', 'regex:/^[^.\s]+(\.[^.\s]+)*$/'],
            'priority' => ['required', 'integer', 'min:0', 'max:100000'],
            'enabled'  => ['boolean'],
            'note'     => ['nullable', 'string', 'max:255'],
        ], [
            'keys.required'  => 'Add at least one key, or choose "Store everything" / "Store nothing".',
            'keys.*.regex'   => 'Keys use dot notation without spaces, e.g. data.status or data.*.id.',
        ]);

        $data['pattern'] = trim($data['pattern']);
        $data['keys']    = $data['action'] === 'keep' ? $data['keys'] : null;

        return $data;
    }
}
