<?php
/**
 * Shared setup for the checks. A check sets $overrides (config key => value), then requires this file.
 *
 * Provides: $app, $kernel, check($label, $ok, $extra), send($method, $uri, $data), request_for($path,
 * $routeName, $data), finish(). The package copy in /tmp/apilogger-test/pkg is loaded instead of the
 * app's vendor/gdi/apilogger (a prepended autoloader, because Composer's classmap would win otherwise).
 */

$overrides ??= [];
$appDir = getenv('APILOGGER_TEST_APP_DIR') ?: '/app';

require $appDir . '/vendor/autoload.php';
spl_autoload_register(function (string $class) {
    if (str_starts_with($class, 'GDI\\ApiLogger\\')) {
        $file = '/tmp/apilogger-test/pkg/src/' . str_replace('\\', '/', substr($class, strlen('GDI\\ApiLogger\\'))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
}, true, true);

$app = require $appDir . '/bootstrap/app.php';
$app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) use ($overrides) {
    $config = $app['config'];
    $config->set('database.default', 'sqlite');
    $config->set('database.connections.sqlite', [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ]);
    $config->set('cache.default', 'array');
    $config->set('session.driver', 'array');
    $config->set('queue.default', 'sync');
    $config->set('logging.default', 'null'); // keep the app's storage/logs untouched
    $config->set('apilogger.route.middleware', ['web']); // no admin login in checks
    foreach ($overrides as $key => $value) {
        $config->set($key, $value);
    }
});

// Some apps touch URL/request while booting (core-api-v3: URL::forceScheme).
$app->instance('request', \Illuminate\Http\Request::create('http://localhost/'));
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$loadedFrom = (new ReflectionClass(\GDI\ApiLogger\Providers\ApiLoggerServiceProvider::class))->getFileName();
if (!str_starts_with($loadedFrom, '/tmp/apilogger-test/pkg/')) {
    fwrite(STDERR, "Package was loaded from {$loadedFrom}, not from the working tree copy.\n");
    exit(1);
}

$GLOBALS['__failures'] = 0;

function check(string $label, bool $ok, string $extra = ''): void
{
    if (!$ok) {
        $GLOBALS['__failures']++;
    }
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . ($extra !== '' ? "  -> {$extra}" : '') . "\n";
}

function send(string $method, string $uri, array $data = []): \Symfony\Component\HttpFoundation\Response
{
    global $kernel;
    $request  = \Illuminate\Http\Request::create($uri, $method, $data);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
}

/** A request already matched to a named route, as the logger sees it after routing. */
function request_for(string $path, ?string $routeName = null, array $data = [], string $method = 'POST'): \Illuminate\Http\Request
{
    $request = \Illuminate\Http\Request::create('/' . ltrim($path, '/'), $method, $data);
    $route   = new \Illuminate\Routing\Route($method, ltrim($path, '/'), []);
    if ($routeName !== null) {
        $route->name($routeName);
    }
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);

    return $request;
}

function migrate_package(): void
{
    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => '/tmp/apilogger-test/pkg/database/migrations', '--realpath' => true, '--force' => true,
    ]);
}

/** Print the summary and write the status file run.sh reads. Call at the end of every check. */
function finish(): never
{
    $failures = $GLOBALS['__failures'];
    $name     = basename($_SERVER['SCRIPT_FILENAME']);
    file_put_contents(__DIR__ . "/checks/.status-{$name}", $failures === 0 ? 'ok' : 'fail');
    echo $failures === 0 ? "-- all passed\n" : "-- {$failures} failed\n";
    exit($failures === 0 ? 0 : 1);
}
