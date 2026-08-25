<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Pam\Api\CallableRequestHandler;
use Pam\Api\Container\Container;
use Pam\Api\Pipeline;
use Pam\App;
use Pam\Http\Request;
use Pam\Http\Response;

final class BenchmarkDependency
{
}

final class BenchmarkService
{
    public function __construct(public readonly BenchmarkDependency $dependency)
    {
    }
}

$budget = json_decode(
    file_get_contents(__DIR__ . '/budgets.json') ?: throw new RuntimeException('Cannot read budgets.'),
    true,
    32,
    JSON_THROW_ON_ERROR,
);
$assert = in_array('--assert', array_slice($_SERVER['argv'] ?? [], 1), true);
$iterations = (int) $budget['iterations'];
$warmup = (int) $budget['warmupIterations'];

$container = new Container();
$singleton = new BenchmarkDependency();
$container->instance(BenchmarkDependency::class, $singleton);
$request = new Request('GET', '/benchmark', [], [], '');
$destination = new CallableRequestHandler(static fn (Request $request, Response $response): Response => $response);
$middleware = [];
for ($index = 0; $index < 8; ++$index) {
    $middleware[] = static fn (Request $request, Response $response, $next): Response => $next->handle($request, $response);
}
$pipeline = new Pipeline($middleware, $destination);
$payload = ['data' => array_fill(0, 100, ['id' => 42, 'name' => 'PAM', 'active' => true])];

$operations = [
    'containerSingleton' => static fn () => $container->get(BenchmarkDependency::class),
    'containerAutowire' => static fn () => $container->get(BenchmarkService::class),
    'compiledMiddleware' => static fn () => $pipeline->handle($request, new Response()),
    'jsonSerialization' => static fn () => (new Response())->json($payload),
];

$results = [];
$passed = true;
foreach ($operations as $name => $operation) {
    for ($index = 0; $index < $warmup; ++$index) {
        $operation();
    }
    $result = measure($operation, $iterations);
    $limits = $budget['operations'][$name];
    $result['passed'] = $result['p99Nanoseconds'] <= $limits['maximumP99Nanoseconds']
        && $result['operationsPerSecond'] >= $limits['minimumOperationsPerSecond'];
    $passed = $passed && $result['passed'];
    $results[$name] = $result;
}

$startup = measure(
    static function (): void {
        $app = new App(false);
        $app->get('/health', static fn (Request $request, Response $response): Response => $response);
        $app->boot();
    },
    (int) $budget['startup']['iterations'],
);
$startup['passed'] = $startup['p95Nanoseconds'] <= $budget['startup']['maximumP95Nanoseconds']
    && $startup['p99Nanoseconds'] <= $budget['startup']['maximumP99Nanoseconds'];
$peakMemory = memory_get_peak_usage(true);
$passed = $passed && $startup['passed'] && $peakMemory <= $budget['maximumPeakMemoryBytes'];

echo json_encode([
    'schemaVersion' => 1,
    'phpVersion' => PHP_VERSION,
    'operations' => $results,
    'startup' => $startup,
    'peakMemoryBytes' => $peakMemory,
    'budget' => $budget,
    'passed' => $passed,
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), "\n";

if ($assert && !$passed) {
    fwrite(STDERR, "PAM HTTP performance offensive budget failed.\n");
    exit(1);
}

/**
 * @param callable(): mixed $operation
 * @return array{operationsPerSecond: int, p50Nanoseconds: int, p95Nanoseconds: int, p99Nanoseconds: int}
 */
function measure(callable $operation, int $iterations): array
{
    $samples = [];
    $started = hrtime(true);
    for ($index = 0; $index < $iterations; ++$index) {
        $sample = hrtime(true);
        $operation();
        $samples[] = hrtime(true) - $sample;
    }
    $elapsed = max(1, hrtime(true) - $started);
    sort($samples, SORT_NUMERIC);
    return [
        'operationsPerSecond' => (int) round(($iterations * 1_000_000_000) / $elapsed),
        'p50Nanoseconds' => percentile($samples, 50),
        'p95Nanoseconds' => percentile($samples, 95),
        'p99Nanoseconds' => percentile($samples, 99),
    ];
}

/** @param non-empty-list<int> $samples */
function percentile(array $samples, int $percentile): int
{
    $index = (int) ceil(count($samples) * $percentile / 100) - 1;
    return $samples[max(0, min(count($samples) - 1, $index))];
}
