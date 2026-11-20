'use client';

import { useRef, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { Download, Plus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { isObjectiveOverdue, objectiveStatusBadgeVariant } from '@/lib/progress';
import { useObjectives } from '@/hooks/use-progress';
import type { Objective, ObjectivesBlock as ObjectivesBlockData, ProgressPeriod } from '@/types/progress';
import { ObjectiveEvidenceForm } from './ObjectiveEvidenceForm';
import { ObjectiveForm } from './ObjectiveForm';
import { ObjectiveProposalForm } from './ObjectiveProposalForm';
import { ObjectiveStatusControl } from './ObjectiveStatusControl';
import { ProgressCard } from './ProgressCard';

interface ObjectivesBlockProps {
  subjectId: string;
  block: ObjectivesBlockData;
  period?: ProgressPeriod | null;
  canCreate?: boolean;
  relationshipId?: string;
}

/**
 * Objectives list with status and overdue marks. Administration actions
 * (edit/transition) are hidden when the viewer cannot manage the objective;
 * evidence-only viewers see just the evidence form (RF-1, RF-2).
 */
export function ObjectivesBlock({ subjectId, block, period, canCreate = false, relationshipId }: ObjectivesBlockProps) {
  const t = useTranslations();
  const query = useObjectives(subjectId);
  const [showCreate, setShowCreate] = useState(false);
  const [showProposal, setShowProposal] = useState(false);
  const createButtonRef = useRef<HTMLButtonElement>(null);
  const [feedback, setFeedback] = useState('');

  const objectives = query.data?.data ?? [];

  function onCreated() {
    setShowCreate(false);
    setFeedback(t('progress.feedback.objective_created'));
    createButtonRef.current?.focus();
  }

  const headerAction = (
    <div className="flex gap-2">
      {block.allow_proposal ? (
        <Button type="button" size="sm" variant="outline" onClick={() => setShowProposal((open) => !open)}>
          {t('progress.objectives.propose')}
        </Button>
      ) : null}
      {canCreate ? (
        <Button ref={createButtonRef} type="button" size="sm" onClick={() => setShowCreate((open) => !open)}>
          <Plus className="h-4 w-4" aria-hidden="true" />
          {t('progress.objectives.add')}
        </Button>
      ) : null}
    </div>
  );

  return (
    <ProgressCard title={t('progress.objectives.title')} period={period} action={headerAction}>
      <div role="status" className="sr-only">{feedback}</div>
      {showCreate ? (
        <div className="mb-4 rounded-xl border border-slate-200 p-4">
          <ObjectiveForm
            ownerId={subjectId}
            relationshipId={relationshipId}
            onDone={onCreated}
            onCancel={() => setShowCreate(false)}
          />
        </div>
      ) : null}

      {showProposal ? (
        <div className="mb-4 rounded-xl border border-slate-200 p-4">
          <ObjectiveProposalForm
            relationshipId={relationshipId}
            onDone={() => setShowProposal(false)}
            onCancel={() => setShowProposal(false)}
          />
        </div>
      ) : null}

      {query.isLoading ? (
        <div className="flex justify-center py-6">
          <Spinner />
        </div>
      ) : objectives.length > 0 ? (
        <ul className="space-y-3">
          {objectives.map((objective) => (
            <ObjectiveRow key={objective.id} objective={objective} />
          ))}
        </ul>
      ) : (
        <p className="text-sm text-slate-500">{t('progress.objectives.empty')}</p>
      )}
    </ProgressCard>
  );
}

function ObjectiveRow({ objective }: { objective: Objective }) {
  const t = useTranslations();
  const locale = useLocale();
  const [editing, setEditing] = useState(false);
  const [addingEvidence, setAddingEvidence] = useState(false);
  const editButtonRef = useRef<HTMLButtonElement>(null);
  const evidenceButtonRef = useRef<HTMLButtonElement>(null);
  const [feedback, setFeedback] = useState('');

  function onEdited() {
    setEditing(false);
    setFeedback(t('progress.form.saved'));
    editButtonRef.current?.focus();
  }

  function onEvidenceAdded() {
    setAddingEvidence(false);
    setFeedback(t('progress.feedback.evidence_added'));
    evidenceButtonRef.current?.focus();
  }

  const canManage = objective.permissions?.update || objective.permissions?.transition;
  const overdue = isObjectiveOverdue(objective);

  return (
    <li className="rounded-xl border border-slate-200 p-4">
      <div role="status" className="sr-only">{feedback}</div>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="font-medium text-slate-900">{objective.title}</p>
          {objective.due_at ? (
            <p className="mt-0.5 text-xs text-slate-500">
              {new Date(objective.due_at).toLocaleDateString(locale)}
            </p>
          ) : null}
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant={objectiveStatusBadgeVariant(objective.status)}>
            {t(`progress.objectives.status.${objective.status}`)}
          </Badge>
          {overdue ? <Badge variant="danger">{t('progress.objectives.overdue')}</Badge> : null}
        </div>
      </div>

      {canManage ? (
        <div className="mt-3 border-t border-slate-100 pt-3">
          <ObjectiveStatusControl objective={objective} />
          {objective.permissions?.update ? (
            <div className="mt-3">
              <Button ref={editButtonRef} type="button" size="sm" variant="ghost" onClick={() => setEditing((open) => !open)}>
                {t('progress.objectives.edit')}
              </Button>
              {editing ? (
                <div className="mt-2">
                  <ObjectiveForm
                    ownerId={objective.owner_id}
                    objective={objective}
                    onDone={onEdited}
                    onCancel={() => setEditing(false)}
                  />
                </div>
              ) : null}
            </div>
          ) : null}
        </div>
      ) : null}

      {objective.evidences && objective.evidences.length > 0 ? (
        <ul className="mt-3 space-y-2 border-t border-slate-100 pt-3">
          {objective.evidences.map((evidence) => (
            <li key={evidence.id} className="text-sm text-slate-700">
              {evidence.note ? <p className="whitespace-pre-wrap">{evidence.note}</p> : null}
              {evidence.media_asset ? (
                <a
                  href={`/api/v1/media/${evidence.media_asset.id}`}
                  aria-label={t('progress.evidence.file_link', { name: evidence.media_asset.original_name })}
                  className="mt-1 inline-flex min-h-6 items-center gap-1.5 text-primary-700 underline decoration-primary-300 underline-offset-2 hover:text-primary-800"
                >
                  <Download className="h-4 w-4 shrink-0" aria-hidden="true" />
                  <span className="truncate">{evidence.media_asset.original_name}</span>
                </a>
              ) : null}
            </li>
          ))}
        </ul>
      ) : null}

      {objective.permissions?.add_evidence ? (
        <div className="mt-3 border-t border-slate-100 pt-3">
          <Button ref={evidenceButtonRef} type="button" size="sm" variant="ghost" onClick={() => setAddingEvidence((open) => !open)}>
            {t('progress.evidence.title')}
          </Button>
          {addingEvidence ? (
            <div className="mt-2">
              <ObjectiveEvidenceForm objectiveId={objective.id} onDone={onEvidenceAdded} />
            </div>
          ) : null}
        </div>
      ) : null}
    </li>
  );
}
