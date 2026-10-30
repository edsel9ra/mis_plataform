<?php

// PHPUnit/Pest bootstrap: vendor autoload + test-environment mirroring.
require __DIR__.'/../vendor/autoload.php';

/**
 * Mirror PHPUnit's <env> decisions onto $_SERVER.
 *
 * PHPUnit's PhpHandler applies <env force="true"> via putenv() and $_ENV, but
 * never touches $_SERVER. Laravel's env() reads $_SERVER first (Dotenv's
 * ServerConstAdapter precedes EnvConstAdapter/PutenvAdapter), so when the suite
 * is launched with `docker compose exec ... php artisan test` without the -e
 * overrides of `make test-backend`, the container environment (.env.backend)
 * fills $_SERVER with runtime values (e.g. DB_DATABASE=mis_project) and shadows
 * the test values. The test process then commits its fixtures into the dev
 * database while its spawned concurrency workers (which inherit the putenv
 * values) resolve the testing database, or run with an unintended app env.
 *
 * Replaying PHPUnit's exact semantics (forced/isset rules below) and writing
 * the resulting value into $_SERVER as well reproduces the effective
 * environment that `make test-backend` (-e APP_ENV, -e DB_DATABASE) provides,
 * making the suite invocation-independent.
 */

$configPath = __DIR__.'/../phpunit.xml';

if (! is_file($configPath)) {
    return;
}

$config = @simplexml_load_file($configPath);

if ($config === false || ! isset($config->{'php'}->{'env'})) {
    return;
}

foreach ($config->{'php'}->{'env'} as $variable) {
    $name = (string) $variable['name'];
    $value = (string) $variable['value'];
    $force = ((string) ($variable['force'] ?? '')) === 'true';

    if ($force || getenv($name) === false) {
        putenv("{$name}={$value}");
    }

    $value = getenv($name);

    if ($force || ! isset($_ENV[$name])) {
        $_ENV[$name] = $value;
    }

    $_SERVER[$name] = $value;
}
