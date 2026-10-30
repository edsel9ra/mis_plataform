<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$backendPath = dirname(__DIR__, 2);
require $backendPath.'/vendor/autoload.php';

$app = require $backendPath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $inputPath, $gatePath, $readyPath, $resultPath] = $argv;

file_put_contents($readyPath, (string) getmypid());

$deadline = microtime(true) + 10;
while (! file_exists($gatePath)) {
    if (microtime(true) >= $deadline) {
        file_put_contents($resultPath, json_encode([
            'status' => 'error',
            'error' => 'Start barrier timed out.',
        ], JSON_THROW_ON_ERROR));
        exit(0);
    }
    usleep(5_000);
}

try {
    $request = json_decode(file_get_contents($inputPath), true, flags: JSON_THROW_ON_ERROR);
    $actor = User::query()->findOrFail($request['actor_id']);

    Auth::guard('sanctum')->setUser($actor);
    $httpRequest = Request::create(
        '/api/v1/sessions',
        'POST',
        [],
        [],
        [],
        ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
        json_encode($request['payload'], JSON_THROW_ON_ERROR),
    );
    $kernel = $app->make(HttpKernel::class);
    $response = $kernel->handle($httpRequest);
    $body = json_decode($response->getContent(), true);
    $kernel->terminate($httpRequest, $response);

    if (in_array($response->getStatusCode(), [200, 201], true)) {
        $result = [
            'status' => 'ok',
            'http_status' => $response->getStatusCode(),
            'session_id' => (string) ($body['id'] ?? $body['data']['id'] ?? ''),
        ];
    } elseif ($response->getStatusCode() === 422) {
        $result = ['status' => 'rejected', 'http_status' => 422];
    } else {
        $result = [
            'status' => 'error',
            'http_status' => $response->getStatusCode(),
            'error' => $response->getContent(),
        ];
    }
} catch (Throwable $exception) {
    $result = [
        'status' => 'error',
        'error' => $exception::class.': '.$exception->getMessage(),
    ];
}

file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR));
