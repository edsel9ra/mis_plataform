'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useLocale, useTranslations } from 'next-intl';
import { api } from '@/lib/api';
import { apiIsoToDatetimeLocal, datetimeLocalToApiIso } from '@/lib/datetime';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert } from '@/components/ui/alert';
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

interface PaginatedResponse<T> {
  data: T[];
  total: number;
  current_page: number;
  last_page: number;
}

const CLIENT_TYPE_OPTIONS = [
  { value: 'personal', label: 'Personal' },
  { value: 'familiar', label: 'Familiar' },
  { value: 'grupal', label: 'Grupal' },
  { value: 'empresa', label: 'Empresa' },
];

const CATEGORY_OPTIONS = [
  { value: 'bienestar', label: 'Bienestar' },
  { value: 'desarrollo', label: 'Desarrollo' },
  { value: 'profesional', label: 'Profesional' },
  { value: 'familiar', label: 'Familiar' },
  { value: 'empresa', label: 'Empresa' },
];

const emptyAssessmentForm = {
  user_id: '', test_version: 'ipip-neo-120' as string,
  answers: '{}', results: '{}', raw_scores: '{}',
  completed_at: '',
};

const emptyCatalogForm = {
  slug: '',
  title_es: '',
  short_name_es: '',
  description_es: '',
  category: 'bienestar',
  client_types: ['personal'] as string[],
  passing_score: 70,
  time_limit_minutes: 15,
  is_active: true,
  questions: JSON.stringify([
    {
      id: 1,
      text: 'Pregunta de evaluacion',
      options: ['Nunca', 'Raramente', 'A veces', 'Frecuentemente', 'Siempre'],
      dimension: 'dimension',
      reverse_score: false,
    },
  ], null, 2),
};

type CatalogForm = typeof emptyCatalogForm;

export default function AdminEvaluations() {
  const t = useTranslations();
  const locale = useLocale();
  const queryClient = useQueryClient();
  const [selectedAssessment, setSelectedAssessment] = useState<Assessment | null>(null);
  const [selectedEvaluation, setSelectedEvaluation] = useState<EvaluationCatalogItem | null>(null);
  const [selectedEvaluationResult, setSelectedEvaluationResult] = useState<EvaluationResult | null>(null);
  const [assessmentPage, setAssessmentPage] = useState(1);
  const [resultPage, setResultPage] = useState(1);
  const [catalogSearch, setCatalogSearch] = useState('');
  const [resultSearch, setResultSearch] = useState('');
  const [catalogClientType, setCatalogClientType] = useState('');
  const [showAssessmentModal, setShowAssessmentModal] = useState(false);
  const [showCatalogModal, setShowCatalogModal] = useState(false);
  const [editingAssessmentId, setEditingAssessmentId] = useState<string | null>(null);
  const [editingEvaluationId, setEditingEvaluationId] = useState<string | null>(null);
  const [assessmentForm, setAssessmentForm] = useState(emptyAssessmentForm);
  const [catalogForm, setCatalogForm] = useState<CatalogForm>(emptyCatalogForm);
  const [formError, setFormError] = useState<string | null>(null);

  const { data: assessmentData, isLoading: assessmentsLoading } = useQuery<PaginatedResponse<Assessment>>({
    queryKey: ['admin-assessments', assessmentPage],
    queryFn: () => api.get<PaginatedResponse<Assessment>>('/admin/assessments', { page: assessmentPage }),
  });

  const { data: catalogData, isLoading: catalogLoading } = useQuery<PaginatedResponse<EvaluationCatalogItem>>({
    queryKey: ['admin-evaluation-catalog', catalogSearch, catalogClientType],
    queryFn: () => api.get<PaginatedResponse<EvaluationCatalogItem>>('/admin/evaluation-catalog', {
      search: catalogSearch || undefined,
      client_type: catalogClientType || undefined,
      per_page: 100,
    }),
  });

  const { data: resultData, isLoading: resultsLoading } = useQuery<PaginatedResponse<EvaluationResult>>({
    queryKey: ['admin-evaluation-results', resultPage, resultSearch],
    queryFn: () => api.get<PaginatedResponse<EvaluationResult>>('/admin/evaluation-results', {
      page: resultPage,
      search: resultSearch || undefined,
    }),
  });

  const { data: usersData } = useQuery<{ data: { id: string; name: string; email: string }[] }>({
    queryKey: ['admin-users-brief'],
    queryFn: () => api.get('/admin/users', { per_page: 200 }),
  });

  const createAssessmentMutation = useMutation({
    mutationFn: (data: typeof emptyAssessmentForm) => api.post('/admin/assessments', parseAssessmentPayload(data)),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin-assessments'] }); closeAssessmentModal(); },
  });

  const updateAssessmentMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<typeof emptyAssessmentForm> }) =>
      api.put(`/admin/assessments/${id}`, parseAssessmentPayload(data)),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin-assessments'] }); closeAssessmentModal(); },
  });

  const deleteAssessmentMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/admin/assessments/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-assessments'] }),
  });

  const createEvaluationMutation = useMutation({
    mutationFn: (data: CatalogForm) => api.post('/admin/evaluation-catalog', parseCatalogPayload(data)),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin-evaluation-catalog'] }); closeCatalogModal(); },
  });

  const updateEvaluationMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: CatalogForm }) => api.put(`/admin/evaluation-catalog/${id}`, parseCatalogPayload(data)),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin-evaluation-catalog'] }); closeCatalogModal(); },
  });

  const deleteEvaluationMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/admin/evaluation-catalog/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-evaluation-catalog'] });
      setSelectedEvaluation(null);
    },
  });

  function openCreateAssessment() {
    setEditingAssessmentId(null);
    setAssessmentForm(emptyAssessmentForm);
    setFormError(null);
    setShowAssessmentModal(true);
  }

  function openEditAssessment(assessment: Assessment) {
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
    setEditingEvaluationId(null);
    setCatalogForm(emptyCatalogForm);
    setFormError(null);
    setShowCatalogModal(true);
  }

  function openEditEvaluation(evaluation: EvaluationCatalogItem) {
    setEditingEvaluationId(evaluation.id);
    setCatalogForm({
      slug: evaluation.slug,
      title_es: localizedText(evaluation.title, locale),
      short_name_es: localizedText(evaluation.short_name, locale),
      description_es: localizedText(evaluation.description, locale),
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
    try {
      parseAssessmentPayload(assessmentForm);
      if (editingAssessmentId) {
        updateAssessmentMutation.mutate({ id: editingAssessmentId, data: assessmentForm });
      } else {
        createAssessmentMutation.mutate(assessmentForm);
      }
    } catch {
      setFormError('Revisa que answers, results y raw_scores sean JSON valido.');
    }
  }

  function handleCatalogSubmit(e: React.FormEvent) {
    e.preventDefault();
    try {
      parseCatalogPayload(catalogForm);
      if (editingEvaluationId) {
        updateEvaluationMutation.mutate({ id: editingEvaluationId, data: catalogForm });
      } else {
        createEvaluationMutation.mutate(catalogForm);
      }
    } catch {
      setFormError('Las preguntas deben ser un JSON valido con text y al menos 2 options por pregunta.');
    }
  }

  if (assessmentsLoading || catalogLoading || resultsLoading) return <PageSpinner />;

  const catalog = catalogData?.data ?? [];
  const evaluationResults = resultData?.data ?? [];
  const totalQuestions = catalog.reduce((sum, item) => sum + (item.questions?.length ?? 0), 0);

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-950">{t('admin.evaluations')}</h1>
          <p className="mt-1 text-sm text-gray-500">
            Gestiona el catalogo de evaluaciones complementarias y los resultados Big Five de usuarios.
          </p>
        </div>
        <div className="flex items-center gap-3">
          <Button variant="outline" onClick={openCreateAssessment}>+ Resultado Big Five</Button>
          <Button onClick={openCreateEvaluation}>+ Evaluacion</Button>
        </div>
      </div>

      <section className="grid gap-4 sm:grid-cols-4">
        <SummaryCard label="Evaluaciones" value={catalogData?.total ?? 0} icon={FileQuestion} color="bg-blue-50 text-blue-700" />
        <SummaryCard label="Preguntas" value={totalQuestions} icon={ListChecks} color="bg-emerald-50 text-emerald-700" />
        <SummaryCard label="Resultados Big Five" value={assessmentData?.total ?? 0} icon={ClipboardCheck} color="bg-violet-50 text-violet-700" />
        <SummaryCard label="Resultados evaluaciones" value={resultData?.total ?? 0} icon={BarChart3} color="bg-amber-50 text-amber-700" />
      </section>

      <Alert variant="info">
        Las plantillas base vienen del proyecto Community: inteligencia emocional, resiliencia, liderazgo, estres/burnout, motivacion, clima organizacional, cohesion familiar, toma de decisiones, creatividad y bienestar integral.
      </Alert>

      <Tabs defaultValue="catalog" className="space-y-4">
        <TabsList>
          <TabsTrigger value="catalog">Catalogo configurable</TabsTrigger>
          <TabsTrigger value="big-five">Resultados Big Five</TabsTrigger>
          <TabsTrigger value="results">Resultados evaluaciones</TabsTrigger>
        </TabsList>

        <TabsContent value="catalog" className="space-y-6">
          <Card className="border-0 shadow-sm ring-1 ring-slate-100">
            <div className="flex flex-wrap gap-4">
              <div className="min-w-[220px] flex-1">
                <Input placeholder={t('common.search')} value={catalogSearch} onChange={(e) => setCatalogSearch(e.target.value)} />
              </div>
              <div className="w-44">
                <Select options={[{ value: '', label: 'Todos' }, ...CLIENT_TYPE_OPTIONS]} value={catalogClientType} onChange={(e) => setCatalogClientType(e.target.value)} />
              </div>
            </div>
          </Card>

          <div className="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
            <div className="grid gap-4 sm:grid-cols-2">
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
                        {t(`client_types.${clientType}`)}
                      </span>
                    ))}
                    <span className="rounded-full bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-700">
                      {evaluation.questions?.length ?? 0} preguntas
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
                    <InfoPill label="Categoria" value={selectedEvaluation.category ?? '—'} />
                    <InfoPill label="Tiempo" value={`${selectedEvaluation.time_limit_minutes ?? 0} min`} />
                    <InfoPill label="Aprobacion" value={`${selectedEvaluation.passing_score}%`} />
                    <InfoPill label="Resultados" value={selectedEvaluation.results_count ?? 0} />
                  </div>
                  <div className="space-y-3">
                    {selectedEvaluation.questions?.map((question, index) => (
                      <div key={`${question.id ?? index}`} className="rounded-xl border border-slate-100 bg-slate-50 p-3">
                        <p className="text-sm font-medium text-slate-900">{index + 1}. {question.text}</p>
                        <p className="mt-1 text-xs text-slate-500">{question.options.length} opciones · {question.dimension ?? 'sin dimension'}</p>
                      </div>
                    ))}
                  </div>
                </div>
              ) : (
                <div className="flex min-h-80 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 text-center">
                  <Sparkles className="h-8 w-8 text-primary-500" aria-hidden="true" />
                  <p className="mt-3 font-semibold text-slate-900">Selecciona una evaluacion</p>
                  <p className="mt-1 max-w-xs text-sm text-slate-500">Edita preguntas, segmentos de cliente y parametros de aprobacion.</p>
                </div>
              )}
            </Card>
          </div>
        </TabsContent>

        <TabsContent value="big-five" className="grid gap-6 lg:grid-cols-2">
          <Card className="border-0 shadow-sm ring-1 ring-slate-100">
            <CardHeader>
              <CardTitle>Resultados Big Five</CardTitle>
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

            {assessmentData && assessmentData.last_page > 1 && (
              <div className="mt-4 flex items-center justify-between border-t pt-4">
                <Button variant="outline" size="sm" disabled={assessmentPage <= 1} onClick={() => setAssessmentPage(p => p - 1)}>
                  {t('common.previous')}
                </Button>
                <span className="text-sm text-gray-600">
                  {t('common.page')} {assessmentData.current_page} {t('common.of')} {assessmentData.last_page}
                </span>
                <Button variant="outline" size="sm" disabled={assessmentPage >= assessmentData.last_page} onClick={() => setAssessmentPage(p => p + 1)}>
                  {t('common.next')}
                </Button>
              </div>
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
                <InfoPill label="Version" value={selectedAssessment.test_version} />
                {selectedAssessment.completed_at && <InfoPill label={t('sessions.date')} value={new Date(selectedAssessment.completed_at).toLocaleDateString(locale)} />}
                {selectedAssessment.results?.factors && (
                  <div>
                    <p className="mb-2 text-sm font-medium text-gray-700">{t('personality.results')}</p>
                    <div className="space-y-2">
                      {Object.entries(selectedAssessment.results.factors).map(([key, val]) => (
                        <div key={key} className="flex items-center justify-between">
                          <span className="text-sm text-gray-600">{key}</span>
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
            <Input placeholder={t('common.search')} value={resultSearch} onChange={(e) => { setResultSearch(e.target.value); setResultPage(1); }} />
          </Card>

          <div className="grid gap-6 lg:grid-cols-[1fr_0.9fr]">
            <Card className="border-0 shadow-sm ring-1 ring-slate-100">
              <CardHeader>
                <CardTitle>Resultados de evaluaciones</CardTitle>
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

              {resultData && resultData.last_page > 1 && (
                <div className="mt-4 flex items-center justify-between border-t pt-4">
                  <Button variant="outline" size="sm" disabled={resultPage <= 1} onClick={() => setResultPage(p => p - 1)}>
                    {t('common.previous')}
                  </Button>
                  <span className="text-sm text-gray-600">
                    {t('common.page')} {resultData.current_page} {t('common.of')} {resultData.last_page}
                  </span>
                  <Button variant="outline" size="sm" disabled={resultPage >= resultData.last_page} onClick={() => setResultPage(p => p + 1)}>
                    {t('common.next')}
                  </Button>
                </div>
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
                    <InfoPill label="Puntaje" value={`${Math.round(selectedEvaluationResult.score)}%`} />
                    <InfoPill label="Estado" value={selectedEvaluationResult.passed ? 'Aprobada' : 'No aprobada'} />
                    <InfoPill label="Fecha" value={new Date(selectedEvaluationResult.completed_at).toLocaleDateString(locale)} />
                    <InfoPill label="Tiempo" value={formatSeconds(selectedEvaluationResult.time_spent_seconds)} />
                  </div>
                  <div>
                    <p className="mb-2 text-sm font-medium text-gray-700">Respuestas registradas</p>
                    <div className="space-y-2">
                      {selectedEvaluationResult.answers.map((answer, index) => (
                        <div key={`${answer.question_id}-${index}`} className="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
                          <span className="text-slate-600">Pregunta {answer.question_id}</span>
                          <span className="font-semibold text-slate-950">Opcion {answer.option_index + 1}</span>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              ) : (
                <div className="flex min-h-80 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 text-center">
                  <BarChart3 className="h-8 w-8 text-primary-500" aria-hidden="true" />
                  <p className="mt-3 font-semibold text-slate-900">Selecciona un resultado</p>
                  <p className="mt-1 max-w-xs text-sm text-slate-500">Revisa puntaje, estado y respuestas de evaluaciones desarrolladas por usuarios.</p>
                </div>
              )}
            </Card>
          </div>
        </TabsContent>
      </Tabs>

      <Dialog open={showCatalogModal} onOpenChange={(open) => { if (!open) closeCatalogModal(); else setShowCatalogModal(true); }}>
        <DialogContent aria-labelledby="admin-evaluation-catalog-dialog-title" className="max-w-3xl">
          <DialogHeader>
            <DialogTitle id="admin-evaluation-catalog-dialog-title">{editingEvaluationId ? 'Editar evaluacion' : 'Crear evaluacion'}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleCatalogSubmit} className="mt-4 space-y-4">
            {formError && <Alert variant="error">{formError}</Alert>}
            <div className="grid gap-4 sm:grid-cols-2">
              <Input label="Slug" value={catalogForm.slug} onChange={e => setCatalogForm({ ...catalogForm, slug: e.target.value })} required />
              <Input label="Titulo" value={catalogForm.title_es} onChange={e => setCatalogForm({ ...catalogForm, title_es: e.target.value })} required />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <Input label="Nombre corto" value={catalogForm.short_name_es} onChange={e => setCatalogForm({ ...catalogForm, short_name_es: e.target.value })} />
              <Select label="Categoria" options={CATEGORY_OPTIONS} value={catalogForm.category} onChange={e => setCatalogForm({ ...catalogForm, category: e.target.value })} />
            </div>
            <Textarea label="Descripcion" rows={2} value={catalogForm.description_es} onChange={e => setCatalogForm({ ...catalogForm, description_es: e.target.value })} />
            <div className="grid gap-4 sm:grid-cols-2">
              <Input label="Puntaje de aprobacion" type="number" value={catalogForm.passing_score} onChange={e => setCatalogForm({ ...catalogForm, passing_score: Number(e.target.value) })} required />
              <Input label="Tiempo limite (min)" type="number" value={catalogForm.time_limit_minutes} onChange={e => setCatalogForm({ ...catalogForm, time_limit_minutes: Number(e.target.value) })} />
            </div>
            <div>
              <span className="block text-sm font-medium text-gray-700">Tipos de cliente</span>
              <div className="mt-2 grid gap-2 sm:grid-cols-4">
                {CLIENT_TYPE_OPTIONS.map((option) => (
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
            <Textarea label="Preguntas (JSON)" rows={10} value={catalogForm.questions} onChange={e => setCatalogForm({ ...catalogForm, questions: e.target.value })} required />
            <label className="flex items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" checked={catalogForm.is_active} onChange={e => setCatalogForm({ ...catalogForm, is_active: e.target.checked })} />
              Evaluacion activa
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
            <DialogTitle id="admin-evaluation-dialog-title">{editingAssessmentId ? 'Editar resultado Big Five' : 'Crear resultado Big Five'}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleAssessmentSubmit} className="mt-4 space-y-4">
            {formError && <Alert variant="error">{formError}</Alert>}
            <Select label="Usuario" options={(usersData?.data || []).map((u) => ({
              value: u.id, label: `${u.name} (${u.email})`,
            }))} value={assessmentForm.user_id}
              onChange={e => setAssessmentForm({ ...assessmentForm, user_id: e.target.value })} required={!editingAssessmentId} />
            <Select label="Version del test" options={[
              { value: 'ipip-neo-120', label: 'IPIP-NEO-120' },
              { value: 'ipip-neo-300', label: 'IPIP-NEO-300' },
            ]} value={assessmentForm.test_version} onChange={e => setAssessmentForm({ ...assessmentForm, test_version: e.target.value })} />
            <Textarea label="Respuestas (JSON)" rows={3} value={assessmentForm.answers} onChange={e => setAssessmentForm({ ...assessmentForm, answers: e.target.value })} />
            <Textarea label="Resultados (JSON)" rows={4} value={assessmentForm.results} onChange={e => setAssessmentForm({ ...assessmentForm, results: e.target.value })} />
            <Textarea label="Puntajes brutos (JSON)" rows={3} value={assessmentForm.raw_scores} onChange={e => setAssessmentForm({ ...assessmentForm, raw_scores: e.target.value })} />
            <Input label="Completado en" type="datetime-local" value={assessmentForm.completed_at} onChange={e => setAssessmentForm({ ...assessmentForm, completed_at: e.target.value })} />
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
    title: { es: data.title_es, en: data.title_es, pt: data.title_es },
    short_name: data.short_name_es ? { es: data.short_name_es, en: data.short_name_es, pt: data.short_name_es } : undefined,
    description: data.description_es ? { es: data.description_es, en: data.description_es, pt: data.description_es } : undefined,
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

function toggleValue(values: string[], value: string): string[] {
  return values.includes(value)
    ? values.filter((item) => item !== value)
    : [...values, value];
}

function formatSeconds(value: number | null): string {
  if (!value) return '0 min';

  const minutes = Math.floor(value / 60);
  const seconds = value % 60;

  return `${minutes}m ${seconds}s`;
}
