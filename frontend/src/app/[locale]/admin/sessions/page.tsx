'use client';

import { useEffect, useState } from 'react';
import { keepPreviousData, useInfiniteQuery, useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useLocale, useTranslations } from 'next-intl';
import { api, ApiError } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { fieldErrorPresentation, formErrorAnnouncement, type FieldErrors } from '@/lib/scheduling';
import { apiIsoToDatetimeLocal, datetimeLocalToApiIso } from '@/lib/datetime';
import { normalizePaginatedResponse, type PaginatedResponse, type RawPaginatedResponse } from '@/lib/pagination';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { Card, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PageSpinner } from '@/components/ui/spinner';
import { Alert } from '@/components/ui/alert';
import { PaginationControls } from '@/components/ui/pagination';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormErrorSummary } from '@/components/ui/form-error-summary';
import { CalendarClock, CheckCircle2, Clock } from 'lucide-react';

interface Session {
  id: string;
  title: string;
  description: string | null;
  status: string;
  scheduled_at: string;
  duration_minutes: number;
  session_type: string;
  relationship_id: string;
  relationship: {
    mentor: { id: string; name?: string; last_name?: string | null; full_name?: string };
    source: { name?: string; id?: string };
  };
}

interface RelationshipOption {
  id: string;
  type: string;
  mentor?: { name?: string; last_name?: string | null; full_name?: string };
  source?: { name?: string; full_name?: string; family_name?: string; legal_name?: string; id?: string };
}

const STATUS_BADGE: Record<string, 'primary' | 'success' | 'warning' | 'danger' | 'default'> = {
  scheduled: 'primary', in_progress: 'warning', completed: 'success', canceled: 'danger',
};

/** Laravel field name → stable DOM id (RF-010.3: errors anchored per field). */
const SESSION_FIELD_IDS: Record<string, string> = {
  title: 'admin-session-title',
  description: 'admin-session-description',
  session_type: 'admin-session-type',
  status: 'admin-session-status',
  scheduled_at: 'admin-session-scheduled-at',
  duration_minutes: 'admin-session-duration',
  relationship_id: 'admin-session-relationship',
};

const SESSION_FIELD_ORDER = [
  'admin-session-title',
  'admin-session-description',
  'admin-session-type',
  'admin-session-status',
  'admin-session-scheduled-at',
  'admin-session-duration',
  'admin-session-relationship',
];

const CANCEL_REASON_FIELD_ID = 'admin-cancel-reason';

const emptyForm = {
  title: '', description: '', session_type: 'individual',
  scheduled_at: '', duration_minutes: 60, status: 'scheduled' as string,
  relationship_id: '',
};

export default function AdminSessions() {
  const t = useTranslations();
  const locale = useLocale();
  const queryClient = useQueryClient();
  const statusOptions = getStatusOptions(t);
  const [statusFilter, setStatusFilter] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [mutationError, setMutationError] = useState<unknown>(null);
  const [modalError, setModalError] = useState<unknown>(null);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>(null);
  const [showModal, setShowModal] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Session | null>(null);
  const [cancelTarget, setCancelTarget] = useState<Session | null>(null);
  const [cancelReason, setCancelReason] = useState('');
  const [cancelFieldErrors, setCancelFieldErrors] = useState<FieldErrors>(null);
  const [cancelError, setCancelError] = useState<unknown>(null);
  const [statusAnnouncement, setStatusAnnouncement] = useState('');
  const [form, setForm] = useState(emptyForm);
  const debouncedSearch = useDebouncedValue(search.trim());

  useEffect(() => setPage(1), [debouncedSearch, statusFilter]);

  const { data, isLoading, isFetching, error, refetch } = useQuery<PaginatedResponse<Session>>({
    queryKey: ['admin-sessions', statusFilter, debouncedSearch, page],
    queryFn: async ({ signal }) => {
      const response = await api.get<RawPaginatedResponse<Session>>('/admin/sessions', {
        status: statusFilter || undefined,
        search: debouncedSearch || undefined,
        page,
        per_page: 20,
      }, { signal });

      return normalizePaginatedResponse(response, { page, perPage: 20 });
    },
    placeholderData: keepPreviousData,
  });

  const relationshipsQuery = useInfiniteQuery({
    queryKey: ['admin-relationships-list'],
    initialPageParam: 1,
    queryFn: async ({ signal, pageParam }) => {
      const response = await api.get<RawPaginatedResponse<RelationshipOption>>('/relationships', {
        page: pageParam,
        per_page: 20,
      }, { signal });

      return normalizePaginatedResponse(response, { page: pageParam, perPage: 20 });
    },
    getNextPageParam: (lastPage) => lastPage.meta.current_page < lastPage.meta.last_page
      ? lastPage.meta.current_page + 1
      : undefined,
  });
  const relationshipOptions = relationshipsQuery.data?.pages.flatMap((pageData) => pageData.data) ?? [];

  const createMutation = useMutation({
    mutationFn: (data: typeof emptyForm) => api.post('/admin/sessions', data),
    onSuccess: () => { clearModalErrors(); queryClient.invalidateQueries({ queryKey: ['admin-sessions'] }); closeModal(); },
    onError: handleModalError,
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<typeof emptyForm> }) => api.put(`/admin/sessions/${id}`, data),
    onSuccess: () => { clearModalErrors(); queryClient.invalidateQueries({ queryKey: ['admin-sessions'] }); closeModal(); },
    onError: handleModalError,
  });

  const cancelSession = useMutation({
    mutationFn: ({ id, reason }: { id: string; reason: string }) =>
      api.put(`/admin/sessions/${id}/status`, { status: 'canceled', reason }),
    onSuccess: (_data, { id }) => {
      setMutationError(null);
      setStatusAnnouncement(t('admin.session_canceled'));
      setCancelTarget(null);
      queryClient.invalidateQueries({ queryKey: ['admin-sessions'] });
      requestAnimationFrame(() => document.getElementById(`session-status-${id}`)?.focus());
    },
    onError: (error) => {
      if (error instanceof ApiError && error.status === 422 && error.errors) {
        const mapped: Record<string, string[]> = {};
        const general: string[] = [];
        for (const [field, messages] of Object.entries(error.errors)) {
          if (field === 'reason') mapped[CANCEL_REASON_FIELD_ID] = messages;
          else general.push(...messages);
        }
        setCancelFieldErrors(mapped);
        setCancelError(general.length > 0 ? general.join(' ') : null);
        const [first] = formErrorAnnouncement(mapped, [CANCEL_REASON_FIELD_ID]);
        if (first) document.getElementById(first.fieldId)?.focus();
        return;
      }
      setCancelError(error);
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/admin/sessions/${id}`),
    onSuccess: () => { setMutationError(null); queryClient.invalidateQueries({ queryKey: ['admin-sessions'] }); },
    onError: (error) => setMutationError(error),
  });

  function clearModalErrors() {
    setModalError(null);
    setFieldErrors(null);
  }

  function clearFieldError(fieldId: string) {
    setFieldErrors((current) => {
      if (!current?.[fieldId]) return current;
      const next = { ...current };
      delete next[fieldId];
      return Object.keys(next).length > 0 ? next : null;
    });
  }

  function handleModalError(error: unknown) {
    if (error instanceof ApiError && error.status === 422 && error.errors) {
      // Server field names → stable DOM ids so each message lands next to
      // its field and in the announced summary (RF-010.3).
      const mapped: Record<string, string[]> = {};
      for (const [field, messages] of Object.entries(error.errors)) {
        mapped[SESSION_FIELD_IDS[field] ?? field] = messages;
      }
      setModalError(null);
      setFieldErrors(mapped);
      const [first] = formErrorAnnouncement(mapped, SESSION_FIELD_ORDER);
      if (first) document.getElementById(first.fieldId)?.focus();
      return;
    }
    setModalError(error);
  }

  function openCancel(session: Session) {
    setCancelTarget(session);
    setCancelReason('');
    setCancelFieldErrors(null);
    setCancelError(null);
  }

  function closeCancel() {
    setCancelTarget(null);
    setCancelReason('');
    setCancelFieldErrors(null);
    setCancelError(null);
  }

  function handleCancelSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!cancelTarget) return;
    if (!cancelReason.trim()) {
      setCancelFieldErrors({ [CANCEL_REASON_FIELD_ID]: [t('admin.cancel_reason_required')] });
      document.getElementById(CANCEL_REASON_FIELD_ID)?.focus();
      return;
    }
    cancelSession.mutate({ id: cancelTarget.id, reason: cancelReason.trim() });
  }

  function openCreate() { setMutationError(null); clearModalErrors(); setEditingId(null); setForm(emptyForm); setShowModal(true); }

  function openEdit(session: Session) {
    setMutationError(null);
    clearModalErrors();
    setEditingId(session.id);
    setForm({
      title: session.title, description: session.description || '', session_type: session.session_type,
      scheduled_at: apiIsoToDatetimeLocal(session.scheduled_at), duration_minutes: session.duration_minutes,
      status: session.status, relationship_id: session.relationship_id,
    });
    setShowModal(true);
  }

  function closeModal() { setShowModal(false); setEditingId(null); setForm(emptyForm); clearModalErrors(); }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    const payload = { ...form, scheduled_at: datetimeLocalToApiIso(form.scheduled_at) };
    if (editingId) {
      updateMutation.mutate({ id: editingId, data: payload });
    } else {
      createMutation.mutate(payload);
    }
  }

  if (isLoading) return <PageSpinner />;
  if (error) return (
    <Alert variant="error">
      {apiErrorMessage(error, (key) => t(key)) || t('common.load_error')}
      <Button type="button" variant="outline" size="sm" className="ml-3" onClick={() => refetch()}>
        {t('common.retry')}
      </Button>
    </Alert>
  );

  const sessionStats = [
    { label: t('session_statuses.scheduled'), value: data?.data?.filter((item) => item.status === 'scheduled').length ?? 0, icon: CalendarClock, color: 'bg-blue-50 text-blue-700' },
    { label: t('session_statuses.in_progress'), value: data?.data?.filter((item) => item.status === 'in_progress').length ?? 0, icon: Clock, color: 'bg-amber-50 text-amber-700' },
    { label: t('session_statuses.completed'), value: data?.data?.filter((item) => item.status === 'completed').length ?? 0, icon: CheckCircle2, color: 'bg-emerald-50 text-emerald-700' },
  ];

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-950">{t('admin.sessions')}</h1>
          <p className="mt-1 text-sm text-gray-600">{t('admin.sessions_description')}</p>
        </div>
        <div className="flex items-center gap-4">
          <span className="text-sm text-gray-600">{data?.meta.total ?? 0} {t('common.total')}</span>
          <Button onClick={openCreate}>+ {t('common.create')}</Button>
        </div>
      </div>

      <section className="grid gap-4 sm:grid-cols-3">
        {sessionStats.map(({ label, value, icon: Icon, color }) => (
          <Card key={label} className="border-0 shadow-sm ring-1 ring-slate-100">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">{label}</p>
                <p className="mt-2 text-2xl font-bold text-gray-950">{value}</p>
              </div>
              <span className={`rounded-2xl p-3 ${color}`}>
                <Icon className="h-5 w-5" aria-hidden="true" />
              </span>
            </div>
          </Card>
        ))}
      </section>

      <Card className="border-0 shadow-sm ring-1 ring-slate-100">
        <CardHeader>
          <div className="flex flex-wrap gap-4">
            <div className="min-w-[220px] flex-1">
              <Input placeholder={t('admin.search_sessions')} value={search}
                onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
            </div>
            <div className="w-44">
                <Select aria-label={t('common.status')} options={statusOptions} value={statusFilter}
              onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} />
            </div>
          </div>
        </CardHeader>

        {Boolean(mutationError) && (
          <Alert variant="error" className="mx-4 mb-4">
            {apiErrorMessage(mutationError, (key) => t(key)) || t('common.error')}
          </Alert>
        )}

        <div className="overflow-x-auto focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" tabIndex={0} role="region" aria-label={t('admin.sessions')}>
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-left text-gray-600">
                <th className="px-4 py-3 font-medium">{t('sessions.title')}</th>
                <th className="px-4 py-3 font-medium">{t('admin.mentor')}</th>
                <th className="px-4 py-3 font-medium">{t('common.status')}</th>
                <th className="px-4 py-3 font-medium">{t('sessions.date')}</th>
                <th className="px-4 py-3 font-medium">{t('sessions.duration')}</th>
                <th className="px-4 py-3 font-medium">{t('common.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {data?.data?.map((session) => (
                <tr key={session.id} className="border-b hover:bg-gray-50">
                  <td className="px-4 py-3 font-medium text-gray-900">{session.title}</td>
                  <td className="px-4 py-3 text-gray-600">
                    {mentorName(session.relationship?.mentor, t)}
                  </td>
                  <td className="px-4 py-3" id={`session-status-${session.id}`} tabIndex={-1}>
                    <Badge variant={STATUS_BADGE[session.status] || 'default'}>{sessionStatusLabel(session.status, t)}</Badge>
                  </td>
                  <td className="px-4 py-3 text-gray-600">
                    {new Date(session.scheduled_at).toLocaleDateString(locale)}
                  </td>
                   <td className="px-4 py-3 text-gray-600">{session.duration_minutes} {t('common.minutes_short')}</td>
                  <td className="px-4 py-3">
                    <div className="flex gap-2">
                      <Button variant="outline" size="sm" aria-label={`${t('common.edit')} ${session.title}`}
                        onClick={() => openEdit(session)}>
                        {t('common.edit')}
                      </Button>
                      {session.status === 'scheduled' && (
                        <Button variant="danger" size="sm" aria-label={`${t('admin.cancel_session')} ${session.title}`}
                          onClick={() => openCancel(session)}>
                           {t('admin.cancel_session')}
                        </Button>
                      )}
                      <Button variant="danger" size="sm" aria-label={`${t('common.delete')} ${session.title}`}
                        onClick={() => setDeleteTarget(session)}>
                        {t('common.delete')}
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {data && (
          <PaginationControls
            meta={data.meta}
            page={page}
            onPageChange={setPage}
            isLoading={isFetching}
            ariaLabel={t('common.pagination')}
            previousLabel={t('common.previous')}
            nextLabel={t('common.next')}
            pageLabel={t('common.page')}
            ofLabel={t('common.of')}
          />
        )}
      </Card>

      <div role="status" aria-live="polite" className="sr-only">{statusAnnouncement}</div>

      <Dialog open={deleteTarget !== null} onOpenChange={(open) => { if (!open) setDeleteTarget(null); }}>
        <DialogContent aria-labelledby="admin-session-delete-title" aria-describedby="admin-session-delete-desc">
          <DialogHeader>
            <DialogTitle id="admin-session-delete-title">{t('admin.delete_session')}</DialogTitle>
          </DialogHeader>
          <p id="admin-session-delete-desc" className="text-sm text-gray-600">
            {t('admin.delete_session_confirm', { title: deleteTarget?.title ?? '' })}
          </p>
          <DialogFooter>
            <Button variant="outline" type="button" data-autofocus onClick={() => setDeleteTarget(null)}>
              {t('common.cancel')}
            </Button>
            <Button variant="danger" type="button" loading={deleteMutation.isPending}
              onClick={() => { if (deleteTarget) deleteMutation.mutate(deleteTarget.id); setDeleteTarget(null); }}>
              {t('common.delete')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={cancelTarget !== null} onOpenChange={(open) => { if (!open) closeCancel(); }}>
        <DialogContent aria-labelledby="admin-session-cancel-title" aria-describedby="admin-session-cancel-desc">
          <DialogHeader>
            <DialogTitle id="admin-session-cancel-title">{t('admin.cancel_session')}</DialogTitle>
          </DialogHeader>
          <p id="admin-session-cancel-desc" className="text-sm text-gray-600">
            {t('admin.cancel_session_desc', { title: cancelTarget?.title ?? '' })}
          </p>
          <form onSubmit={handleCancelSubmit} className="mt-4 space-y-4" noValidate>
            <FormErrorSummary
              title={t('common.validation_error')}
              errors={formErrorAnnouncement(cancelFieldErrors, [CANCEL_REASON_FIELD_ID])}
            />
            {Boolean(cancelError) && (
              <Alert variant="error">
                {typeof cancelError === 'string'
                  ? cancelError
                  : apiErrorMessage(cancelError, (key) => t(key)) || t('common.error')}
              </Alert>
            )}
            <Textarea
              id={CANCEL_REASON_FIELD_ID}
              label={t('admin.cancel_reason')}
              value={cancelReason}
              onChange={(e) => {
                setCancelReason(e.target.value);
                setCancelFieldErrors((current) => {
                  if (!current?.[CANCEL_REASON_FIELD_ID]) return current;
                  const next = { ...current };
                  delete next[CANCEL_REASON_FIELD_ID];
                  return Object.keys(next).length > 0 ? next : null;
                });
              }}
              error={fieldErrorPresentation(cancelFieldErrors, CANCEL_REASON_FIELD_ID).messages.join(' ') || undefined}
              announceError={false}
              required
              rows={3}
              data-autofocus
            />
            <DialogFooter>
              <Button variant="outline" type="button" onClick={closeCancel}>{t('common.back')}</Button>
              <Button variant="danger" type="submit" loading={cancelSession.isPending}>
                {t('admin.confirm_cancel_session')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={showModal} onOpenChange={(open) => { if (!open) closeModal(); else setShowModal(true); }}>
        <DialogContent aria-labelledby="admin-session-dialog-title">
          <DialogHeader>
            <DialogTitle id="admin-session-dialog-title">{editingId ? t('admin.edit_session') : t('admin.create_session')}</DialogTitle>
          </DialogHeader>
            <form onSubmit={handleSubmit} className="mt-4 space-y-4" noValidate>
              <FormErrorSummary
                title={t('common.validation_error')}
                errors={formErrorAnnouncement(fieldErrors, SESSION_FIELD_ORDER)}
              />
              {Boolean(modalError) && (
                <Alert variant="error">{apiErrorMessage(modalError, (key) => t(key)) || t('common.error')}</Alert>
              )}
              <Input id={SESSION_FIELD_IDS.title} label={t('admin.session_title')} value={form.title}
                onChange={e => { setForm({ ...form, title: e.target.value }); clearFieldError(SESSION_FIELD_IDS.title); }}
                error={fieldErrorPresentation(fieldErrors, SESSION_FIELD_IDS.title).messages.join(' ') || undefined}
                announceError={false} required />
              <Input id={SESSION_FIELD_IDS.description} label={t('admin.session_description')} value={form.description}
                onChange={e => { setForm({ ...form, description: e.target.value }); clearFieldError(SESSION_FIELD_IDS.description); }}
                error={fieldErrorPresentation(fieldErrors, SESSION_FIELD_IDS.description).messages.join(' ') || undefined}
                announceError={false} />
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Select id={SESSION_FIELD_IDS.session_type} label={t('admin.session_type')} options={[
                  { value: 'individual', label: t('session_types.individual') },
                  { value: 'family', label: t('session_types.family') },
                  { value: 'group', label: t('session_types.group') },
                  { value: 'corporate', label: t('session_types.corporate') },
                ]} value={form.session_type}
                  onChange={e => { setForm({ ...form, session_type: e.target.value }); clearFieldError(SESSION_FIELD_IDS.session_type); }}
                  error={fieldErrorPresentation(fieldErrors, SESSION_FIELD_IDS.session_type).messages.join(' ') || undefined}
                  announceError={false} />
                <Select id={SESSION_FIELD_IDS.status} label={t('admin.session_status')} options={statusOptions.slice(1)} value={form.status}
                  onChange={e => { setForm({ ...form, status: e.target.value }); clearFieldError(SESSION_FIELD_IDS.status); }}
                  error={fieldErrorPresentation(fieldErrors, SESSION_FIELD_IDS.status).messages.join(' ') || undefined}
                  announceError={false} />
              </div>
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Input id={SESSION_FIELD_IDS.scheduled_at} label={t('admin.scheduled_at')} type="datetime-local" value={form.scheduled_at}
                  onChange={e => { setForm({ ...form, scheduled_at: e.target.value }); clearFieldError(SESSION_FIELD_IDS.scheduled_at); }}
                  error={fieldErrorPresentation(fieldErrors, SESSION_FIELD_IDS.scheduled_at).messages.join(' ') || undefined}
                  announceError={false} required />
                <Input id={SESSION_FIELD_IDS.duration_minutes} label={t('admin.duration_minutes')} type="number" value={form.duration_minutes}
                  onChange={e => { setForm({ ...form, duration_minutes: Number(e.target.value) }); clearFieldError(SESSION_FIELD_IDS.duration_minutes); }}
                  error={fieldErrorPresentation(fieldErrors, SESSION_FIELD_IDS.duration_minutes).messages.join(' ') || undefined}
                  announceError={false} required />
              </div>
              <div className="space-y-2">
                <Select id={SESSION_FIELD_IDS.relationship_id} label={t('admin.relationship')} options={relationshipOptions.map((r) => ({
                  value: r.id, label: relationshipLabel(r, t),
                }))} value={form.relationship_id}
                  onChange={e => { setForm({ ...form, relationship_id: e.target.value }); clearFieldError(SESSION_FIELD_IDS.relationship_id); }}
                  error={fieldErrorPresentation(fieldErrors, SESSION_FIELD_IDS.relationship_id).messages.join(' ') || undefined}
                  announceError={false} required />
                {relationshipsQuery.error && (
                  <Alert variant="error">{apiErrorMessage(relationshipsQuery.error, (key) => t(key)) || t('common.load_error')}</Alert>
                )}
                {relationshipsQuery.hasNextPage && (
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    loading={relationshipsQuery.isFetchingNextPage}
                    onClick={() => relationshipsQuery.fetchNextPage()}
                  >
                    {t('common.load_more')}
                  </Button>
                )}
              </div>
              <DialogFooter>
                <Button variant="outline" type="button" onClick={closeModal}>{t('common.cancel')}</Button>
                <Button type="submit" loading={createMutation.isPending || updateMutation.isPending}>
                  {editingId ? t('common.save') : t('common.create')}
                </Button>
              </DialogFooter>
            </form>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function getStatusOptions(t: ReturnType<typeof useTranslations>) {
  return [
    { value: '', label: t('common.all') },
    { value: 'scheduled', label: t('session_statuses.scheduled') },
    { value: 'in_progress', label: t('session_statuses.in_progress') },
    { value: 'completed', label: t('session_statuses.completed') },
    { value: 'canceled', label: t('session_statuses.canceled') },
  ];
}

function sessionStatusLabel(status: string, t: ReturnType<typeof useTranslations>): string {
  const labels: Record<string, string> = {
    scheduled: 'session_statuses.scheduled',
    in_progress: 'session_statuses.in_progress',
    completed: 'session_statuses.completed',
    canceled: 'session_statuses.canceled',
    cancelled: 'session_statuses.cancelled',
  };

  return labels[status] ? t(labels[status]) : t('common.not_applicable');
}

function mentorName(mentor: RelationshipOption['mentor'], t: ReturnType<typeof useTranslations>): string {
  return mentor?.full_name || [mentor?.name, mentor?.last_name].filter(Boolean).join(' ') || t('admin.mentor');
}

function relationshipLabel(relationship: RelationshipOption, t: ReturnType<typeof useTranslations>): string {
  const source = relationship.source?.name
    || relationship.source?.full_name
    || relationship.source?.family_name
    || relationship.source?.legal_name
    || relationship.source?.id
     || clientTypeLabel(relationship.type, t);

  return `${mentorName(relationship.mentor, t)} · ${source}`;
}

function clientTypeLabel(type: string, t: ReturnType<typeof useTranslations>): string {
  const labels: Record<string, string> = {
    personal: 'client_types.personal',
    familiar: 'client_types.familiar',
    grupal: 'client_types.grupal',
    empresa: 'client_types.empresa',
  };

  return labels[type] ? t(labels[type]) : t('common.not_applicable');
}
