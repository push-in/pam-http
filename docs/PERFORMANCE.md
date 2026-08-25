# Performance contract

PAM HTTP compiles work at application freeze: exact routes use a direct lookup,
dynamic routes are indexed by method, middleware becomes an immutable handler
chain, route middleware is cached, and dependency-injection reflection becomes
reusable parameter plans. The request path therefore performs only work that
depends on the current request.

Run both public performance contracts with regular PHP:

```bash
php benchmarks/router.php --assert
php benchmarks/kernel.php --assert
```

The router contract covers static and dynamic matching. The kernel contract
covers singleton resolution, autowiring, compiled middleware, JSON Resources,
startup, and peak memory. Both report throughput plus p50, p95, and p99 latency;
their versioned budgets make CI fail on regressions.

Use the same PHP build, CPU governor, fixture and sample count when comparing
branches. Warm OPcache in production, preload the Composer autoloader and
application bootstrap where appropriate, and profile a release configuration
before changing a hot path.
