<?php

use App\Services\Progress\Calculators\LearningMapCalculator;
use App\Services\Progress\Map\MapElementType;

/**
 * Pure map ordering/placement (RF-013.1, .3, .4): no DB, no clock, no
 * inference — elements land under a module only via the explicit
 * path_module_id link recorded by Spec 012.
 */

function mapModuleRow(string $id, int $order, array $extra = []): array
{
    return array_merge(['id' => $id, 'title' => "Módulo {$id}", 'order' => $order], $extra);
}

function mapChallengeRow(string $id, string $status, ?string $moduleId, array $extra = []): array
{
    return array_merge([
        'id' => $id,
        'title' => "Reto {$id}",
        'status' => $status,
        'path_module_id' => $moduleId,
    ], $extra);
}

it('orders modules by their registered order ascending', function () {
    $calculator = new LearningMapCalculator;

    $ordered = $calculator->orderModules([
        mapModuleRow('mod-3', 3),
        mapModuleRow('mod-1', 1),
        mapModuleRow('mod-2', 2),
    ]);

    expect(array_column($ordered, 'id'))->toBe(['mod-1', 'mod-2', 'mod-3']);
});

it('breaks order ties deterministically by id ascending', function () {
    $calculator = new LearningMapCalculator;

    $ordered = $calculator->orderModules([
        mapModuleRow('b-mod', 1),
        mapModuleRow('a-mod', 1),
        mapModuleRow('c-mod', 1),
        mapModuleRow('z-first', 0),
    ]);

    expect(array_column($ordered, 'id'))->toBe(['z-first', 'a-mod', 'b-mod', 'c-mod']);

    // Same input, different arrival order → same result.
    $again = $calculator->orderModules([
        mapModuleRow('c-mod', 1),
        mapModuleRow('z-first', 0),
        mapModuleRow('a-mod', 1),
        mapModuleRow('b-mod', 1),
    ]);

    expect(array_column($again, 'id'))->toBe(['z-first', 'a-mod', 'b-mod', 'c-mod']);
});

it('keeps every other module field untouched while ordering', function () {
    $calculator = new LearningMapCalculator;

    $ordered = $calculator->orderModules([
        mapModuleRow('mod-2', 2, ['title' => 'Segundo', 'activity_rate' => 'metric-object']),
        mapModuleRow('mod-1', 1, ['title' => 'Primero']),
    ]);

    expect($ordered[0])->toBe([
        'id' => 'mod-1',
        'title' => 'Primero',
        'order' => 1,
    ]);
    expect($ordered[1]['activity_rate'])->toBe('metric-object');
});

it('returns an empty list for a path without modules', function () {
    $calculator = new LearningMapCalculator;

    expect($calculator->orderModules([]))->toBe([]);
    expect($calculator->placeChallenges([], [
        mapChallengeRow('ch-1', 'completed', 'mod-1'),
    ]))->toBe([]);
});

it('places a challenge only under the module it is explicitly linked to', function () {
    $calculator = new LearningMapCalculator;

    $placed = $calculator->placeChallenges(
        ['mod-1', 'mod-2'],
        [
            mapChallengeRow('ch-1', 'completed', 'mod-2'),
            mapChallengeRow('ch-2', 'assigned', 'mod-1'),
        ],
    );

    expect(array_keys($placed))->toBe(['mod-1', 'mod-2']);
    expect($placed['mod-1'])->toHaveCount(1);
    expect($placed['mod-2'])->toHaveCount(1);

    $element = $placed['mod-2'][0];
    expect($element->type)->toBe(MapElementType::Challenge);
    expect($element->id)->toBe('ch-1');
    expect($element->title)->toBe('Reto ch-1');
    expect($element->sourceStatus)->toBe('completed');
});

it('copies the source status verbatim, including values outside the known set', function () {
    $calculator = new LearningMapCalculator;

    $placed = $calculator->placeChallenges(
        ['mod-1'],
        [
            mapChallengeRow('ch-1', 'abandoned', 'mod-1'),
            mapChallengeRow('ch-2', 'template', 'mod-1'),
            mapChallengeRow('ch-3', 'status_from_the_future', 'mod-1'),
        ],
    );

    expect(array_map(
        fn ($element) => $element->sourceStatus,
        $placed['mod-1'],
    ))->toBe(['abandoned', 'template', 'status_from_the_future']);
});

it('does not place an unlinked challenge even when its title matches a module', function () {
    $calculator = new LearningMapCalculator;

    $placed = $calculator->placeChallenges(
        ['mod-1'],
        [mapChallengeRow('ch-1', 'in_progress', null, ['title' => 'Módulo mod-1'])],
    );

    expect($placed['mod-1'])->toBe([]);
});

it('does not place a challenge linked to a module outside the path', function () {
    $calculator = new LearningMapCalculator;

    $placed = $calculator->placeChallenges(
        ['mod-1'],
        [
            mapChallengeRow('ch-1', 'completed', 'other-path-module'),
            mapChallengeRow('ch-2', 'completed', 'deleted-module'),
        ],
    );

    expect($placed['mod-1'])->toBe([]);
});

it('does not place a challenge whose link key is absent', function () {
    $calculator = new LearningMapCalculator;

    $challenge = mapChallengeRow('ch-1', 'assigned', 'mod-1');
    unset($challenge['path_module_id']);

    $placed = $calculator->placeChallenges(['mod-1'], [$challenge]);

    expect($placed['mod-1'])->toBe([]);
});

it('keeps challenges under a module in the order received', function () {
    $calculator = new LearningMapCalculator;

    $placed = $calculator->placeChallenges(
        ['mod-1'],
        [
            mapChallengeRow('ch-b', 'assigned', 'mod-1'),
            mapChallengeRow('ch-a', 'completed', 'mod-1'),
        ],
    );

    expect(array_map(fn ($element) => $element->id, $placed['mod-1']))
        ->toBe(['ch-b', 'ch-a']);
});

it('returns every module id with an empty element list when nothing is linked', function () {
    $calculator = new LearningMapCalculator;

    $placed = $calculator->placeChallenges(['mod-1', 'mod-2'], []);

    expect($placed)->toBe(['mod-1' => [], 'mod-2' => []]);
});

it('produces the same version key for the same path definition regardless of input order', function () {
    $calculator = new LearningMapCalculator;

    $key = $calculator->pathVersionKey('path-1', [
        mapModuleRow('mod-2', 2),
        mapModuleRow('mod-1', 1),
    ]);

    $same = $calculator->pathVersionKey('path-1', [
        mapModuleRow('mod-1', 1),
        mapModuleRow('mod-2', 2),
    ]);

    expect($same)->toBe($key);
});

it('produces different version keys for different paths with the same modules', function () {
    $calculator = new LearningMapCalculator;

    $modules = [mapModuleRow('mod-1', 1), mapModuleRow('mod-2', 2)];

    expect($calculator->pathVersionKey('path-2', $modules))
        ->not->toBe($calculator->pathVersionKey('path-1', $modules));
});

it('changes the version key when a module is added or removed', function () {
    $calculator = new LearningMapCalculator;

    $before = $calculator->pathVersionKey('path-1', [
        mapModuleRow('mod-1', 1),
        mapModuleRow('mod-2', 2),
    ]);

    $added = $calculator->pathVersionKey('path-1', [
        mapModuleRow('mod-1', 1),
        mapModuleRow('mod-2', 2),
        mapModuleRow('mod-3', 3),
    ]);

    $removed = $calculator->pathVersionKey('path-1', [
        mapModuleRow('mod-1', 1),
    ]);

    expect($added)->not->toBe($before);
    expect($removed)->not->toBe($before);
});

it('changes the version key when the module sequence changes', function () {
    $calculator = new LearningMapCalculator;

    $before = $calculator->pathVersionKey('path-1', [
        mapModuleRow('mod-1', 1),
        mapModuleRow('mod-2', 2),
    ]);

    $reordered = $calculator->pathVersionKey('path-1', [
        mapModuleRow('mod-1', 2),
        mapModuleRow('mod-2', 1),
    ]);

    expect($reordered)->not->toBe($before);
});

it('keeps the version key when only non-structural fields change', function () {
    $calculator = new LearningMapCalculator;

    $before = $calculator->pathVersionKey('path-1', [
        mapModuleRow('mod-1', 1, ['title' => 'Antes']),
        mapModuleRow('mod-2', 2),
    ]);

    $renamed = $calculator->pathVersionKey('path-1', [
        mapModuleRow('mod-1', 1, ['title' => 'Después']),
        mapModuleRow('mod-2', 2),
    ]);

    expect($renamed)->toBe($before);
});

it('returns a stable 32-char hex fingerprint for a path without modules', function () {
    $calculator = new LearningMapCalculator;

    $key = $calculator->pathVersionKey('path-1', []);

    expect($key)->toMatch('/^[0-9a-f]{32}$/');
    expect($calculator->pathVersionKey('path-1', []))->toBe($key);
});
