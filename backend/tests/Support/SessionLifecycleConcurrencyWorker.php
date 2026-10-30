<?php

declare(strict_types=1);

use App\Actions\CompleteSessionAction;
use App\Actions\ConfirmAttendanceAction;
use App\Enums\AttendanceStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
    $input = json_decode(file_get_contents($inputPath), true, flags: JSON_THROW_ON_ERROR);
    $actor = User::query()->findOrFail($input['actor_id']);
    $now = CarbonImmutable::parse($input['now'], 'UTC');

    if ($input['operation'] === 'complete') {
        app(CompleteSessionAction::class)->execute($input['session_id'], $actor, $now);
    } else {
        app(ConfirmAttendanceAction::class)->execute(
            $input['session_id'],
            $actor,
            AttendanceStatus::from($input['status']),
            $input['target_user_id'] ?? null,
            $now,
        );
    }

    $result = ['status' => 'ok'];
} catch (ValidationException $exception) {
    $result = ['status' => 'rejected'];
} catch (Throwable $exception) {
    $result = [
        'status' => 'error',
        'error' => $exception::class.': '.$exception->getMessage(),
    ];
}

DB::disconnect();
file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR));
