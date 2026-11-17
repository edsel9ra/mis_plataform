<?php

/**
 * Spec 015 benchmark diagnostics (benchmark.md §Salida esperada): captures
 * every SQL statement of ProgressHistoryService::page() for one bench
 * subject (first page and a follow-up page) and runs
 * EXPLAIN (ANALYZE, BUFFERS) on each, plus the wall time of the page call
 * vs. the summed query time. Read-only; *_bench databases only. Run like
 * seed-dataset.php:  ... backend php /bench/explain-page.php > /bench-out/explain.txt
 */

use App\Models\User;
use App\Services\Progress\History\ProgressHistoryService;
use Illuminate\Support\Facades\DB;

require '/var/www/backend/vendor/autoload.php';
$app = require '/var/www/backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$database = (string) DB::connection()->getDatabaseName();
if (! str_ends_with($database, '_bench')) {
    fwrite(STDERR, "Refusing: '{$database}' is not a *_bench database.\n");
    exit(1);
}

$subject = User::query()->where('email', 'bench.subject0@mis.test')->firstOrFail();
$service = app(ProgressHistoryService::class);

// Warm-up so the timings below are not first-call autoload/compile costs.
$first = $service->page($subject, null, 25);
$service->page($subject, $first['next_cursor'], 25);

foreach (['first page' => null, 'second page' => $first['next_cursor']] as $label => $cursor) {
    DB::flushQueryLog();
    DB::enableQueryLog();
    $t = microtime(true);
    $service->page($subject, $cursor, 25);
    $wall = (microtime(true) - $t) * 1000;
    DB::disableQueryLog();
    $log = DB::getQueryLog();

    printf("=== %s: %d queries, page() wall %.1f ms, summed query time %.1f ms ===\n\n",
        $label, count($log), $wall, array_sum(array_column($log, 'time')));

    foreach ($log as $i => $entry) {
        printf("--- [%d] %.2f ms ---\n%s\nbindings: %s\n", $i + 1, $entry['time'], $entry['query'], json_encode($entry['bindings']));
        $plan = DB::select('EXPLAIN (ANALYZE, BUFFERS) '.$entry['query'], $entry['bindings']);
        foreach ($plan as $row) {
            echo '  '.((array) $row)['QUERY PLAN'].PHP_EOL;
        }
        echo PHP_EOL;
    }
}
