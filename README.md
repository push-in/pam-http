# pushinbr/pam-api

The optional Express-like HTTP layer for Pam: route parameters, 404/405 handling,
a precompiled middleware pipeline, error boundaries and Composer provider
discovery.

```bash
pam composer require pushinbr/pam-api
```

```php
use Pam\App;

$app = new App();
$app->get('/users/{id}', static fn ($request, $response) =>
    $response->json(['id' => $request->route('id')]));
$app->listen(3000);
```

## License

Free and open-source under the [Apache License 2.0](LICENSE). You may use,
modify, and distribute this package for any purpose, including commercially.


## Recommended PAM workflow

Start new applications with `pam init my-api --template api`. In an existing PAM project, install the higher-level router with `pam composer require pushinbr/pam-api`; PAM runs Composer inside its private Embed SAPI.

Run `pam doctor` after dependency changes and before creating a release. The project remains a normal Composer project with a standard manifest, lockfile, PSR-4 autoloading, and `vendor/autoload.php`.

## API guide

| Surface | Use it for |
| --- | --- |
| `App` | Register routes, middleware, providers, error boundaries, and the listener. |
| `Router` | Compile and match method/path routes with typed results. |
| `Pipeline` | Execute middleware and the destination handler in order. |
| `CorsMiddleware` | Apply explicit origin, method, and header policy. |
| `RateLimitMiddleware` | Apply bounded per-key request limits. |
| `SecurityHeadersMiddleware` | Set conservative browser security headers. |

Route parameters are available through `$request->route()`. A path that exists for another method produces 405 behavior; an unknown path produces 404 behavior. Register error handling with `onError()` and keep transport-level timeouts and request limits in the PAM listener options.

## Production checklist

- Keep request data and mutable state scoped to the current request.
- Test success, validation failure, exception, cancellation, and timeout paths.
- Configure explicit limits and avoid unbounded payloads, queues, or retained collections.
- Run `pam doctor`, `pam test`, and the relevant integration suite before release.
- Validate real dependencies and workload behavior; compatibility is not inferred from package installation alone.

## Troubleshooting

- **Class not found:** run `pam composer install`, verify PSR-4 configuration, and rerun `pam doctor`.
- **Behavior differs over the network:** reproduce with PAM's transport integration tests; in-memory execution does not model the socket boundary.
- **A dependency blocks a worker:** use PAM-native I/O, a compatible event loop, a process pool, or additional isolated workers.

## Documentation and support

- [PAM introduction](https://push-in.github.io/pam-docs/introduction/)
- [Package ecosystem](https://push-in.github.io/pam-docs/packages/overview/)
- [Runtime compatibility](https://push-in.github.io/pam-docs/runtime/compatibility/)
- [Report an issue](https://github.com/push-in/pam-api/issues)

Report security vulnerabilities through GitHub private vulnerability reporting or the PAM security policy, not a public issue.
