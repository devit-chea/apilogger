<?php
// Payload/response filtering from config: allowlists, per-route rules, masking, exclusions, truncation.

$overrides = [
    'apilogger.rules.database'          => false,
    'apilogger.max_response_length'     => 60,
    'apilogger.max_payload_length'      => 70,
    'apilogger.hidden_fields'           => ['password'],
    'apilogger.exclude_payload_keys'    => ['card.cvv'],
    'apilogger.exclude_response_keys'   => ['uuid'],
    'apilogger.exclude_response_patterns' => [],
    'apilogger.response_keys'           => ['response', 'message'],
    'apilogger.response_rules'          => [
        'endpoint.code' => ['response', 'uuid', 'data.*.id', 'data.missing.x'],
        'api/v3/user*'  => false,
        'api/v3/full'   => ['*'],
    ],
    'apilogger.payload_keys'            => ['site_id', 'password'],
    'apilogger.payload_rules'           => [
        'endpoint.code'   => ['site_id', 'card', 'items.*.id'],
        'api/v3/password' => false,
        'api/v3/full'     => [],
    ],
];
require __DIR__ . '/../bootstrap.php';

$logger = new class extends \GDI\ApiLogger\Drivers\AbstractLogger {
    public function save(array $data): void {}
    public function all(int $perPage = 20, array $filters = []): mixed { return null; }
    public function find(mixed $id): ?array { return null; }
    public function clear(): void {}
    public function payload($request) { return $this->preparePayload($request); }
    public function body($request, $body) { return $this->prepareResponseBody($request, $body); }
};

// --- response
$body = json_encode(['response' => true, 'uuid' => 'u1', 'message' => 'ខ្មែរ',
    'data' => [['id' => 1, 'big' => str_repeat('x', 500)], ['id' => 2, 'big' => 'y']]]);

$out = $logger->body(request_for('api/v3/services/A', 'endpoint.code'), $body);
check('response: route rule, * wildcard, exclusion after allowlist, filter before cut',
    $out === '{"response":true,"data":[{"id":1},{"id":2}]}', (string) $out);

$out = $logger->body(request_for('api/v3/user/1'), $body);
check('response: false rule stores nothing', $out === null, var_export($out, true));

$out = $logger->body(request_for('api/other'), $body);
check('response: global keys, unescaped unicode', $out === '{"response":true,"message":"ខ្មែរ"}', (string) $out);

$out = $logger->body(request_for('api/v3/full'), $body);
check('response: [*] keeps all, then cut', str_ends_with((string) $out, '...[truncated]') && str_starts_with((string) $out, '{"response":true,"message":"ខ្មែរ","data"'), (string) $out);

$out = $logger->body(request_for('api/other'), '<html>err</html>');
check('response: non-JSON unchanged', $out === '<html>err</html>', (string) $out);

// --- payload
$input = ['site_id' => 7, 'password' => 'secret', 'name' => 'សុខ', 'card' => ['no' => '4111', 'cvv' => '123'],
    'items' => [['id' => 1, 'x' => str_repeat('z', 200)], ['id' => 2]]];

$out = $logger->payload(request_for('api/v3/services/A', 'endpoint.code', $input));
check('payload: route rule, * wildcard, nested mask, cut',
    str_starts_with((string) $out, '{"site_id":7,"card":{"no":"4111","cvv":"***"},"items":[{"id":1},') && str_ends_with((string) $out, '...[truncated]'), (string) $out);

$out = $logger->payload(request_for('api/v3/password', null, $input));
check('payload: false rule stores nothing', $out === null, var_export($out, true));

$out = $logger->payload(request_for('api/other', null, $input));
check('payload: global keys, hidden field still masked', $out === '{"site_id":7,"password":"***"}', (string) $out);

$out = $logger->payload(request_for('api/v3/full', null, $input));
check('payload: [] keeps all', str_starts_with((string) $out, '{"site_id":7,"password":"***","name":"សុខ"'), (string) $out);

finish();
