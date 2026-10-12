import { describe, expect, it } from 'vitest';
import {
  allowedObjectiveTransitions,
  collectiveMessageKey,
  collectiveSuppressionMessageKey,
  formatAggregateMean,
  formatAggregateSum,
  formatCount,
  formatMetricValue,
  formatPercent,
  formatRatio,
  hasMetricData,
  isBlockIncomplete,
  isPercentageMetric,
  metricUi,
  objectiveStatusBadgeVariant,
  periodLabelKey,
  resolvePeriodLabel,
  sortCommitments,
} from './progress';
import {
  objectiveEvidenceSchema,
  objectiveProposalSchema,
  objectiveSchema,
  objectiveUpdateSchema,
} from './schemas/objective';
import type { CollectiveProgress, CommitmentItem, MetricValue, ProgressPanel } from '@/types/progress';

function metric(overrides: Partial<MetricValue> = {}): MetricValue {
  return {
    state: 'value',
    value: null,
    numerator: null,
    denominator: null,
    source: null,
    period: null,
    incomplete: false,
    ...overrides,
  };
}

function collective(overrides: Partial<CollectiveProgress> = {}): CollectiveProgress {
  return {
    subject: { type: 'cohort', id: 'c1', name: 'Cohort' },
    viewer_role: 'member',
    state: 'value',
    suppressed: false,
    suppression_reason: null,
    metrics: { mean: 67, sum: { achieved: 4, total: 6 } },
    base: { eligible_members: 5, contributing_members: 4 },
    ...overrides,
  };
}

describe('metric state to UI mapping', () => {
  it('never renders no_data as a zero', () => {
    expect(metricUi('no_data').showsNumber).toBe(false);
    expect(metricUi('no_data').messageKey).toBe('progress.metric.no_data');
    expect(formatMetricValue(metric({ state: 'no_data', value: null }))).toBeNull();

    // A real zero is disclosed and formatted as 0%.
    expect(metricUi('zero').showsNumber).toBe(true);
    expect(formatMetricValue(metric({ state: 'zero', value: 0, numerator: 0, denominator: 3 }))).toBe('0%');
  });

  it('surfaces an incomplete state distinctly', () => {
    expect(metricUi('incomplete').tone).toBe('incomplete');
    expect(metricUi('incomplete').messageKey).toBe('progress.metric.incomplete');
    expect(hasMetricData('incomplete')).toBe(true);
    expect(hasMetricData('no_data')).toBe(false);
    expect(formatMetricValue(metric({ state: 'incomplete', value: 2 }))).toBe('2');
  });

  it('treats suppressed and blocked as non-disclosable', () => {
    expect(metricUi('suppressed').showsNumber).toBe(false);
    expect(metricUi('suppressed').messageKey).toBe('progress.metric.suppressed');
    expect(metricUi('blocked').messageKey).toBe('progress.metric.blocked');
    expect(formatMetricValue(metric({ state: 'blocked', value: 10 }))).toBeNull();
  });
});

describe('number formatting with null', () => {
  it('returns null instead of a misleading value', () => {
    expect(formatPercent(null)).toBeNull();
    expect(formatPercent(undefined)).toBeNull();
    expect(formatCount(null)).toBeNull();
    expect(formatRatio(null, 3)).toBeNull();
    expect(formatRatio(3, null)).toBeNull();
    expect(formatMetricValue(metric({ value: null }))).toBeNull();
    expect(formatMetricValue(null)).toBeNull();
  });

  it('formats percentages and counts', () => {
    expect(formatPercent(66.6)).toBe('67%');
    expect(formatCount(4)).toBe('4');
    expect(formatRatio(3, 5)).toBe('3/5');
  });

  it('uses the ratio presence to decide percent vs count', () => {
    expect(isPercentageMetric(metric({ numerator: 1, denominator: 2 }))).toBe(true);
    expect(isPercentageMetric(metric({ numerator: null, denominator: null }))).toBe(false);
    expect(formatMetricValue(metric({ value: 4 }))).toBe('4');
    expect(formatMetricValue(metric({ value: 50, numerator: 1, denominator: 2 }))).toBe('50%');
  });
});

describe('commitments ordering', () => {
  it('sorts ascending by date without mutating the input', () => {
    const items: CommitmentItem[] = [
      { kind: 'objective', id: 'o1', title: 'Later', at: '2026-10-20T10:00:00+00:00' },
      { kind: 'session', id: 's1', title: 'Sooner', at: '2026-10-13T09:00:00+00:00' },
      { kind: 'activity', id: 'a1', title: 'Middle', at: '2026-10-15T12:00:00+00:00' },
    ];

    const sorted = sortCommitments(items);

    expect(sorted.map((item) => item.id)).toEqual(['s1', 'a1', 'o1']);
    expect(items.map((item) => item.id)).toEqual(['o1', 's1', 'a1']);
  });
});

describe('collective suppression messaging', () => {
  it('exposes a suppression message only when suppressed', () => {
    expect(collectiveSuppressionMessageKey(collective({ suppressed: true }))).toBe('progress.collective.suppressed');
    expect(collectiveSuppressionMessageKey(collective({ suppressed: false }))).toBeNull();
    expect(collectiveSuppressionMessageKey(null)).toBeNull();
  });

  it('maps aggregate states to their message keys', () => {
    expect(collectiveMessageKey('no_data')).toBe('progress.collective.no_data');
    expect(collectiveMessageKey('suppressed')).toBe('progress.collective.suppressed');
    expect(collectiveMessageKey('value')).toBeNull();
  });

  it('never formats metrics for a suppressed aggregate', () => {
    const suppressed = collective({ suppressed: true, state: 'suppressed', metrics: { mean: 67, sum: { achieved: 4, total: 6 } } });

    expect(formatAggregateMean(suppressed)).toBeNull();
    expect(formatAggregateSum(suppressed)).toBeNull();
    expect(formatAggregateMean(collective())).toBe('67%');
    expect(formatAggregateSum(collective())).toBe('4/6');
  });
});

describe('period labels and incomplete blocks', () => {
  it('maps period types to i18n keys and prefers the server label', () => {
    expect(periodLabelKey('current_month')).toBe('progress.periods.current_month');
    expect(periodLabelKey('next_7_days')).toBe('progress.periods.next_7_days');
    expect(resolvePeriodLabel({ type: 'current_month', from: null, to: null, label: 'Este mes' }, () => 'fallback')).toBe('Este mes');
    expect(resolvePeriodLabel({ type: 'current_state', from: null, to: null, label: '' }, (key) => `t:${key}`)).toBe('t:progress.periods.current_state');
    expect(resolvePeriodLabel(null, () => 'x')).toBeNull();
  });

  it('reports which blocks are degraded', () => {
    const panel = { incomplete_blocks: ['sessions'] } as unknown as ProgressPanel;

    expect(isBlockIncomplete(panel, 'sessions')).toBe(true);
    expect(isBlockIncomplete(panel, 'objectives')).toBe(false);
    expect(isBlockIncomplete(null, 'sessions')).toBe(false);
  });
});

describe('objective schema', () => {
  const baseIndividual = {
    scope: 'individual' as const,
    owner_id: '11111111-1111-4111-8111-111111111111',
    title: 'Read a book',
    success_criteria: 'Finish chapter one',
    due_at: '2026-11-01T10:00:00+00:00',
  };

  it('requires title, criteria and due date (CA-1.3)', () => {
    expect(objectiveSchema.safeParse(baseIndividual).success).toBe(true);
    expect(objectiveSchema.safeParse({ ...baseIndividual, title: '' }).success).toBe(false);
    expect(objectiveSchema.safeParse({ ...baseIndividual, success_criteria: '' }).success).toBe(false);
    expect(objectiveSchema.safeParse({ ...baseIndividual, due_at: '' }).success).toBe(false);
    expect(objectiveSchema.safeParse({ ...baseIndividual, due_at: 'not-a-date' }).success).toBe(false);
  });

  it('couples scope with the collective owner', () => {
    const collective = { ...baseIndividual, scope: 'collective' as const };

    // A collective objective must name its collective type.
    expect(objectiveSchema.safeParse(collective).success).toBe(false);
    expect(objectiveSchema.safeParse({ ...collective, owner_type: 'cohort' }).success).toBe(true);

    // An individual objective must not carry a collective type.
    expect(objectiveSchema.safeParse({ ...baseIndividual, owner_type: 'cohort' }).success).toBe(false);
  });

  it('keeps owner/scope immutable on update and requires a change', () => {
    expect(objectiveUpdateSchema.safeParse({}).success).toBe(false);
    expect(objectiveUpdateSchema.safeParse({ title: 'Renamed' }).success).toBe(true);
    // Unknown keys such as `scope` are rejected by the strict object shape.
    expect(objectiveUpdateSchema.safeParse({ scope: 'collective' }).success).toBe(false);
  });

  it('offers only valid status transitions and tones', () => {
    expect(allowedObjectiveTransitions('not_started')).toEqual(['in_progress', 'achieved', 'abandoned']);
    expect(allowedObjectiveTransitions('achieved')).not.toContain('achieved');
    expect(allowedObjectiveTransitions('abandoned')).toEqual(['not_started', 'in_progress']);
    expect(objectiveStatusBadgeVariant('achieved')).toBe('success');
    expect(objectiveStatusBadgeVariant('not_started')).toBe('default');
  });

  it('allows proposals without scope/owner and needs evidence content', () => {
    expect(objectiveProposalSchema.safeParse({
      title: 'Propose something',
      success_criteria: 'Done when reviewed',
      due_at: '2026-12-01T10:00:00+00:00',
    }).success).toBe(true);

    expect(objectiveEvidenceSchema.safeParse({ note: 'Evidence note' }).success).toBe(true);
    expect(objectiveEvidenceSchema.safeParse({}).success).toBe(false);
  });
});
