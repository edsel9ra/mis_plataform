<?php

use App\Http\Resources\CollectiveLearningAggregateResource;
use App\Http\Resources\LearningMapResource;
use App\Services\Progress\Calculators\CollectiveAggregateCalculator;
use App\Services\Progress\Map\AggregateMetric;
use App\Services\Progress\Map\CollectiveLearningAggregate;
use App\Services\Progress\Map\MapElement;
use App\Services\Progress\Map\MapElementType;
use App\Services\Progress\Map\MapModule;
use App\Services\Progress\Map\MapModuleCompletion;
use App\Services\Progress\Map\MapPath;
use App\Services\Progress\Map\PathAggregateRate;
use App\Services\Progress\Support\MetricValue;
use App\Services\Progress\Support\SuppressionReason;
use Illuminate\Http\Request;

function mapElementFixture(): MapElement
{
    return new MapElement(
        type: MapElementType::Challenge,
        id: 'challenge-1',
        title: 'Reto de ejemplo',
        sourceStatus: 'in_progress',
    );
}

function mapModuleFixture(): MapModule
{
    return new MapModule(
        id: 'module-1',
        title: 'Módulo uno',
        order: 2,
        activityRate: MetricValue::value(50, 'challenge_status'),
        completion: new MapModuleCompletion(state: 'not_completed', completedAt: null),
        elements: [mapElementFixture()],
    );
}

function mapPathFixture(): MapPath
{
    return new MapPath(
        id: 'path-1',
        title: 'Ruta de ejemplo',
        versionKey: 'v-fingerprint',
        rate: MetricValue::value(75, 'module_completion'),
        modules: [mapModuleFixture()],
    );
}

/**
 * Recursively asserts that no array key leaks base/contribution sizes.
 *
 * @param  array<string, mixed>  $payload
 */
function expectNoDisclosingKeys(array $payload): void
{
    foreach ($payload as $key => $value) {
        expect($key)->not->toMatch('/count|numerator|denominator|base|contribut|members?|participants?/i');
        if (is_array($value)) {
            expectNoDisclosingKeys($value);
        } else {
            expect(is_numeric($value))->toBeFalse("numeric value leaked at key '{$key}'");
        }
    }
}

it('serializes a map path with the same keys as the learning-paths resource plus version_key', function () {
    $serialized = LearningMapResource::make(mapPathFixture())->toArray(Request::create('/'));

    // Shared parts keep the exact T012.7 contract keys.
    expect($serialized)->toHaveKeys(['learning_path', 'rate', 'modules']);
    expect($serialized['learning_path'])->toBe(['id' => 'path-1', 'title' => 'Ruta de ejemplo']);
    expect($serialized['version_key'])->toBe('v-fingerprint');
    expect($serialized['rate'])->toMatchArray([
        'state' => 'value',
        'value' => 75,
        'source' => 'module_completion',
    ]);
});

it('serializes a map module with the same keys as the learning-paths module plus elements', function () {
    $module = LearningMapResource::make(mapPathFixture())->toArray(Request::create('/'))['modules'][0];

    expect($module)->toHaveKeys(['id', 'title', 'position', 'activity_rate', 'completion', 'elements']);
    expect($module['id'])->toBe('module-1');
    expect($module['position'])->toBe(2);
    expect($module['activity_rate']['state'])->toBe('value');
    expect($module['completion'])->toBe(['state' => 'not_completed', 'completed_at' => null]);
});

it('serializes a map element with type/id/title/raw source status and no derived percentage', function () {
    $element = LearningMapResource::make(mapPathFixture())->toArray(Request::create('/'))['modules'][0]['elements'][0];

    expect($element)->toBe([
        'type' => 'challenge',
        'id' => 'challenge-1',
        'title' => 'Reto de ejemplo',
        'source_status' => 'in_progress',
    ]);
});

it('serializes an available collective learning aggregate', function () {
    $aggregate = new CollectiveLearningAggregate(
        state: CollectiveLearningAggregate::STATE_AVAILABLE,
        suppressionReason: null,
        activityRate: new AggregateMetric(state: AggregateMetric::STATE_VALUE, value: 66, suppressionReason: null),
        rates: [new PathAggregateRate(
            pathId: 'path-1',
            versionKey: 'v-fingerprint',
            title: 'Ruta de ejemplo',
            metric: new AggregateMetric(state: AggregateMetric::STATE_VALUE, value: 80, suppressionReason: null),
        )],
    );

    $serialized = CollectiveLearningAggregateResource::make($aggregate)->toArray(Request::create('/'));

    expect($serialized['state'])->toBe('available');
    expect($serialized['suppression_reason'])->toBeNull();
    expect($serialized['activity_rate'])->toBe([
        'state' => 'value',
        'value' => 66,
        'suppression_reason' => null,
    ]);
    expect($serialized['rates'])->toHaveCount(1);
    expect($serialized['rates'][0])->toBe([
        'path_id' => 'path-1',
        'version_key' => 'v-fingerprint',
        'title' => 'Ruta de ejemplo',
        'rate' => ['state' => 'value', 'value' => 80, 'suppression_reason' => null],
    ]);
});

it('suppresses the whole block for insufficient base with only state and reason', function () {
    $aggregate = new CollectiveLearningAggregate(
        state: CollectiveLearningAggregate::STATE_SUPPRESSED,
        suppressionReason: SuppressionReason::InsufficientBase,
        activityRate: null,
        rates: [],
    );

    $serialized = CollectiveLearningAggregateResource::make($aggregate)->toArray(Request::create('/'));

    expect($serialized)->toBe([
        'state' => 'suppressed',
        'suppression_reason' => 'insufficient_base',
    ]);
    expectNoDisclosingKeys($serialized);
});

it('suppresses a single metric for insufficient contributions without leaking counts', function () {
    $aggregate = new CollectiveLearningAggregate(
        state: CollectiveLearningAggregate::STATE_AVAILABLE,
        suppressionReason: null,
        activityRate: new AggregateMetric(
            state: AggregateMetric::STATE_SUPPRESSED,
            value: null,
            suppressionReason: SuppressionReason::InsufficientMetricContributions,
        ),
        rates: [
            new PathAggregateRate(
                pathId: 'path-1',
                versionKey: 'v',
                title: 'Ruta',
                metric: new AggregateMetric(
                    state: AggregateMetric::STATE_SUPPRESSED,
                    value: null,
                    suppressionReason: SuppressionReason::InsufficientMetricContributions,
                ),
            ),
            new PathAggregateRate(
                pathId: 'path-2',
                versionKey: 'v2',
                title: 'Ruta 2',
                metric: new AggregateMetric(state: AggregateMetric::STATE_VALUE, value: 40, suppressionReason: null),
            ),
        ],
    );

    $serialized = CollectiveLearningAggregateResource::make($aggregate)->toArray(Request::create('/'));

    expect($serialized['activity_rate'])->toBe([
        'state' => 'suppressed',
        'suppression_reason' => 'insufficient_metric_contributions',
    ]);
    // A suppressed sibling does not suppress the rest.
    expect($serialized['rates'][0]['rate'])->toBe([
        'state' => 'suppressed',
        'suppression_reason' => 'insufficient_metric_contributions',
    ]);
    expect($serialized['rates'][1]['rate'])->toBe([
        'state' => 'value',
        'value' => 40,
        'suppression_reason' => null,
    ]);

    expectNoDisclosingKeys($serialized['activity_rate']);
    expectNoDisclosingKeys($serialized['rates'][0]['rate']);
});

it('never exposes member identities anywhere in the aggregate payload', function () {
    $aggregate = new CollectiveLearningAggregate(
        state: CollectiveLearningAggregate::STATE_AVAILABLE,
        suppressionReason: null,
        activityRate: new AggregateMetric(state: AggregateMetric::STATE_VALUE, value: 50, suppressionReason: null),
        rates: [],
    );

    $serialized = CollectiveLearningAggregateResource::make($aggregate)->toArray(Request::create('/'));
    $json = json_encode($serialized);

    expect($json)->not->toContain('member')->not->toContain('user_id');
});

it('reuses the existing insufficient_base reason code as a single enum source', function () {
    expect(SuppressionReason::InsufficientBase->value)->toBe('insufficient_base');
    expect(SuppressionReason::InsufficientMetricContributions->value)->toBe('insufficient_metric_contributions');
    expect(CollectiveAggregateCalculator::SUPPRESSION_REASON)->toBe(SuppressionReason::InsufficientBase->value);
});
