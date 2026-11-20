'use client';

import { useMemo, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { Lock, Unlink } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import {
  availableEvidenceSources,
  buildEvidenceLinkPayload,
  evidenceEntriesForOceanFactor,
  evidenceEntriesForSkill,
  evidenceInstrumentLabel,
  evidenceLinkErrorMessage,
  evidenceStateKey,
  isCurrentEvidence,
  personalitySourceOption,
} from '@/lib/progress';
import {
  useEvidenceSourceCandidates,
  useRemoveCompetencyEvidenceLink,
  useSetCompetencyEvidenceLink,
} from '@/hooks/use-progress';
import { cn } from '@/lib/utils';
import type {
  CompetenciesBlock,
  CompetencyEvidenceEntry,
  EvidenceSourceCandidate,
} from '@/types/progress';
import { ProgressCard } from './ProgressCard';

const OCEAN_FACTORS = ['O', 'C', 'E', 'A', 'N'] as const;

/**
 * Competencies with Skills and Personality (OCEAN) kept as separate sources
 * (P9/P15). OCEAN can be `blocked` (no entitlement) or `no_data` (not
 * completed), never surfaced as zeros (RF-7, CA-7.4).
 *
 * Spec 014: explicit evidence links render as context chips — never inferred,
 * never merged with the level — and only `current` is labelled vigente.
 * `canManage` (owner or assigned mentor) enables link/unlink controls;
 * support and other read-only viewers never see them.
 */
export function CompetenciesCard({
  block,
  subjectId,
  canManage = false,
}: {
  block: CompetenciesBlock;
  /** Subject id for mentor routes — undefined uses the owner endpoints. */
  subjectId?: string;
  canManage?: boolean;
}) {
  const t = useTranslations();
  const locale = useLocale();
  const ocean = block.ocean;

  const sourcesQuery = useEvidenceSourceCandidates(locale, subjectId, canManage);
  const setLink = useSetCompetencyEvidenceLink(subjectId);
  const removeLink = useRemoveCompetencyEvidenceLink(subjectId);

  const [competencyChoice, setCompetencyChoice] = useState('');
  const [sourceChoice, setSourceChoice] = useState('');

  const candidates = useMemo<EvidenceSourceCandidate[]>(() => {
    const base = sourcesQuery.data ?? [];
    const personality = personalitySourceOption(ocean);
    return personality ? [...base, personality] : base;
  }, [sourcesQuery.data, ocean]);

  const competencyOptions = useMemo(() => {
    const skills = block.skills
      .filter((skill) => Boolean(skill.skill_tag_id))
      .map((skill) => ({ value: `skill_tag:${skill.skill_tag_id}`, label: skill.tag ?? '—' }));
    // Linking an ocean_factor requires the OCEAN entitlement — a blocked block
    // would only produce a 403, so those options are not offered.
    const factors =
      ocean.state === 'blocked'
        ? []
        : OCEAN_FACTORS.map((factor) => ({
            value: `ocean_factor:${factor}`,
            label: t('progress.competencies.evidence.factor_option', { factor }),
          }));
    return [...skills, ...factors];
  }, [block.skills, ocean.state, t]);

  const linkedForChoice = useMemo<CompetencyEvidenceEntry[]>(() => {
    const [type, key] = competencyChoice.split(':');
    if (type === 'skill_tag') {
      const skill = block.skills.find((item) => item.skill_tag_id === key);
      return skill ? evidenceEntriesForSkill(skill) : [];
    }
    if (type === 'ocean_factor') return evidenceEntriesForOceanFactor(ocean, key);
    return [];
  }, [competencyChoice, block.skills, ocean]);

  const sourceOptions = useMemo(
    () =>
      availableEvidenceSources(candidates, linkedForChoice).map((candidate) => ({
        value: `${candidate.source_type}:${candidate.source_id}`,
        label: candidate.label,
      })),
    [candidates, linkedForChoice],
  );

  const link = () => {
    const [competencyType, competencyKey] = competencyChoice.split(':') as [
      'skill_tag' | 'ocean_factor',
      string,
    ];
    const candidate = candidates.find(
      (option) => `${option.source_type}:${option.source_id}` === sourceChoice,
    );
    if (!competencyType || !competencyKey || !candidate) return;

    setLink.mutate(
      { payload: buildEvidenceLinkPayload(candidate, competencyType, competencyKey) },
      { onSuccess: () => setSourceChoice('') },
    );
  };

  const unlinkLabel = (entry: CompetencyEvidenceEntry) =>
    t('progress.competencies.evidence.unlink', {
      source: evidenceInstrumentLabel(entry, locale) || t(evidenceStateKey(entry.state)),
    });

  const evidenceChip = (entry: CompetencyEvidenceEntry) => {
    const current = isCurrentEvidence(entry);
    const label = evidenceInstrumentLabel(entry, locale);
    const detail =
      current && entry.completed_at
        ? ` · ${new Date(entry.completed_at).toLocaleDateString()}`
        : '';

    return (
      <span
        key={entry.link_id}
        className={cn(
          'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs',
          current
            ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
            : 'border-slate-200 bg-slate-50 text-slate-500',
        )}
      >
        {entry.state === 'blocked' ? <Lock className="h-3 w-3" aria-hidden="true" /> : null}
        <span>
          {label ? `${label} · ` : ''}
          {t(evidenceStateKey(entry.state))}
          {detail}
        </span>
        {canManage ? (
          <button
            type="button"
            className="rounded-full p-0.5 text-slate-400 transition-colors hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            aria-label={unlinkLabel(entry)}
            disabled={removeLink.isPending}
            onClick={() => removeLink.mutate(entry.link_id)}
          >
            <Unlink className="h-3 w-3" aria-hidden="true" />
          </button>
        ) : null}
      </span>
    );
  };

  const linkError = evidenceLinkErrorMessage(setLink.error ?? removeLink.error);

  return (
    <ProgressCard title={t('progress.competencies.title')}>
      <div className="grid gap-5 sm:grid-cols-2">
        <section aria-label={t('progress.competencies.skills_title')}>
          <h3 className="text-sm font-semibold text-slate-900">{t('progress.competencies.skills_title')}</h3>
          <p className="mt-0.5 text-xs text-slate-500">{t('progress.competencies.source_skills')}</p>
          {block.skills.length > 0 ? (
            <ul className="mt-2 space-y-2">
              {block.skills.map((skill) => (
                <li key={skill.id ?? skill.tag} className="flex flex-wrap items-center gap-2">
                  <Badge variant="primary">
                    {skill.tag ?? '—'}
                    {skill.level != null ? ` · ${skill.level}` : ''}
                  </Badge>
                  {evidenceEntriesForSkill(skill).map(evidenceChip)}
                </li>
              ))}
            </ul>
          ) : (
            <p className="mt-2 text-sm text-slate-500">{t('progress.competencies.skills_empty')}</p>
          )}
        </section>

        <section aria-label={t('progress.competencies.personality_title')}>
          <h3 className="text-sm font-semibold text-slate-900">{t('progress.competencies.personality_title')}</h3>
          <p className="mt-0.5 text-xs text-slate-500">{t('progress.competencies.source_personality')}</p>

          {ocean.state === 'value' && ocean.factors ? (
            <>
              <dl className="mt-2 flex flex-wrap gap-3">
                {Object.entries(ocean.factors).map(([factor, score]) => (
                  <div key={factor} className="rounded-lg bg-slate-50 px-2.5 py-1 text-center">
                    <dt className="text-xs font-semibold uppercase text-slate-500">{factor}</dt>
                    <dd className="text-sm font-semibold text-slate-900">{Math.round(Number(score))}</dd>
                  </div>
                ))}
              </dl>
              {Object.entries(ocean.evidence ?? {}).map(([factor, entries]) =>
                entries.length > 0 ? (
                  <div key={factor} className="mt-2 flex flex-wrap items-center gap-2">
                    <span className="text-xs font-semibold uppercase text-slate-500">{factor}</span>
                    {entries.map(evidenceChip)}
                  </div>
                ) : null,
              )}
              {ocean.assessed_at ? (
                <p className="mt-2 text-xs text-slate-500">
                  {t('progress.competencies.assessed_at', {
                    date: new Date(ocean.assessed_at).toLocaleDateString(),
                  })}
                </p>
              ) : null}
            </>
          ) : ocean.state === 'blocked' ? (
            <>
              <p className="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500">
                <Lock className="h-4 w-4" aria-hidden="true" />
                {t('progress.competencies.ocean_blocked')}
              </p>
              {Object.entries(ocean.evidence ?? {}).map(([factor, entries]) =>
                entries.length > 0 ? (
                  <div key={factor} className="mt-2 flex flex-wrap items-center gap-2">
                    <span className="text-xs font-semibold uppercase text-slate-500">{factor}</span>
                    {entries.map(evidenceChip)}
                  </div>
                ) : null,
              )}
            </>
          ) : (
            <p className="mt-2 text-sm text-slate-500">{t('progress.competencies.ocean_no_data')}</p>
          )}
        </section>
      </div>

      {canManage ? (
        <div className="mt-4 border-t border-slate-100 pt-3">
          <h3 className="text-sm font-semibold text-slate-900">
            {t('progress.competencies.evidence.manage_title')}
          </h3>
          <div className="mt-2 grid items-end gap-2 sm:grid-cols-[1fr_1fr_auto]">
            <Select
              label={t('progress.competencies.evidence.select_competency')}
              options={competencyOptions}
              placeholder={t('progress.competencies.evidence.select_competency_placeholder')}
              value={competencyChoice}
              onChange={(event) => {
                setCompetencyChoice(event.target.value);
                setSourceChoice('');
              }}
            />
            <Select
              label={t('progress.competencies.evidence.select_source')}
              options={sourceOptions}
              placeholder={
                sourceOptions.length === 0
                  ? t('progress.competencies.evidence.no_sources')
                  : t('progress.competencies.evidence.select_source_placeholder')
              }
              value={sourceChoice}
              onChange={(event) => setSourceChoice(event.target.value)}
            />
            <Button
              type="button"
              size="sm"
              disabled={!competencyChoice || !sourceChoice}
              loading={setLink.isPending}
              onClick={link}
            >
              {t('progress.competencies.evidence.link')}
            </Button>
          </div>
          {linkError ? (
            <p role="alert" className="mt-2 text-sm text-red-600">
              {t(linkError.key)}
            </p>
          ) : null}
        </div>
      ) : null}
    </ProgressCard>
  );
}
