<?php
// Database-managed rules: migration, dashboard pages, import, validation, cache flush, logger effect.

use GDI\ApiLogger\Models\ApiLoggerRule;

$overrides = [
    'apilogger.rules.database' => true,
    'apilogger.exclude_response_keys' => [],
    'apilogger.exclude_response_patterns' => [],
    'apilogger.response_keys'  => ['response', 'uuid'],
    'apilogger.response_rules' => ['oauth/authorize' => false],
    'apilogger.payload_keys'   => [],
    'apilogger.payload_rules'  => ['endpoint.getDataByCode' => ['param_type', 'query', 'path']],
];
require __DIR__ . '/../bootstrap.php';

migrate_package();
check('rules table migrated', \Illuminate\Support\Facades\Schema::hasTable('api_logger_rules'));

$page = send('GET', '/apilogger/rules');
check('rules page loads', $page->getStatusCode() === 200, (string) $page->getStatusCode());
check('rules page shows config rules', str_contains($page->getContent(), 'endpoint.getDataByCode'));
check('layout has Rules link', str_contains($page->getContent(), '>Rules</a>'));

check('import redirects', send('POST', '/apilogger/rules/import')->getStatusCode() === 302);
$imported = ApiLoggerRule::ordered()->get();
check('import: 1 payload rule + 1 response rule + response * default', $imported->count() === 3, (string) $imported->count());
$star = $imported->firstWhere('pattern', '*');
check('import: global keys become * rule with priority 1000', $star && $star->type === 'response' && $star->priority === 1000 && $star->keys === ['response', 'uuid']);
check('import: false becomes "none"', $imported->firstWhere('pattern', 'oauth/authorize')?->action === 'none');
send('POST', '/apilogger/rules/import');
check('second import adds nothing', ApiLoggerRule::count() === 3);

$before = ApiLoggerRule::count();
send('POST', '/apilogger/rules', ['type' => 'response', 'pattern' => 'x', 'action' => 'keep', 'keys_text' => '', 'priority' => 50, 'enabled' => '1']);
check('keep without keys is rejected', ApiLoggerRule::count() === $before);
send('POST', '/apilogger/rules', ['type' => 'response', 'pattern' => 'x', 'action' => 'keep', 'keys_text' => 'bad key', 'priority' => 50, 'enabled' => '1']);
check('key with a space is rejected', ApiLoggerRule::count() === $before);
send('POST', '/apilogger/rules', ['type' => 'nope', 'pattern' => 'x', 'action' => 'all', 'priority' => 50]);
check('unknown type is rejected', ApiLoggerRule::count() === $before);

send('POST', '/apilogger/rules', ['type' => 'response', 'pattern' => 'endpoint.getDataByCode', 'action' => 'keep',
    'keys_text' => "response\r\ndata.*.id, uuid", 'priority' => 50, 'enabled' => '1', 'note' => 'check']);
$rule = ApiLoggerRule::latest('id')->first();
check('create rule (newlines and commas split keys)', $rule?->keys === ['response', 'data.*.id', 'uuid'], json_encode($rule?->keys));
check('edit page loads', send('GET', "/apilogger/rules/{$rule->id}/edit")->getStatusCode() === 200);

$logger = $app->make(\GDI\ApiLogger\Contracts\ApiLoggerInterface::class);
$build  = fn (array $body) => $logger->buildLogData(
    request_for('api/v3/services/VID3001', 'endpoint.getDataByCode', ['param_type' => 'query', 'query' => ['id' => 1], 'body' => ['nid' => 'x']]),
    new \Illuminate\Http\JsonResponse($body),
    microtime(true),
);
echo '     logger: ' . get_class($logger) . "\n";

$data = $build(['response' => true, 'uuid' => 'u-1', 'data' => [['id' => 7, 'name' => 'secret']]]);
check('database rule applies to the response', $data['response'] === '{"response":true,"data":[{"id":7}],"uuid":"u-1"}', (string) $data['response']);
check('imported payload rule applies', $data['payload'] === '{"param_type":"query","query":{"id":1}}', (string) $data['payload']);
check('rules query is not listed as a model', !str_contains((string) $data['models'], 'ApiLoggerRule'), (string) $data['models']);
if (array_key_exists('uuid', $data)) { // host logger adds a uuid column (core-api-v3's GatewayDatabaseLogger)
    check('host uuid column still read from the live response', $data['uuid'] === 'u-1');
}

send('PUT', "/apilogger/rules/{$rule->id}", ['type' => 'response', 'pattern' => 'endpoint.getDataByCode', 'action' => 'none', 'keys_text' => 'response', 'priority' => 50]);
$rule->refresh();
check('update: unchecked box disables, keys cleared for "none"', $rule->enabled === false && $rule->action === 'none' && $rule->keys === null);
$data = $build(['response' => true, 'uuid' => 'u-2', 'data' => []]);
check('disabled rule ignored and cache cleared on save', $data['response'] === '{"response":true,"uuid":"u-2"}', (string) $data['response']);

check('delete rule', send('DELETE', "/apilogger/rules/{$rule->id}")->getStatusCode() === 302 && ApiLoggerRule::find($rule->id) === null);
check('/{id} log route still answers (404 for unknown id)', send('GET', '/apilogger/log_missing')->getStatusCode() === 404);

\Illuminate\Support\Facades\Artisan::call('apilogger:import-rules');
check('artisan apilogger:import-rules', str_contains(\Illuminate\Support\Facades\Artisan::output(), 'Imported 0 rule(s)'), trim(\Illuminate\Support\Facades\Artisan::output()));

\Illuminate\Support\Facades\Schema::drop('api_logger_rules');
app(\GDI\ApiLogger\Rules\RuleRepository::class)->flush();
$data = $build(['response' => true, 'uuid' => 'u-3', 'data' => [1]]);
check('missing rules table falls back to config, logging keeps working', $data['response'] === '{"response":true,"uuid":"u-3"}', (string) $data['response']);

finish();
