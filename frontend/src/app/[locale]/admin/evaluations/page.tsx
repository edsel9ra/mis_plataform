'use client';

import { useEffect, useState } from 'react';
import { useInfiniteQuery, useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useLocale, useTranslations } from 'next-intl';
import { api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { apiIsoToDatetimeLocal, datetimeLocalToApiIso } from '@/lib/datetime';
import { normalizePaginatedResponse, type PaginatedResponse, type RawPaginatedResponse } from '@/lib/pagination';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert } from '@/components/ui/alert';
import { PaginationControls } from '@/components/ui/pagination';
import { PageSpinner } from '@/components/ui/spinner';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { BarChart3, ClipboardCheck, FileQuestion, ListChecks, Sparkles, type LucideIcon } from 'lucide-react';

interface Assessment {
  id: string;
  user_id: string;
  test_version: string;
  completed_at: string | null;
  answers: Record<string, unknown> | null;
  results: { factors?: Record<string, number> } | null;
  raw_scores: Record<string, number> | null;
  user: { id: string; name: string; last_name: string | null; email: string };
}

interface EvaluationQuestion {
  id?: string | number;
  text: string;
  options: string[];
  dimension?: string;
  reverse_score?: boolean;
}

interface EvaluationCatalogItem {
  id: string;
  slug: string;
  title: Record<string, string>;
  short_name: Record<string, string> | null;
  description: Record<string, string> | null;
  category: string | null;
  client_types: string[] | null;
  questions: EvaluationQuestion[];
  passing_score: number;
  time_limit_minutes: number | null;
  is_active: boolean;
  results_count?: number;
}

interface EvaluationResult {
  id: string;
  score: number;
  passed: boolean;
  answers: { question_id: string | number; option_index: number }[];
  time_spent_seconds: number | null;
  completed_at: string;
  user: { id: string; name: string; last_name: string | null; email: string; client_type?: string | null };
  assessment: EvaluationCatalogItem;
}

interface BriefUser {
  id: string;
  name: string;
  email: string;
}

const CLIENT_TYPE_VALUES = ['personal', 'familiar', 'grupal', 'empresa'] as const;
const CATEGORY_VALUES = ['bienestar', 'desarrollo', 'profesional', 'familiar', 'empresa'] as const;

const emptyAssessmentForm = {
  user_id: '', test_version: 'ipip-neo-120' as string,
  answers: '{}', results: '{}', raw_scores: '{}',
  completed_at: '',
};

function createEmptyCatalogForm(translate: (key: string) => string) {
  return {
    slug: '',
    title_es: '',
    title_en: '',
    title_pt: '',
    short_name_es: '',
    short_name_en: '',
    short_name_pt: '',
    description_es: '',
    description_en: '',
    description_pt: '',
    category: 'bienestar',
    client_types: ['personal'] as string[],
    passing_score: 70,
    time_limit_minutes: 15,
    is_active: true,
    questions: JSON.stringify([
      {
        id: 1,
        text: translate('admin.default_question'),
        options: [
          translate('admin.never'),
          translate('admin.rarely'),
          translate('admin.sometimes'),
          translate('admin.frequently'),
          translate('admin.always'),
        ],
        dimension: 'dimension',
        reverse_score: false,
      },
    ], null, 2),
  };
}

type CatalogForm = ReturnType<typeof createEmptyCatalogForm>;

export default function AdminEvaluations() {
  const t = useTranslations();
  const locale = useLocale();
  const queryClient = useQueryClient();
  const clientTypeOptions = CLIENT_TYPE_VALUES.map((value) => ({ value, label: t(`client_types.${value}`) }));
  const categoryOptions = CATEGORY_VALUES.map((value) => ({ value, label: evaluationCategoryLabel(value, t) }));
  const emptyCatalogForm = createEmptyCatalogForm(t);
  const [selectedAssessment, setSelectedAssessment] = useState<Assessment | null>(null);
  const [selectedEvaluation, setSelectedEvaluation] = useState<EvaluationCatalogItem | null>(null);
  const [selectedEvaluationResult, setSelectedEvaluationResult] = useState<EvaluationResult | null>(null);
  const [assessmentPage, setAssessmentPage] = useState(1);
  const [resultPage, setResultPage] = useState(1);
  const [catalogSearch, setCatalogSearch] = useState('');
  const [resultSearch, setResultSearch] = useState('');
  const [catalogClientType, setCatalogClientType] = useState('');
  const [catalogPage, setCatalogPage] = useState(1);
  const [showAssessmentModal, setShowAssessmentModal] = useState(false);
  const [showCatalogModal, setShowCatalogModal] = useState(false);
  const [editingAssessmentId, setEditingAssessmentId] = useState<string | null>(null);
  const [editingEvaluationId, setEditingEvaluationId] = useState<string | null>(null);
  const [assessmentForm, setAssessmentForm] = useState(emptyAssessmentForm);
  const [catalogForm, setCatalogForm] = useState<CatalogForm>(emptyCatalogForm);
  const [formError, setFormError] = useState<string | null>(null);
  const [mutationError, setMutationError] = useState<unknown>(null);
  const debouncedCatalogSearch = useDebouncedValue(catalogSearch.trim());
  const debouncedResultSearch = useDebouncedValue(resultSearch.trim());

  useEffect(() => setCatalogPage(1), [catalogClientType, debouncedCatalogSearch]);
  useEffect(() => setResultPage(1), [debouncedResultSearch]);

  const { data: assessmentData, isLoading: assessmentsLoading, isFetching: assessmentsFetching, error: assessmentsError } = useQuery<PaginatedResponse<Assessment>>({
    queryKey: ['admin-assessments', assessmentPage],
    queryFn: async ({ signal }) => {
      const response = await api.get<RawPaginatedResponse<Assessment>>('/admin/assessments', {
        page: assessmentPage,
        per_page: 20,
      }, { signal });

      return normalizePaginatedResponse(response, { page: assessmentPage, perPage: 20 });
    },
  });

  const { data: catalogData, isLoading: catalogLoading, isFetching: catalogFetching, error: catalogError } = useQuery<PaginatedResponse<EvaluationCatalogItem>>({
    queryKey: ['admin-evaluation-catalog', debouncedCatalogSearch, catalogClientType, catalogPage],
    queryFn: async ({ signal }) => {
      const response = await api.get<RawPaginatedResponse<EvaluationCatalogItem>>('/admin/evaluation-catalog', {
      search: debouncedCatalogSearch || undefined,
      client_type: catalogClientType || undefined,
        page: catalogPage,
        per_page: 12,
      }, { signal });

      return normalizePaginatedResponse(response, { page: catalogPage, perPage: 12 });
    },
  });

  const { data: resultData, isLoading: resultsLoading, isFetching: resultsFetching, error: resultsError } = useQuery<PaginatedResponse<EvaluationResult>>({
    queryKey: ['admin-evaluation-results', resultPage, debouncedResultSearch],
    queryFn: async ({ signal }) => {
      const response = await api.get<RawPaginatedResponse<EvaluationResult>>('/admin/evaluation-results', {
      page: resultPage,
        per_page: 12,
        search: debouncedResultSearch || undefined,
      }, { signal });

      return normalizePaginatedResponse(response, { page: resultPage, perPage: 12 });
    },
  });

  const usersQuery = useInfiniteQuery({
    queryKey: ['admin-users-brief'],
    initialPageParam: 1,
    queryFn: ({ signal, pageParam }) => api.get<RawPaginatedResponse<BriefUser>>('/admin/users', {
      page: pageParam,
      per_page: 20,
    }, { signal }),
    getNextPageParam: (lastPage) => {
      const meta = lastPage.meta ?? lastPage;
      const currentPage = typeof meta.current_page === 'number' ? meta.current_page : 1;
      const lastPageNumber = typeof meta.last_page === 'number' ? meta.last_page : currentPage;

      return currentPage < lastPageNumber ? currentPage + 1 : undefined;
    },
  });
  const briefUsers = usersQuery.data?.pages.flatMap((pageData) => pageData.data ?? []) ?? [];

  const createAssessmentMutation = useMutation({
    mutationFn: (data: typeof emptyAssessmentForm) => api.post('/admin/assessments', parseAssessmentPayload(data)),
    onSuccess: () => { setMutationError(null); queryClient.invalidateQueries({ queryKey: ['admin-assessments'] }); closeAssessmentModal(); },
    onError: (error) => setMutationError(error),
  });

  const updateAssessmentMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<typeof emptyAssessmentForm> }) =>
      api.put(`/admin/assessments/${id}`, parseAssessmentPayload(data)),
    onSuccess: () => { setMutationError(null); queryClient.invalidateQueries({ queryKey: ['admin-assessments'] }); closeAssessmentModal(); },
    onError: (error) => setMutationError(error),
  });

  const deleteAssessmentMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/admin/assessments/${id}`),
    onSuccess: () => { setMutationError(null); queryClient.invalidateQueries({ queryKey: ['admin-assessments'] }); },
    onError: (error) => setMutationError(error),
  });

  const createEvaluationMutation = useMutation({
    mutationFn: (data: CatalogForm) => api.post('/admin/evaluation-catalog', parseCatalogPayload(data)),
    onSuccess: () => { setMutationError(null); queryClient.invalidateQueries({ queryKey: ['admin-evaluation-catalog'] }); closeCatalogModal(); },
    onError: (error) => setMutationError(error),
  });

  const updateEvaluationMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: CatalogForm }) => api.put(`/admin/evaluation-catalog/${id}`, parseCatalogPayload(data)),
    onSuccess: () => { setMutationError(null); queryClient.invalidateQueries({ queryKey: ['admin-evaluation-catalog'] }); closeCatalogModal(); },
    onError: (error) => setMutationError(error),
  });

  const deleteEvaluationMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/admin/evaluation-catalog/${id}`),
    onSuccess: () => {
      setMutationError(null);
      queryClient.invalidateQueries({ queryKey: ['admin-evaluation-catalog'] });
      setSelectedEvaluation(null);
    },
    onError: (error) => setMutationError(error),
  });

  function openCreateAssessment() {
    setMutationError(null);
    setEditingAssessmentId(null);
    setAssessmentForm(emptyAssessmentForm);
    setFormError(null);
    setShowAssessmentModal(true);
  }

  function openEditAssessment(assessment: Assessment) {
    setMutationError(null);
    setEditingAssessmentId(assessment.id);
    setAssessmentForm({
      user_id: assessment.user_id,
      test_version: assessment.test_version,
      answers: JSON.stringify(assessment.answers || {}, null, 2),
      results: JSON.stringify(assessment.results || {}, null, 2),
      raw_scores: JSON.stringify(assessment.raw_scores || {}, null, 2),
      completed_at: apiIsoToDatetimeLocal(assessment.completed_at),
    });
    setFormError(null);
    setShowAssessmentModal(true);
  }

  function openCreateEvaluation() {
    setMutationError(null);
    setEditingEvaluationId(null);
    setCatalogForm(emptyCatalogForm);
    setFormError(null);
    setShowCatalogModal(true);
  }

  function openEditEvaluation(evaluation: EvaluationCatalogItem) {
    setMutationError(null);
    setEditingEvaluationId(evaluation.id);
    setCatalogForm({
      slug: evaluation.slug,
      title_es: localizedText(evaluation.title, 'es'),
      title_en: localizedText(evaluation.title, 'en'),
      title_pt: localizedText(evaluation.title, 'pt'),
      short_name_es: localizedText(evaluation.short_name, 'es'),
      short_name_en: localizedText(evaluation.short_name, 'en'),
      short_name_pt: localizedText(evaluation.short_name, 'pt'),
      description_es: localizedText(evaluation.description, 'es'),
      description_en: localizedText(evaluation.description, 'en'),
      description_pt: localizedText(evaluation.description, 'pt'),
      category: evaluation.category || 'bienestar',
      client_types: evaluation.client_types?.length ? evaluation.client_types : ['personal'],
      passing_score: evaluation.passing_score,
      time_limit_minutes: evaluation.time_limit_minutes ?? 15,
      is_active: evaluation.is_active,
      questions: JSON.stringify(evaluation.questions || [], null, 2),
    });
    setFormError(null);
    setShowCatalogModal(true);
  }

  function closeAssessmentModal() {
    setShowAssessmentModal(false);
    setEditingAssessmentId(null);
    setAssessmentForm(emptyAssessmentForm);
    setFormError(null);
  }

  function closeCatalogModal() {
    setShowCatalogModal(false);
    setEditingEvaluationId(null);
    setCatalogForm(emptyCatalogForm);
    setFormError(null);
  }

  function handleAssessmentSubmit(e: React.FormEvent) {
    e.preventDefault();
    setMutationError(null);
    try {
      parseAssessmentPayload(assessmentForm);
      if (editingAssessmentId) {
        updateAssessmentMutation.mutate({ id: editingAssessmentId, data: assessmentForm });
      } else {
        createAssessmentMutation.mutate(assessmentForm);
      }
    } catch {
      setFormError(t('admin.invalid_assessment_json'));
    }
  }

  function handleCatalogSubmit(e: React.FormEvent) {
    e.preventDefault();
    setMutationError(null);
    try {
      parseCatalogPayload(catalogForm);
      if (editingEvaluationId) {
        updateEvaluationMutation.mutate({ id: editingEvaluationId, data: catalogForm });
      } else {
        createEvaluationMutation.mutate(catalogForm);
      }
    } catch {
      setFormError(t('admin.invalid_questions_json'));
    }
  }

  if (assessmentsLoading || catalogLoading || resultsLoading) return <PageSpinner />;

  if (assessmentsError || catalogError || resultsError) {
    return <Alert variant="error">{apiErrorMessage(assessmentsError ?? catalogError ?? resultsError, (key) => t(key)) || t('common.load_error')}</Alert>;
  }

  const catalog = catalogData?.data ?? [];
  const evaluationResults = resultData?.data ?? [];
  const totalQuestions = catalog.reduce((sum, item) => sum + (item.questions?.length ?? 0), 0);

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-950">{t('admin.evaluations')}</h1>
          <p className="mt-1 text-sm text-gray-500">
            {t('admin.evaluations_description')}
          </p>
        </div>
        <div className="flex items-center gap-3">
          <Button variant="outline" onClick={openCreateAssessment}>+ {t('admin.create_big_five_result')}</Button>
          <Button onClick={openCreateEvaluation}>+ {t('admin.create_evaluation')}</Button>
        </div>
      </div>

      <section className="grid gap-4 sm:grid-cols-4">
        <SummaryCard label={t('admin.summary_evaluations')} value={catalogData?.meta.total ?? 0} icon={FileQuestion} color="bg-blue-50 text-blue-700" />
        <SummaryCard label={t('admin.summary_questions')} value={totalQuestions} icon={ListChecks} color="bg-emerald-50 text-emerald-700" />
        <SummaryCard label={t('admin.summary_big_five_results')} value={assessmentData?.meta.total ?? 0} icon={ClipboardCheck} color="bg-violet-50 text-violet-700" />
        <SummaryCard label={t('admin.summary_evaluation_results')} value={resultData?.meta.total ?? 0} icon={BarChart3} color="bg-amber-50 text-amber-700" />
      </section>

        {Boolean(formError || mutationError) && (
          <Alert variant="error">
            {formError || apiErrorMessage(mutationError, (key) => t(key)) || t('common.error')}
          </Alert>
        )}

        <Alert variant="info">
        {t('admin.base_templates_notice')}
      </Alert>

      <Tabs defaultValue="catalog" className="space-y-4">
        <TabsList>
          <TabsTrigger value="catalog">{t('admin.catalog_tab')}</TabsTrigger>
          <TabsTrigger value="big-five">{t('admin.big_five_results_tab')}</TabsTrigger>
          <TabsTrigger value="results">{t('admin.evaluation_results_tab')}</TabsTrigger>
        </TabsList>

        <TabsContent value="catalog" className="space-y-6">
          <Card className="border-0 shadow-sm ring-1 ring-slate-100">
            <div className="flex flex-wrap gap-4">
              <div className="min-w-[220px] flex-1">
                <Input aria-label={t('common.search')} placeholder={t('common.search')} value={catalogSearch} onChange={(e) => setCatalogSearch(e.target.value)} />
              </div>
              <div className="w-44">
                <Select options={[{ value: '', label: t('common.all') }, ...clientTypeOptions]} value={catalogClientType} onChange={(e) => setCatalogClientType(e.target.value)} />
              </div>
            </div>
          </Card>

          <div className="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
            <div className={`grid gap-4 sm:grid-cols-2 ${catalogFetching ? 'opacity-60' : ''}`} aria-busy={catalogFetching}>
              {catalog.map((evaluation) => (
                <button
                  key={evaluation.id}
                  onClick={() => setSelectedEvaluation(evaluation)}
                  className="rounded-2xl border bg-white p-5 text-left shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-xl"
                >
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="font-semibold text-gray-950">{localizedText(evaluation.title, locale)}</p>
                      <p className="mt-1 text-sm text-gray-500">{localizedText(evaluation.description, locale)}</p>
                    </div>
                    <Badge variant={evaluation.is_active ? 'success' : 'warning'}>{evaluation.is_active ? t('common.active') : t('common.inactive')}</Badge>
                  </div>
                  <div className="mt-4 flex flex-wrap gap-2">
                    {(evaluation.client_types ?? []).map((clientType) => (
                      <span key={clientType} className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                         {clientTypeLabel(clientType, t)}
                      </span>
                    ))}
                      <span className="rounded-full bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-700">
                       {evaluation.questions?.length ?? 0} {t('evaluations.questions')}
                    </span>
                  </div>
                </button>
              ))}
              {catalog.length === 0 && <p className="text-sm text-gray-500">{t('common.no_results')}</p>}
            </div>

            <Card className="border-0 shadow-sm ring-1 ring-slate-100">
              {selectedEvaluation ? (
                <div className="space-y-5">
                  <CardHeader className="mb-0 p-0">
                    <div className="flex items-start justify-between gap-4">
                      <div>
                        <CardTitle>{localizedText(selectedEvaluation.title, locale)}</CardTitle>
                        <p className="mt-1 text-sm text-gray-500">{selectedEvaluation.slug}</p>
                      </div>
                      <div className="flex gap-2">
                        <Button variant="outline" size="sm" onClick={() => openEditEvaluation(selectedEvaluation)}>{t('common.edit')}</Button>
                        <Button variant="danger" size="sm" onClick={() => { if (confirm(t('common.confirm_delete'))) deleteEvaluationMutation.mutate(selectedEvaluation.id); }}>{t('common.delete')}</Button>
                      </div>
                    </div>
                  </CardHeader>
                  <div className="grid grid-cols-2 gap-3 text-sm">
                    <InfoPill label={t('admin.category')} value={selectedEvaluation.category ? evaluationCategoryLabel(selectedEvaluation.category, t) : '—'} />
                     <InfoPill label={t('admin.time')} value={`${selectedEvaluation.time_limit_minutes ?? 0} ${t('common.minutes_short')}`} />
                    <InfoPill label={t('admin.passing_score')} value={`${selectedEvaluation.passing_score}%`} />
                    <InfoPill label={t('admin.results')} value={selectedEvaluation.results_count ?? 0} />
                  </div>
                  <div className="space-y-3">
                    {selectedEvaluation.questions?.map((question, index) => (
                      <div key={`${question.id ?? index}`} className="rounded-xl border border-slate-100 bg-slate-50 p-3">
                        <p className="text-sm font-medium text-slate-900">{index + 1}. {question.text}</p>
                        <p className="mt-1 text-xs text-slate-500">{question.options.length} {t('admin.options')} · {question.dimension ?? t('admin.no_dimension')}</p>
                      </div>
                    ))}
                  </div>
                </div>
              ) : (
                <div className="flex min-h-80 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 text-center">
                  <Sparkles className="h-8 w-8 text-primary-500" aria-hidden="true" />
                  <p className="mt-3 font-semibold text-slate-900">{t('admin.select_evaluation')}</p>
                  <p className="mt-1 max-w-xs text-sm text-slate-500">{t('admin.edit_evaluation_description')}</p>
                </div>
              )}
            </Card>
          </div>
          {catalogData && (
            <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
              <PaginationControls
                meta={catalogData.meta}
                page={catalogPage}
                onPageChange={setCatalogPage}
                isLoading={catalogFetching}
                ariaLabel={t('common.pagination')}
                previousLabel={t('common.previous')}
                nextLabel={t('common.next')}
                pageLabel={t('common.page')}
                ofLabel={t('common.of')}
              />
            </div>
          )}
        </TabsContent>

        <TabsContent value="big-five" className="grid gap-6 lg:grid-cols-2">
          <Card className="border-0 shadow-sm ring-1 ring-slate-100">
            <CardHeader>
                <CardTitle>{t('admin.big_five_results_tab')}</CardTitle>
            </CardHeader>
            <div className="space-y-2">
              {assessmentData?.data?.map((assessment) => (
                <button
                  key={assessment.id}
                  onClick={() => setSelectedAssessment(assessment)}
                  className="w-full rounded-lg border p-3 text-left transition-colors hover:bg-gray-50"
                >
                  <div className="flex items-center justify-between">
                    <span className="font-medium text-gray-900">
                      {assessment.user.name} {assessment.user.last_name ?? ''}
                    </span>
                    {assessment.completed_at
                      ? <Badge variant="success">{t('common.completed')}</Badge>
                      : <Badge variant="warning">{t('common.pending')}</Badge>}
                  </div>
                  <p className="mt-1 text-sm text-gray-500">
                    {assessment.completed_at ? new Date(assessment.completed_at).toLocaleDateString(locale) : '—'}
                  </p>
                </button>
              ))}
            </div>

            {assessmentData && (
              <PaginationControls
                meta={assessmentData.meta}
                page={assessmentPage}
                onPageChange={setAssessmentPage}
                isLoading={assessmentsFetching}
                ariaLabel={t('common.pagination')}
                previousLabel={t('common.previous')}
                nextLabel={t('common.next')}
                pageLabel={t('common.page')}
                ofLabel={t('common.of')}
              />
            )}
          </Card>

          {selectedAssessment && (
            <Card className="border-0 shadow-sm ring-1 ring-slate-100">
              <CardHeader>
                <div className="flex items-start justify-between">
                  <div>
                    <CardTitle>{selectedAssessment.user.name} {selectedAssessment.user.last_name ?? ''}</CardTitle>
                    <p className="text-sm text-gray-500">{selectedAssessment.user.email}</p>
                  </div>
                  <div className="flex gap-2">
                    <Button variant="outline" size="sm" onClick={() => openEditAssessment(selectedAssessment)}>{t('common.edit')}</Button>
                    <Button variant="danger" size="sm" onClick={() => { if (confirm(t('common.confirm_delete'))) deleteAssessmentMutation.mutate(selectedAssessment.id); }}>{t('common.delete')}</Button>
                  </div>
                </div>
              </CardHeader>

              <div className="space-y-4">
                <InfoPill label={t('admin.version')} value={selectedAssessment.test_version} />
                {selectedAssessment.completed_at && <InfoPill label={t('sessions.date')} value={new Date(selectedAssessment.completed_at).toLocaleDateString(locale)} />}
                {selectedAssessment.results?.factors && (
                  <div>
                    <p className="mb-2 text-sm font-medium text-gray-700">{t('personality.results')}</p>
                    <div className="space-y-2">
                      {Object.entries(selectedAssessment.results.factors).map(([key, val]) => (
                        <div key={key} className="flex items-center justify-between">
                           <span className="text-sm text-gray-600">{oceanLabel(key, t)}</span>
                          <div className="flex items-center gap-2">
                            <div className="h-2 w-32 rounded-full bg-gray-100">
                              <div className="h-2 rounded-full bg-primary-500" style={{ width: `${Math.min(100, val)}%` }} />
                            </div>
                            <span className="text-sm font-medium text-gray-900">{Math.round(val)}</span>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </Card>
          )}
        </TabsContent>

        <TabsContent value="results" className="space-y-6">
          <Card className="border-0 shadow-sm ring-1 ring-slate-100">
            <Input aria-label={t('common.search')} placeholder={t('common.search')} value={resultSearch} onChange={(e) => setResultSearch(e.target.value)} />
          </Card>

          <div className="grid gap-6 lg:grid-cols-[1fr_0.9fr]">
            <Card className="border-0 shadow-sm ring-1 ring-slate-100">
              <CardHeader>
                <CardTitle>{t('admin.evaluation_results_tab')}</CardTitle>
              </CardHeader>
              <div className="space-y-2">
                {evaluationResults.map((result) => (
                  <button
                    key={result.id}
                    onClick={() => setSelectedEvaluationResult(result)}
                    className="w-full rounded-lg border p-3 text-left transition-colors hover:bg-gray-50"
                  >
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <span className="font-medium text-gray-900">
                          {result.user.name} {result.user.last_name ?? ''}
                        </span>
                        <p className="mt-1 text-sm text-gray-500">{localizedText(result.assessment.title, locale)}</p>
                      </div>
                      <Badge variant={result.passed ? 'success' : 'warning'}>{Math.round(result.score)}%</Badge>
                    </div>
                    <p className="mt-1 text-xs text-gray-400">
                      {new Date(result.completed_at).toLocaleDateString(locale)}
                    </p>
                  </button>
                ))}
                {evaluationResults.length === 0 && <p className="text-sm text-gray-500">{t('common.no_results')}</p>}
              </div>

              {resultData && (
                <PaginationControls
                  meta={resultData.meta}
                  page={resultPage}
                  onPageChange={setResultPage}
                  isLoading={resultsFetching}
                  ariaLabel={t('common.pagination')}
                  previousLabel={t('common.previous')}
                  nextLabel={t('common.next')}
                  pageLabel={t('common.page')}
                  ofLabel={t('common.of')}
                />
              )}
            </Card>

            <Card className="border-0 shadow-sm ring-1 ring-slate-100">
              {selectedEvaluationResult ? (
                <div className="space-y-5">
                  <CardHeader className="mb-0 p-0">
                    <div>
                      <CardTitle>{localizedText(selectedEvaluationResult.assessment.title, locale)}</CardTitle>
                      <p className="mt-1 text-sm text-gray-500">
                        {selectedEvaluationResult.user.name} {selectedEvaluationResult.user.last_name ?? ''} · {selectedEvaluationResult.user.email}
                      </p>
                    </div>
                  </CardHeader>
                  <div className="grid grid-cols-2 gap-3 text-sm">
                    <InfoPill label={t('admin.score')} value={`${Math.round(selectedEvaluationResult.score)}%`} />
                    <InfoPill label={t('common.status')} value={selectedEvaluationResult.passed ? t('evaluations.passed') : t('evaluations.failed')} />
                    <InfoPill label={t('admin.date')} value={new Date(selectedEvaluationResult.completed_at).toLocaleDateString(locale)} />
                     <InfoPill label={t('admin.time')} value={formatSeconds(selectedEvaluationResult.time_spent_seconds, t)} />
                  </div>
                  <div>
                    <p className="mb-2 text-sm font-medium text-gray-700">{t('admin.recorded_answers')}</p>
                    <div className="space-y-2">
                      {selectedEvaluationResult.answers.map((answer, index) => (
                        <div key={`${answer.question_id}-${index}`} className="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
                           <span className="text-slate-600">{t('admin.question_number', { number: answer.question_id })}</span>
                           <span className="font-semibold text-slate-950">{t('admin.option_number', { number: answer.option_index + 1 })}</span>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              ) : (
                <div className="flex min-h-80 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 text-center">
                  <BarChart3 className="h-8 w-8 text-primary-500" aria-hidden="true" />
                  <p className="mt-3 font-semibold text-slate-900">{t('admin.select_result')}</p>
                  <p className="mt-1 max-w-xs text-sm text-slate-500">{t('admin.result_details')}</p>
                </div>
              )}
            </Card>
          </div>
        </TabsContent>
      </Tabs>

      <Dialog open={showCatalogModal} onOpenChange={(open) => { if (!open) closeCatalogModal(); else setShowCatalogModal(true); }}>
        <DialogContent aria-labelledby="admin-evaluation-catalog-dialog-title" className="max-w-3xl">
          <DialogHeader>
            <DialogTitle id="admin-evaluation-catalog-dialog-title">{editingEvaluationId ? t('common.edit') : t('common.create')} {t('admin.evaluation')}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleCatalogSubmit} className="mt-4 space-y-4">
            {formError && <Alert variant="error">{formError}</Alert>}
            <div className="grid gap-4 sm:grid-cols-2">
              <Input label={t('admin.slug')} value={catalogForm.slug} onChange={e => setCatalogForm({ ...catalogForm, slug: e.target.value })} required />
              <Select label={t('admin.category')} options={categoryOptions} value={catalogForm.category} onChange={e => setCatalogForm({ ...catalogForm, category: e.target.value })} />
            </div>
            <fieldset className="space-y-3 rounded-xl border border-slate-200 p-4">
              <legend className="px-1 text-sm font-semibold text-slate-700">{t('admin.title_by_language')}</legend>
              <div className="grid gap-4 sm:grid-cols-3">
                <Input label={t('admin.title_es')} value={catalogForm.title_es} onChange={e => setCatalogForm({ ...catalogForm, title_es: e.target.value })} required />
                <Input label={t('admin.title_en')} value={catalogForm.title_en} onChange={e => setCatalogForm({ ...catalogForm, title_en: e.target.value })} />
                <Input label={t('admin.title_pt')} value={catalogForm.title_pt} onChange={e => setCatalogForm({ ...catalogForm, title_pt: e.target.value })} />
              </div>
            </fieldset>
            <fieldset className="space-y-3 rounded-xl border border-slate-200 p-4">
              <legend className="px-1 text-sm font-semibold text-slate-700">{t('admin.short_name_by_language')}</legend>
              <div className="grid gap-4 sm:grid-cols-3">
                <Input label={t('admin.short_name_es')} value={catalogForm.short_name_es} onChange={e => setCatalogForm({ ...catalogForm, short_name_es: e.target.value })} />
                <Input label={t('admin.short_name_en')} value={catalogForm.short_name_en} onChange={e => setCatalogForm({ ...catalogForm, short_name_en: e.target.value })} />
                <Input label={t('admin.short_name_pt')} value={catalogForm.short_name_pt} onChange={e => setCatalogForm({ ...catalogForm, short_name_pt: e.target.value })} />
              </div>
            </fieldset>
            <fieldset className="space-y-3 rounded-xl border border-slate-200 p-4">
              <legend className="px-1 text-sm font-semibold text-slate-700">{t('admin.description_by_language')}</legend>
              <div className="grid gap-4 sm:grid-cols-3">
                <Textarea label={t('admin.description_es')} rows={3} value={catalogForm.description_es} onChange={e => setCatalogForm({ ...catalogForm, description_es: e.target.value })} />
                <Textarea label={t('admin.description_en')} rows={3} value={catalogForm.description_en} onChange={e => setCatalogForm({ ...catalogForm, description_en: e.target.value })} />
                <Textarea label={t('admin.description_pt')} rows={3} value={catalogForm.description_pt} onChange={e => setCatalogForm({ ...catalogForm, description_pt: e.target.value })} />
              </div>
            </fieldset>
            <div className="grid gap-4 sm:grid-cols-2">
              <Input label={t('admin.passing_score')} type="number" value={catalogForm.passing_score} onChange={e => setCatalogForm({ ...catalogForm, passing_score: Number(e.target.value) })} required />
              <Input label={t('admin.time_limit')} type="number" value={catalogForm.time_limit_minutes} onChange={e => setCatalogForm({ ...catalogForm, time_limit_minutes: Number(e.target.value) })} />
            </div>
            <div>
              <span className="block text-sm font-medium text-gray-700">{t('admin.client_types')}</span>
              <div className="mt-2 grid gap-2 sm:grid-cols-4">
                {clientTypeOptions.map((option) => (
                  <label key={option.value} className="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm">
                    <input
                      type="checkbox"
                      checked={catalogForm.client_types.includes(option.value)}
                      onChange={() => setCatalogForm({ ...catalogForm, client_types: toggleValue(catalogForm.client_types, option.value) })}
                    />
                    {option.label}
                  </label>
                ))}
              </div>
            </div>
            <Textarea label={t('admin.questions_json')} rows={10} value={catalogForm.questions} onChange={e => setCatalogForm({ ...catalogForm, questions: e.target.value })} required />
            <label className="flex items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" checked={catalogForm.is_active} onChange={e => setCatalogForm({ ...catalogForm, is_active: e.target.checked })} />
              {t('admin.active_evaluation')}
            </label>
            <DialogFooter>
              <Button variant="outline" type="button" onClick={closeCatalogModal}>{t('common.cancel')}</Button>
              <Button type="submit" loading={createEvaluationMutation.isPending || updateEvaluationMutation.isPending}>
                {editingEvaluationId ? t('common.save') : t('common.create')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={showAssessmentModal} onOpenChange={(open) => { if (!open) closeAssessmentModal(); else setShowAssessmentModal(true); }}>
        <DialogContent aria-labelledby="admin-evaluation-dialog-title">
          <DialogHeader>
            <DialogTitle id="admin-evaluation-dialog-title">{editingAssessmentId ? t('common.edit') : t('common.create')} {t('admin.big_five_result')}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleAssessmentSubmit} className="mt-4 space-y-4">
            {formError && <Alert variant="error">{formError}</Alert>}
            <Select label={t('admin.user')} options={briefUsers.map((u) => ({
              value: u.id, label: `${u.name} (${u.email})`,
            }))} value={assessmentForm.user_id}
              onChange={e => setAssessmentForm({ ...assessmentForm, user_id: e.target.value })} required={!editingAssessmentId} />
            {usersQuery.error && <Alert variant="error">{apiErrorMessage(usersQuery.error, (key) => t(key)) || t('common.load_error')}</Alert>}
            {usersQuery.hasNextPage && (
              <Button
                type="button"
                variant="outline"
                size="sm"
                loading={usersQuery.isFetchingNextPage}
                onClick={() => usersQuery.fetchNextPage()}
              >
                {t('common.load_more')}
              </Button>
            )}
            <Select label={t('admin.test_version')} options={[
              { value: 'ipip-neo-120', label: 'IPIP-NEO-120' },
              { value: 'ipip-neo-300', label: 'IPIP-NEO-300' },
            ]} value={assessmentForm.test_version} onChange={e => setAssessmentForm({ ...assessmentForm, test_version: e.target.value })} />
            <Textarea label={t('admin.answers_json')} rows={3} value={assessmentForm.answers} onChange={e => setAssessmentForm({ ...assessmentForm, answers: e.target.value })} />
            <Textarea label={t('admin.assessment_results_json')} rows={4} value={assessmentForm.results} onChange={e => setAssessmentForm({ ...assessmentForm, results: e.target.value })} />
            <Textarea label={t('admin.raw_scores_json')} rows={3} value={assessmentForm.raw_scores} onChange={e => setAssessmentForm({ ...assessmentForm, raw_scores: e.target.value })} />
            <Input label={t('admin.completed_at')} type="datetime-local" value={assessmentForm.completed_at} onChange={e => setAssessmentForm({ ...assessmentForm, completed_at: e.target.value })} />
            <DialogFooter>
              <Button variant="outline" type="button" onClick={closeAssessmentModal}>{t('common.cancel')}</Button>
              <Button type="submit" loading={createAssessmentMutation.isPending || updateAssessmentMutation.isPending}>
                {editingAssessmentId ? t('common.save') : t('common.create')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function SummaryCard({ label, value, icon: Icon, color }: { label: string; value: number; icon: LucideIcon; color: string }) {
  return (
    <Card className="border-0 shadow-sm ring-1 ring-slate-100">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm text-gray-500">{label}</p>
          <p className="mt-2 text-2xl font-bold text-gray-950">{value}</p>
        </div>
        <span className={`rounded-2xl p-3 ${color}`}>
          <Icon className="h-5 w-5" aria-hidden="true" />
        </span>
      </div>
    </Card>
  );
}

function InfoPill({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
      <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-1 font-semibold text-slate-950">{value}</p>
    </div>
  );
}

function parseAssessmentPayload(data: Partial<typeof emptyAssessmentForm>) {
  return {
    ...data,
    answers: data.answers ? JSON.parse(data.answers) : undefined,
    results: data.results ? JSON.parse(data.results) : undefined,
    raw_scores: data.raw_scores ? JSON.parse(data.raw_scores) : undefined,
    completed_at: data.completed_at ? datetimeLocalToApiIso(data.completed_at) : undefined,
  };
}

function parseCatalogPayload(data: CatalogForm) {
  const questions = JSON.parse(data.questions) as EvaluationQuestion[];

  if (!Array.isArray(questions) || questions.some((question) => !question.text || !Array.isArray(question.options) || question.options.length < 2)) {
    throw new Error('Invalid questions');
  }

  return {
    slug: data.slug,
    title: {
      es: data.title_es,
      en: data.title_en || data.title_es,
      pt: data.title_pt || data.title_es,
    },
    short_name: data.short_name_es || data.short_name_en || data.short_name_pt
      ? {
          es: data.short_name_es || data.short_name_en || data.short_name_pt,
          en: data.short_name_en || data.short_name_es || data.short_name_pt,
          pt: data.short_name_pt || data.short_name_es || data.short_name_en,
        }
      : undefined,
    description: data.description_es || data.description_en || data.description_pt
      ? {
          es: data.description_es || data.description_en || data.description_pt,
          en: data.description_en || data.description_es || data.description_pt,
          pt: data.description_pt || data.description_es || data.description_en,
        }
      : undefined,
    category: data.category,
    client_types: data.client_types,
    questions,
    passing_score: data.passing_score,
    time_limit_minutes: data.time_limit_minutes || undefined,
    is_active: data.is_active,
    metadata: { source: 'admin' },
  };
}

function localizedText(value: Record<string, string> | null | undefined, locale: string): string {
  if (!value) return '';

  return value[locale] ?? value.es ?? value.en ?? value.pt ?? '';
}

function evaluationCategoryLabel(category: string, t: ReturnType<typeof useTranslations>): string {
  const labels: Record<string, string> = {
    bienestar: t('evaluation_categories.wellbeing'),
    desarrollo: t('evaluation_categories.development'),
    profesional: t('evaluation_categories.professional'),
    familiar: t('evaluation_categories.family'),
    empresa: t('evaluation_categories.business'),
  };

  return labels[category] ?? t('common.not_applicable');
}

function clientTypeLabel(type: string, t: (translationKey: string) => string): string {
  const labels: Record<string, string> = {
    personal: 'client_types.personal',
    familiar: 'client_types.familiar',
    grupal: 'client_types.grupal',
    empresa: 'client_types.empresa',
  };

  return labels[type] ? t(labels[type]) : t('common.not_applicable');
}

function oceanLabel(key: string, t: (translationKey: string) => string): string {
  const labels: Record<string, string> = {
    O: 'personality.openness',
    C: 'personality.conscientiousness',
    E: 'personality.extraversion',
    A: 'personality.agreeableness',
    N: 'personality.neuroticism',
  };

  return labels[key] ? t(labels[key]) : t('common.not_applicable');
}

function toggleValue(values: string[], value: string): string[] {
  return values.includes(value)
    ? values.filter((item) => item !== value)
    : [...values, value];
}

function formatSeconds(value: number | null, t: (key: string) => string): string {
  if (!value) return `0 ${t('common.minutes_short')}`;

  const minutes = Math.floor(value / 60);
  const seconds = value % 60;

  return `${minutes} ${t('common.minutes_short')} ${seconds} ${t('common.seconds_short')}`;
}
