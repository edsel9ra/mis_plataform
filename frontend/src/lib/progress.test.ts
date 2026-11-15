import { describe, expect, it } from 'vitest';
import {
  allowedObjectiveTransitions,
  availableEvidenceSources,
  buildEvidenceLinkPayload,
  collectiveMessageKey,
  collectiveSuppressionMessageKey,
  evidenceEntriesForOceanFactor,
  evidenceEntriesForSkill,
  evidenceInstrumentLabel,
  evidenceSourceKindKey,
  evidenceStateKey,
  formatAggregateMean,
  formatAggregateSum,
  formatCount,
  formatMetricValue,
  formatPercent,
  formatRatio,
  hasMetricData,
  isBlockIncomplete,
  isCurrentEvidence,
  isPercentageMetric,
  learningSuppressionMessageKey,
  mapElementStatusKey,
  mapElementTypeKey,
  metricUi,
  objectiveStatusBadgeVariant,
  periodLabelKey,
  personalitySourceOption,
  resolvePeriodLabel,
  sortCommitments,
} from './progress';
import {
  objectiveEvidenceSchema,
  objectiveProposalSchema,
  objectiveSchema,
  objectiveUpdateSchema,
} from './schemas/objective';
import type {
  CollectiveProgress,
  CommitmentItem,
  CompetencyEvidenceEntry,
  CompetencySkillItem,
  EvidenceSourceCandidate,
  MetricValue,
  OceanBlock,
  ProgressPanel,
} from '@/types/progress';
import esMessages from '../i18n/messages/es/common.json';
import enMessages from '../i18n/messages/en/common.json';
import ptMessages from '../i18n/messages/pt/common.json';

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

describe('learning map element labels (spec 013)', () => {
  it('translates the challenge element type and leaves unknown types verbatim', () => {
    expect(mapElementTypeKey('challenge')).toBe('progress.map.element_challenge');
    // Unknown types render the raw source type, never a derived label.
    expect(mapElementTypeKey('quiz')).toBeNull();
    expect(mapElementTypeKey('')).toBeNull();
  });

  it('maps challenge element status to the shared status keys', () => {
    expect(mapElementStatusKey({ type: 'challenge', source_status: 'completed' })).toBe('mentor_workspace.status_completed');
    expect(mapElementStatusKey({ type: 'challenge', source_status: 'abandoned' })).toBe('mentor_workspace.status_abandoned');
    // A source status outside the known set never fabricates a state.
    expect(mapElementStatusKey({ type: 'challenge', source_status: 'bogus' })).toBe('mentor_workspace.status_unknown');
  });

  it('keeps a non-challenge element status verbatim instead of deriving it', () => {
    expect(mapElementStatusKey({ type: 'quiz', source_status: 'graded' })).toBeNull();
  });
});

describe('collective learning suppression messages (spec 013)', () => {
  function resolveKey(key: string): string | undefined {
    return key.split('.').reduce<unknown>(
      (node, part) => (node && typeof node === 'object' ? (node as Record<string, unknown>)[part] : undefined),
      esMessages,
    ) as string | undefined;
  }

  it('gives a different message for each suppression reason', () => {
    const base = learningSuppressionMessageKey('insufficient_base');
    const metric = learningSuppressionMessageKey('insufficient_metric_contributions');

    expect(base).toBe('progress.collective.learning.suppressed_base');
    expect(metric).toBe('progress.collective.learning.suppressed_metric');
    expect(base).not.toBe(metric);
  });

  it('falls back to a generic message for unknown or missing reasons', () => {
    expect(learningSuppressionMessageKey(null)).toBe('progress.collective.learning.suppressed');
    expect(learningSuppressionMessageKey(undefined)).toBe('progress.collective.learning.suppressed');
    expect(learningSuppressionMessageKey('some_future_reason')).toBe('progress.collective.learning.suppressed');
  });

  it('resolves every message in Spanish without raw keys or counts', () => {
    for (const reason of ['insufficient_base', 'insufficient_metric_contributions', 'other', null]) {
      const key = learningSuppressionMessageKey(reason);
      const message = resolveKey(key);

      // Spanish fallback: a real translated string, never the raw key.
      expect(message, key).toBeDefined();
      expect(message).not.toBe(key);
      // Suppression never carries counts, sizes or thresholds (RF-013.7).
      expect(message).not.toMatch(/\{count\}|\{threshold\}|\d/);
    }

    // Static labels used by the block also resolve in Spanish.
    for (const key of ['progress.collective.learning.title', 'progress.collective.learning.activity_rate', 'progress.collective.learning.path_rates']) {
      expect(resolveKey(key), key).toBeDefined();
    }
  });
});

describe('competency evidence presentation (spec 014)', () => {
  function resolveKey(key: string): string | undefined {
    return key.split('.').reduce<unknown>(
      (node, part) => (node && typeof node === 'object' ? (node as Record<string, unknown>)[part] : undefined),
      esMessages,
    ) as string | undefined;
  }

  const catalogEntry = (overrides: Partial<CompetencyEvidenceEntry> = {}): CompetencyEvidenceEntry => ({
    link_id: 'link-1',
    state: 'current',
    source_type: 'assessment_result',
    source_id: 'result-1',
    instrument: { id: 'a1', slug: 'liderazgo', title: { es: 'Liderazgo', en: 'Leadership' } },
    completed_at: '2025-01-01T00:00:00.000000Z',
    ...overrides,
  });

  const personalityEntry = (overrides: Partial<CompetencyEvidenceEntry> = {}): CompetencyEvidenceEntry => ({
    link_id: 'link-2',
    state: 'current',
    source_type: 'personality_assessment',
    source_id: 'pa-1',
    test_version: 'IPIP-NEO-120',
    completed_at: '2025-02-02T00:00:00.000000Z',
    ...overrides,
  });

  it('returns only explicit evidence entries — unlinked skills and factors have none', () => {
    const skill: CompetencySkillItem = { id: 'us1', skill_tag_id: 'tag-1', tag: 'Comunicación', category: null, level: 3, source: 'skills', evidence: [catalogEntry()] };
    const bare: CompetencySkillItem = { id: 'us2', tag: 'Empatía', category: null, level: 2, source: 'skills' };

    expect(evidenceEntriesForSkill(skill)).toHaveLength(1);
    expect(evidenceEntriesForSkill(bare)).toEqual([]);

    const ocean = { state: 'value', source: 'personality', factors: {}, evidence: { C: [personalityEntry()] } } as OceanBlock;
    expect(evidenceEntriesForOceanFactor(ocean, 'C')).toHaveLength(1);
    expect(evidenceEntriesForOceanFactor(ocean, 'O')).toEqual([]);
  });

  it('presents only state=current as current evidence — never inferred', () => {
    expect(isCurrentEvidence(catalogEntry())).toBe(true);
    for (const state of ['incomplete', 'missing_source', 'blocked'] as const) {
      expect(isCurrentEvidence(catalogEntry({ state }))).toBe(false);
    }
  });

  it('never labels an incomplete or blocked entry as vigente', () => {
    const states = ['current', 'incomplete', 'missing_source', 'blocked'] as const;
    const keys = states.map((s) => evidenceStateKey(s));

    expect(new Set(keys).size).toBe(states.length);
    for (const state of states) {
      const key = evidenceStateKey(state);
      const message = resolveKey(key);
      expect(message, key).toBeDefined();
      expect(message).not.toBe(key);
      if (state !== 'current') {
        // No vigente: the label must not reuse the "current" wording.
        expect(message).not.toBe(resolveKey(evidenceStateKey('current')));
      }
    }
  });

  it('distinguishes source kinds: catalog instrument title vs personality test_version', () => {
    expect(evidenceInstrumentLabel(catalogEntry(), 'es')).toBe('Liderazgo');
    expect(evidenceInstrumentLabel(catalogEntry(), 'en')).toBe('Leadership');
    expect(evidenceInstrumentLabel(personalityEntry(), 'es')).toBe('IPIP-NEO-120');
    expect(evidenceSourceKindKey('assessment_result')).not.toBe(evidenceSourceKindKey('personality_assessment'));
  });

  it('hides source metadata for blocked entries', () => {
    const blocked = personalityEntry({ state: 'blocked', reason: 'entitlement_missing', source_id: undefined, test_version: undefined, completed_at: undefined });
    expect(isCurrentEvidence(blocked)).toBe(false);
    expect(evidenceInstrumentLabel(blocked, 'es')).toBe('');
  });

  it('builds a link payload without level or subject fields', () => {
    const payload = buildEvidenceLinkPayload(
      { source_type: 'assessment_result', source_id: 'r1', label: 'X' },
      'skill_tag',
      'tag-9',
    );

    expect(payload).toEqual({
      source_type: 'assessment_result',
      source_id: 'r1',
      competency_type: 'skill_tag',
      competency_key: 'tag-9',
    });
    expect(payload).not.toHaveProperty('level');
    expect(payload).not.toHaveProperty('user_id');
  });

  it('filters already-linked sources for that competency only', () => {
    const candidates: EvidenceSourceCandidate[] = [
      { source_type: 'assessment_result', source_id: 'r1', label: 'A' },
      { source_type: 'personality_assessment', source_id: 'p1', label: 'B' },
    ];

    const options = availableEvidenceSources(candidates, [catalogEntry({ source_id: 'r1' })]);
    expect(options.map((o) => o.source_id)).toEqual(['p1']);
  });

  it('offers the personality source only when the ocean assessment is completed', () => {
    const ocean = { state: 'value', source: 'personality', factors: {}, assessment_id: 'pa-9', test_version: 'IPIP-NEO-120' } as OceanBlock;
    expect(personalitySourceOption(ocean)).toEqual({ source_type: 'personality_assessment', source_id: 'pa-9', label: 'IPIP-NEO-120' });

    const noData = { state: 'no_data', source: 'personality', factors: {} } as OceanBlock;
    expect(personalitySourceOption(noData)).toBeNull();
  });

  it('resolves every evidence label in es/en/pt without raw keys (RF-15)', () => {
    const resolveIn = (catalog: unknown, key: string): string | undefined =>
      key.split('.').reduce<unknown>(
        (node, part) => (node && typeof node === 'object' ? (node as Record<string, unknown>)[part] : undefined),
        catalog,
      ) as string | undefined;

    const keys = [
      ...(['current', 'incomplete', 'missing_source', 'blocked'] as const).map(evidenceStateKey),
      evidenceSourceKindKey('assessment_result'),
      evidenceSourceKindKey('personality_assessment'),
      'progress.competencies.evidence.manage_title',
      'progress.competencies.evidence.select_competency',
      'progress.competencies.evidence.select_source',
      'progress.competencies.evidence.no_sources',
      'progress.competencies.evidence.link',
      'progress.competencies.evidence.unlink',
      'progress.competencies.evidence.factor_option',
      'progress.competencies.evidence.error_field',
      'progress.competencies.evidence.error_unavailable',
    ];

    // Same key set across the three catalogs — the es fallback is a real
    // string everywhere and no locale renders the raw key.
    const esKeys = new Set(
      keys.filter((key) => resolveIn(esMessages, key) !== undefined),
    );
    expect(esKeys.size).toBe(keys.length);

    for (const catalog of [enMessages, ptMessages]) {
      for (const key of keys) {
        const message = resolveIn(catalog, key);
        expect(message, `${key} missing in catalog`).toBeDefined();
        expect(message).not.toBe(key);
      }
    }
  });
});
