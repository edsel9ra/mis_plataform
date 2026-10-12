'use client';

import { useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import {
  Activity,
  AlertTriangle,
  Brain,
  ClipboardList,
  FileAudio,
  FileText,
  FileVideo,
  GitBranch,
  LockKeyhole,
  Plus,
  Send,
  Target,
} from 'lucide-react';
import { OceanRadarChart } from '@/components/charts/ocean-radar-chart';
import { AttentionComparison } from '@/components/mentor-workspace/AttentionComparison';
import { FeedbackUploadForm } from '@/components/mentor-workspace/FeedbackUploadForm';
import { MentorObservationForm } from '@/components/mentor-workspace/MentorObservationForm';
import { WorkspaceFeatureGate } from '@/components/mentor-workspace/PlanAccess';
import { SystemicMapEditor } from '@/components/mentor-workspace/SystemicMapEditor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  useAssignMentorChallenge,
  useMentorChallengeDetails,
  useMentorChallenges,
  useMentorFeedback,
  useMentorSystemicMap,
  useMentorUserChallenges,
  useMentorUserNeurocognitive,
  useMentorEmotionalCheckIns,
  useMentorAlerts,
  useSaveMentorSystemicMap,
} from '@/hooks/use-mentor-workspace';
import { hasEntitlement, isOceanScoreBelowThreshold } from '@/lib/mentor-workspace';
import type {
  ActiveSubscription,
  MentorChallenge,
  MentorFeedback,
  MentorAlert,
  MentorWorkspaceFeature,
  WorkspaceEntitlements,
  WorkspacePlan,
} from '@/types/mentor-workspace';

type ProfileTab = 'ocean' | 'neurocognitive' | 'systemic-map' | 'challenges' | 'feedback';

interface OceanAssessment {
  interpretation: Record<string, { trait: string; score: number; level: string; needs_improvement?: boolean }>;
}

export function MentorUserProfileTabs({
  userId,
  relationshipId,
  assessment,
  plan,
  subscription,
  entitlements,
  locked = false,
}: {
  userId: string;
  relationshipId: string;
  assessment: OceanAssessment | null;
  plan?: WorkspacePlan | null;
  subscription?: ActiveSubscription | null;
  entitlements?: WorkspaceEntitlements | null;
  locked?: boolean;
}) {
  const t = useTranslations();
  const [tab, setTab] = useState<ProfileTab>('ocean');
  const featureForTab: Record<ProfileTab, MentorWorkspaceFeature> = {
    ocean: 'ocean',
    neurocognitive: 'neurocognitive',
    'systemic-map': 'systemic_map',
    challenges: 'challenges',
    feedback: 'feedback',
  };

  return (
    <section className="space-y-4" aria-label={t('mentor_workspace.profile_tabs_label')}>
      <Tabs value={tab} onValueChange={(value) => setTab(value as ProfileTab)}>
        <TabsList className="flex w-full gap-1 overflow-x-auto rounded-2xl bg-white p-1 shadow-sm ring-1 ring-slate-200">
          <ProfileTabTrigger value="ocean" icon={Brain} label={t('mentor_workspace.tab_ocean')} />
          <ProfileTabTrigger value="neurocognitive" icon={Activity} label={t('mentor_workspace.tab_neurocognitive')} />
          <ProfileTabTrigger value="systemic-map" icon={GitBranch} label={t('mentor_workspace.tab_systemic_map')} />
          <ProfileTabTrigger value="challenges" icon={Target} label={t('mentor_workspace.tab_challenges')} />
          <ProfileTabTrigger value="feedback" icon={Send} label={t('mentor_workspace.tab_feedback')} />
        </TabsList>

        <TabsContent value="ocean">
          <WorkspaceFeatureGate feature={featureForTab.ocean} plan={plan} subscription={subscription} entitlements={entitlements} locked={locked}>
            <OceanPanel assessment={assessment} />
          </WorkspaceFeatureGate>
        </TabsContent>
        <TabsContent value="neurocognitive">
          <WorkspaceFeatureGate feature={featureForTab.neurocognitive} plan={plan} subscription={subscription} entitlements={entitlements} locked={locked}>
            <NeurocognitivePanel userId={userId} relationshipId={relationshipId} entitlements={entitlements} />
          </WorkspaceFeatureGate>
        </TabsContent>
        <TabsContent value="systemic-map">
          <WorkspaceFeatureGate feature={featureForTab['systemic-map']} plan={plan} subscription={subscription} entitlements={entitlements} locked={locked}>
            <SystemicMapPanel userId={userId} relationshipId={relationshipId} />
          </WorkspaceFeatureGate>
        </TabsContent>
        <TabsContent value="challenges">
          <WorkspaceFeatureGate feature={featureForTab.challenges} plan={plan} subscription={subscription} entitlements={entitlements} locked={locked}>
            <ChallengesPanel userId={userId} relationshipId={relationshipId} />
          </WorkspaceFeatureGate>
        </TabsContent>
        <TabsContent value="feedback">
          <WorkspaceFeatureGate feature={featureForTab.feedback} plan={plan} subscription={subscription} entitlements={entitlements} locked={locked}>
            <FeedbackPanel userId={userId} relationshipId={relationshipId} />
          </WorkspaceFeatureGate>
        </TabsContent>
      </Tabs>
    </section>
  );
}

function ProfileTabTrigger({ value, icon: Icon, label }: { value: ProfileTab; icon: typeof Brain; label: string }) {
  return (
    <TabsTrigger value={value} className="min-h-11 gap-2 px-3 text-xs sm:px-4 sm:text-sm">
      <Icon className="h-4 w-4 shrink-0" aria-hidden="true" />
      <span>{label}</span>
    </TabsTrigger>
  );
}

function OceanPanel({ assessment }: { assessment: OceanAssessment | null }) {
  const t = useTranslations();
  const radarData = assessment
    ? Object.entries(assessment.interpretation).map(([key, factor]) => ({
      key,
      trait: factor.trait,
      score: factor.score,
      needsImprovement: factor.needs_improvement === true || isOceanScoreBelowThreshold(factor.score),
    }))
    : [];

  if (!assessment) {
    return (
      <Card className="border-amber-200 bg-amber-50 py-12 text-center">
        <Brain className="mx-auto h-12 w-12 text-amber-500" aria-hidden="true" />
        <h2 className="mt-4 text-xl font-semibold text-amber-950">{t('mentor_dashboard.incomplete_title')}</h2>
        <p className="mx-auto mt-2 max-w-xl text-sm text-amber-800">{t('mentor_dashboard.incomplete_description')}</p>
      </Card>
    );
  }

  return (
    <div className="grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(300px,0.9fr)]">
      <Card>
        <CardHeader>
          <div className="flex items-center gap-2">
            <Brain className="h-5 w-5 text-primary-600" aria-hidden="true" />
            <CardTitle>{t('mentor_dashboard.ocean_title')}</CardTitle>
          </div>
        </CardHeader>
        <div className="h-[360px]"><OceanRadarChart data={radarData} /></div>
      </Card>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
        {Object.entries(assessment.interpretation).map(([key, factor]) => {
          const isLowScore = factor.needs_improvement === true || isOceanScoreBelowThreshold(factor.score);

          return (
            <Card
              key={key}
              padding="sm"
              className={`flex items-center justify-between gap-4 ${isLowScore ? 'border-red-300 bg-red-50/80 ring-1 ring-red-200' : ''}`}
            >
              <div>
                <p className={`text-xs font-bold uppercase tracking-wider ${isLowScore ? 'text-red-700' : 'text-primary-700'}`}>{key}</p>
                <p className={`font-semibold ${isLowScore ? 'text-red-950' : 'text-slate-900'}`}>{factor.trait}</p>
                <p className={`text-sm ${isLowScore ? 'text-red-700' : 'text-slate-500'}`}>{factor.level}</p>
              </div>
              <div className="flex flex-col items-end gap-2">
                {isLowScore && <Badge variant="danger">{t('mentor_workspace.low_score_attention')}</Badge>}
                <span className={`text-2xl font-bold ${isLowScore ? 'text-red-700' : 'text-slate-950'}`} aria-label={isLowScore ? t('mentor_workspace.low_score_attention') : undefined}>{Math.round(factor.score)}</span>
              </div>
            </Card>
          );
        })}
      </div>
    </div>
  );
}

function NeurocognitivePanel({ userId, relationshipId, entitlements }: { userId: string; relationshipId: string; entitlements?: WorkspaceEntitlements | null }) {
  const t = useTranslations();
  const locale = useLocale();
  const profileQuery = useMentorUserNeurocognitive(userId, relationshipId);
  const canObserve = hasEntitlement(entitlements, 'mentor_observations');
  const canViewEmotionalCheckIns = hasEntitlement(entitlements, 'emotional_check_ins');
  const checkInsQuery = useMentorEmotionalCheckIns(userId, relationshipId, canViewEmotionalCheckIns);
  const alertsQuery = useMentorAlerts(userId, relationshipId);
  const profile = profileQuery.data;
  const selfReport = profile?.self_report ?? profile?.mentee_self_report ?? profile?.attention?.self_report ?? profile?.attention?.mentee_self_report;
  const mentorObservation = profile?.mentor_observation ?? profile?.attention?.mentor_observation;

  if (profileQuery.isLoading) return <LoadingCard />;
  if (profileQuery.error) return <ErrorCard />;

  return (
    <div className="space-y-5">
      <div>
        <p className="text-sm font-semibold uppercase tracking-[0.18em] text-primary-700">{t('mentor_workspace.attention_eyebrow')}</p>
        <h2 className="mt-1 text-2xl font-bold text-slate-950">{t('mentor_workspace.neurocognitive_title')}</h2>
        <p className="mt-1 max-w-2xl text-sm text-slate-500">{t('mentor_workspace.neurocognitive_description')}</p>
      </div>
      <AttentionComparison selfReport={selfReport} mentorObservation={mentorObservation} />
      <AlertsPanel alerts={alertsQuery.data} isLoading={alertsQuery.isLoading} hasError={Boolean(alertsQuery.error)} />
      {canObserve ? <MentorObservationForm userId={userId} relationshipId={relationshipId} observation={mentorObservation} /> : <SubfeatureLock title={t('mentor_workspace.mentor_observation')} />}
       <Card>
        <CardHeader>
          <div className="flex items-center gap-2">
            <ClipboardList className="h-5 w-5 text-primary-600" aria-hidden="true" />
            <CardTitle>{t('mentor_workspace.emotional_check_ins')}</CardTitle>
          </div>
        </CardHeader>
         {!canViewEmotionalCheckIns ? <SubfeatureLock title={t('mentor_workspace.emotional_check_ins')} /> : checkInsQuery.isLoading ? <p className="text-sm text-slate-500">{t('common.loading')}</p> : checkInsQuery.error ? <ErrorCard /> : checkInsQuery.data?.length ? (
          <div className="space-y-3">
            {checkInsQuery.data.slice(0, 5).map((checkIn) => (
              <div key={checkIn.id} className="flex items-start justify-between gap-3 rounded-xl border border-slate-100 p-3">
                <div>
                  <p className="text-sm font-medium text-slate-800">{checkIn.note || t('mentor_workspace.check_in_without_note')}</p>
                   <p className="mt-1 text-xs text-slate-500">{formatDate(checkIn.created_at, locale)}</p>
                </div>
                <Badge variant="default">{statusLabel(checkIn.status, t)}</Badge>
              </div>
            ))}
          </div>
        ) : <p className="text-sm text-slate-500">{t('mentor_workspace.no_check_ins')}</p>}
      </Card>
    </div>
  );
}

function SubfeatureLock({ title }: { title: string }) {
  const t = useTranslations();

  return (
    <div className="flex items-start gap-3 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
      <LockKeyhole className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
      <span>{t('mentor_workspace.subfeature_locked_description', { feature: title })}</span>
    </div>
  );
}

function AlertsPanel({ alerts, isLoading, hasError }: { alerts?: MentorAlert[]; isLoading: boolean; hasError: boolean }) {
  const t = useTranslations();
  const locale = useLocale();

  return (
    <Card className="border-amber-200 bg-amber-50/60">
      <CardHeader>
        <div className="flex items-center gap-2">
          <AlertTriangle className="h-5 w-5 text-amber-600" aria-hidden="true" />
          <CardTitle>{t('mentor_workspace.alerts_title')}</CardTitle>
        </div>
      </CardHeader>
      {isLoading ? <p className="text-sm text-slate-500">{t('common.loading')}</p> : hasError ? <ErrorCard /> : alerts?.length ? (
        <div className="space-y-2">
          {alerts.map((alert) => (
            <div key={alert.id} className="rounded-xl border border-amber-200 bg-white/70 p-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm font-semibold text-amber-950">{alertTypeLabel(alert.type, t)}</p>
                <Badge variant={alert.severity === 'high' ? 'danger' : 'warning'}>{alert.severity || t('mentor_workspace.alert_severity_default')}</Badge>
              </div>
              <p className="mt-1 text-xs text-amber-800">{formatDate(alert.detected_at || alert.created_at, locale)}</p>
            </div>
          ))}
        </div>
      ) : <p className="text-sm text-slate-600">{t('mentor_workspace.no_alerts')}</p>}
    </Card>
  );
}

function alertTypeLabel(type: string, t: ReturnType<typeof useTranslations>): string {
  if (type === 'disengagement') return t('mentor_workspace.alert_type_disengagement');
  if (type === 'stagnation') return t('mentor_workspace.alert_type_stagnation');

  return type;
}

function SystemicMapPanel({ userId, relationshipId }: { userId: string; relationshipId: string }) {
  const t = useTranslations();
  const mapQuery = useMentorSystemicMap(userId, relationshipId);
  const saveMap = useSaveMentorSystemicMap(userId, relationshipId);

  if (mapQuery.isLoading) return <LoadingCard />;
  if (mapQuery.error) return <ErrorCard />;

  return (
    <div className="space-y-4">
      <div className="flex items-start gap-3 rounded-2xl border border-violet-200 bg-violet-50 p-4 text-sm text-violet-900">
        <LockKeyhole className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
        <p>{t('mentor_workspace.systemic_map_private_long')}</p>
      </div>
      <SystemicMapEditor
        value={mapQuery.data}
        isSaving={saveMap.isPending}
        onSave={(map) => void saveMap.mutateAsync(map).catch(() => {})}
      />
      {saveMap.isSuccess && <p className="text-sm font-medium text-emerald-700">{t('mentor_workspace.map_saved')}</p>}
      {saveMap.error && <p className="text-sm font-medium text-red-700">{t('mentor_workspace.save_error')}</p>}
    </div>
  );
}

function ChallengesPanel({ userId, relationshipId }: { userId: string; relationshipId: string }) {
  const t = useTranslations();
  const challengesQuery = useMentorUserChallenges(userId, relationshipId);
  const catalogQuery = useMentorChallenges();
  const [selectedChallengeId, setSelectedChallengeId] = useState('');
  const assign = useAssignMentorChallenge(userId, relationshipId);

  async function assignChallenge() {
    if (!selectedChallengeId) return;
    try {
      await assign.mutateAsync({ challenge_id: selectedChallengeId });
      setSelectedChallengeId('');
    } catch {
      // The mutation error is rendered below.
    }
  }

  if (challengesQuery.isLoading) return <LoadingCard />;
  if (challengesQuery.error) return <ErrorCard />;
  const challenges = challengesQuery.data ?? [];

  return (
    <div className="space-y-5">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <p className="text-sm font-semibold uppercase tracking-[0.18em] text-primary-700">{t('mentor_workspace.challenges_eyebrow')}</p>
          <h2 className="mt-1 text-2xl font-bold text-slate-950">{t('mentor_workspace.challenges_title')}</h2>
          <p className="mt-1 text-sm text-slate-500">{t('mentor_workspace.challenges_description')}</p>
        </div>
        {catalogQuery.data && catalogQuery.data.length > 0 && (
          <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
            <Select
              label={t('mentor_workspace.assign_challenge')}
              value={selectedChallengeId}
              onChange={(event) => setSelectedChallengeId(event.target.value)}
              options={catalogQuery.data.map((challenge) => ({ value: challenge.id, label: challenge.title }))}
              placeholder={t('mentor_workspace.select_challenge')}
              className="min-w-52"
            />
            <Button type="button" size="sm" loading={assign.isPending} disabled={!selectedChallengeId} onClick={() => void assignChallenge()}>
              <Plus className="h-4 w-4" aria-hidden="true" /> {t('mentor_workspace.assign')}
            </Button>
          </div>
        )}
      </div>
      {assign.error && <ErrorCard />}
      {challenges.length === 0 ? <Card><p className="text-sm text-slate-500">{t('mentor_workspace.no_challenges')}</p></Card> : (
        <div className="grid gap-4 xl:grid-cols-2">
          {challenges.map((challenge) => <ChallengeActivityCard key={challenge.id} challenge={challenge} relationshipId={relationshipId} />)}
        </div>
      )}
    </div>
  );
}

function ChallengeActivityCard({ challenge, relationshipId }: { challenge: MentorChallenge; relationshipId: string }) {
  const t = useTranslations();
  const locale = useLocale();
  const [expanded, setExpanded] = useState(false);
  const detailsQuery = useMentorChallengeDetails(challenge.id, relationshipId, expanded);
  const contentUrl = challenge.content_url || challenge.content_file_url;
  const checkIns = detailsQuery.data?.check_ins ?? [];
  const submissions = detailsQuery.data?.submissions ?? [];

  return (
    <Card className="border-slate-200">
      <div className="flex items-start justify-between gap-4">
        <div className="min-w-0">
          <h3 className="font-semibold text-slate-950">{challenge.title}</h3>
          {challenge.description && <p className="mt-1 text-sm leading-6 text-slate-600">{challenge.description}</p>}
        </div>
        <Badge variant={challenge.status === 'completed' ? 'success' : 'default'}>{statusLabel(challenge.status, t)}</Badge>
      </div>
      <div className="mt-4 flex flex-wrap items-center gap-3 text-xs text-slate-500">
         {challenge.due_at && <span>{t('mentor_workspace.due_date')}: {formatDate(challenge.due_at, locale)}</span>}
        {contentUrl && <a href={contentUrl} target="_blank" rel="noreferrer" className="font-semibold text-primary-700 hover:underline"><FileText className="mr-1 inline h-3.5 w-3.5" aria-hidden="true" />{challenge.content_name || t('mentor_workspace.open_content')}</a>}
      </div>
      <Button type="button" variant="ghost" size="sm" className="mt-4" onClick={() => setExpanded((current) => !current)}>
        {expanded ? t('mentor_workspace.hide_activity') : t('mentor_workspace.view_activity')}
      </Button>
      {expanded && (
        <div className="mt-3 border-t border-slate-100 pt-4">
          {detailsQuery.isLoading ? <p className="text-xs text-slate-500">{t('common.loading')}</p> : detailsQuery.error ? <ErrorCard /> : (
            <div className="grid gap-4 lg:grid-cols-2">
              <div>
                <h4 className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900"><ClipboardList className="h-4 w-4 text-primary-600" aria-hidden="true" />{t('mentor_workspace.check_ins')}</h4>
                 {checkIns.length ? <div className="space-y-2">{checkIns.map((checkIn) => <div key={checkIn.id} className="rounded-xl bg-slate-50 p-3 text-xs"><div className="flex justify-between gap-2"><span>{statusLabel(checkIn.status, t)}</span><span className="text-slate-400">{formatDate(checkIn.checked_in_at || checkIn.created_at, locale)}</span></div>{checkIn.attention_score !== null && checkIn.attention_score !== undefined && <p className="mt-1 text-slate-600">{t('mentor_workspace.attention_score')}: {checkIn.attention_score}/5</p>}{checkIn.note && <p className="mt-1 text-slate-600">{checkIn.note}</p>}</div>)}</div> : <p className="text-xs text-slate-500">{t('mentor_workspace.no_check_ins')}</p>}
              </div>
              <div>
                <h4 className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900"><FileText className="h-4 w-4 text-primary-600" aria-hidden="true" />{t('mentor_workspace.submissions')}</h4>
                 {submissions.length ? <div className="space-y-2">{submissions.map((submission) => <div key={submission.id} className="rounded-xl bg-slate-50 p-3 text-xs">{submission.file_url ? <a href={submission.file_url} target="_blank" rel="noreferrer" className="font-semibold text-primary-700 hover:underline">{submission.file_name || t('mentor_workspace.open_submission')}</a> : <p className="font-semibold text-slate-700">{t('mentor_workspace.submission_without_file')}</p>}{submission.note && <p className="mt-1 text-slate-600">{submission.note}</p>}<p className="mt-1 text-slate-500">{formatDate(submission.submitted_at || submission.created_at, locale)}</p></div>)}</div> : <p className="text-xs text-slate-500">{t('mentor_workspace.no_submissions')}</p>}
              </div>
            </div>
          )}
        </div>
      )}
    </Card>
  );
}

function FeedbackPanel({ userId, relationshipId }: { userId: string; relationshipId: string }) {
  const t = useTranslations();
  const feedbackQuery = useMentorFeedback(userId, relationshipId);
  if (feedbackQuery.isLoading) return <LoadingCard />;

  return (
    <div className="grid gap-5 lg:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
      <Card>
        <CardHeader>
          <div className="flex items-center gap-2">
            <Send className="h-5 w-5 text-primary-600" aria-hidden="true" />
            <CardTitle>{t('mentor_workspace.feedback_history')}</CardTitle>
          </div>
        </CardHeader>
        {feedbackQuery.error ? <ErrorCard /> : feedbackQuery.data?.length ? <div className="space-y-3">{feedbackQuery.data.map((feedback) => <FeedbackItem key={feedback.id} feedback={feedback} />)}</div> : <p className="text-sm text-slate-500">{t('mentor_workspace.no_feedback')}</p>}
      </Card>
      <Card>
        <CardHeader>
          <div className="flex items-center gap-2"><Send className="h-5 w-5 text-primary-600" aria-hidden="true" /><CardTitle>{t('mentor_workspace.new_feedback')}</CardTitle></div>
        </CardHeader>
        <p className="mb-4 text-sm text-slate-500">{t('mentor_workspace.feedback_description')}</p>
        <FeedbackUploadForm userId={userId} relationshipId={relationshipId} />
      </Card>
    </div>
  );
}

function FeedbackItem({ feedback }: { feedback: MentorFeedback }) {
  const t = useTranslations();
  const locale = useLocale();
  const mediaUrl = feedback.media_url || feedback.file_url;
  const isVideo = feedback.mime_type?.startsWith('video/');
  const Icon = isVideo ? FileVideo : feedback.mime_type?.startsWith('audio/') ? FileAudio : FileText;

  return (
    <div className="flex items-start gap-3 rounded-xl border border-slate-100 p-3">
      <Icon className="mt-0.5 h-5 w-5 shrink-0 text-primary-600" aria-hidden="true" />
      <div className="min-w-0">
        <p className="text-sm text-slate-700">{feedback.note || t('mentor_workspace.media_feedback')}</p>
        <p className="mt-1 text-xs text-slate-500">{formatDate(feedback.created_at, locale)}</p>
        {mediaUrl && <a href={mediaUrl} target="_blank" rel="noreferrer" className="mt-2 inline-flex text-xs font-semibold text-primary-700 hover:underline">{feedback.file_name || t('mentor_workspace.open_feedback')}</a>}
      </div>
    </div>
  );
}

function LoadingCard() {
  const t = useTranslations();
  return <Card><p className="text-sm text-slate-500">{t('common.loading')}</p></Card>;
}

function ErrorCard() {
  const t = useTranslations();
  return <Card className="border-red-200 bg-red-50" role="alert"><p className="text-sm text-red-700">{t('mentor_workspace.feature_load_error')}</p></Card>;
}

function formatDate(value?: string | null, locale = 'es'): string {
  if (!value) return '—';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString(locale);
}

function statusLabel(status: string | null | undefined, t: ReturnType<typeof useTranslations>): string {
  const labels: Record<string, string> = {
    completed: t('mentor_workspace.status_completed'),
    in_progress: t('mentor_workspace.status_in_progress'),
    not_started: t('mentor_workspace.status_not_started'),
    blocked: t('mentor_workspace.status_blocked'),
  };

  return (status && labels[status]) || t('mentor_workspace.status_unknown');
}
