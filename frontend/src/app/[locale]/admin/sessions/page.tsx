'use client';

import { useEffect, useState } from 'react';
import { useInfiniteQuery, useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useLocale, useTranslations } from 'next-intl';
import { api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { apiIsoToDatetimeLocal, datetimeLocalToApiIso } from '@/lib/datetime';
import { normalizePaginatedResponse, type PaginatedResponse, type RawPaginatedResponse } from '@/lib/pagination';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { Card, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PageSpinner } from '@/components/ui/spinner';
import { Alert } from '@/components/ui/alert';
import { PaginationControls } from '@/components/ui/pagination';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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
  const [showModal, setShowModal] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState(emptyForm);
  const debouncedSearch = useDebouncedValue(search.trim());

  useEffect(() => setPage(1), [debouncedSearch, statusFilter]);

  const { data, isLoading, isFetching, error } = useQuery<PaginatedResponse<Session>>({
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
    onSuccess: () => { setMutationError(null); queryClient.invalidateQueries({ queryKey: ['admin-sessions'] }); closeModal(); },
    onError: (error) => setMutationError(error),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<typeof emptyForm> }) => api.put(`/admin/sessions/${id}`, data),
    onSuccess: () => { setMutationError(null); queryClient.invalidateQueries({ queryKey: ['admin-sessions'] }); closeModal(); },
    onError: (error) => setMutationError(error),
  });

  const updateStatus = useMutation({
    mutationFn: ({ id, status }: { id: string; status: string }) =>
      api.put(`/admin/sessions/${id}/status`, { status }),
    onSuccess: () => { setMutationError(null); queryClient.invalidateQueries({ queryKey: ['admin-sessions'] }); },
    onError: (error) => setMutationError(error),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/admin/sessions/${id}`),
    onSuccess: () => { setMutationError(null); queryClient.invalidateQueries({ queryKey: ['admin-sessions'] }); },
    onError: (error) => setMutationError(error),
  });

  function openCreate() { setMutationError(null); setEditingId(null); setForm(emptyForm); setShowModal(true); }

  function openEdit(session: Session) {
    setMutationError(null);
    setEditingId(session.id);
    setForm({
      title: session.title, description: session.description || '', session_type: session.session_type,
      scheduled_at: apiIsoToDatetimeLocal(session.scheduled_at), duration_minutes: session.duration_minutes,
      status: session.status, relationship_id: session.relationship_id,
    });
    setShowModal(true);
  }

  function closeModal() { setShowModal(false); setEditingId(null); setForm(emptyForm); }

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
  if (error) return <Alert variant="error">{apiErrorMessage(error, (key) => t(key)) || t('common.load_error')}</Alert>;

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
          <p className="mt-1 text-sm text-gray-500">{t('admin.sessions_description')}</p>
        </div>
        <div className="flex items-center gap-4">
          <span className="text-sm text-gray-500">{data?.meta.total ?? 0} {t('common.total')}</span>
          <Button onClick={openCreate}>+ {t('common.create')}</Button>
        </div>
      </div>

      <section className="grid gap-4 sm:grid-cols-3">
        {sessionStats.map(({ label, value, icon: Icon, color }) => (
          <Card key={label} className="border-0 shadow-sm ring-1 ring-slate-100">
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
                <Select options={statusOptions} value={statusFilter}
              onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} />
            </div>
          </div>
        </CardHeader>

        {Boolean(mutationError) && (
          <Alert variant="error" className="mx-4 mb-4">
            {apiErrorMessage(mutationError, (key) => t(key)) || t('common.error')}
          </Alert>
        )}

        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-left text-gray-500">
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
                  <td className="px-4 py-3">
                    <Badge variant={STATUS_BADGE[session.status] || 'default'}>{sessionStatusLabel(session.status, t)}</Badge>
                  </td>
                  <td className="px-4 py-3 text-gray-600">
                    {new Date(session.scheduled_at).toLocaleDateString(locale)}
                  </td>
                   <td className="px-4 py-3 text-gray-600">{session.duration_minutes} {t('common.minutes_short')}</td>
                  <td className="px-4 py-3">
                    <div className="flex gap-2">
                      <Button variant="outline" size="sm" onClick={() => openEdit(session)}>{t('common.edit')}</Button>
                      {session.status === 'scheduled' && (
                        <Button variant="outline" size="sm"
                          onClick={() => updateStatus.mutate({ id: session.id, status: 'completed' })}>
                           {t('admin.complete_session')}
                        </Button>
                      )}
                      <Button variant="danger" size="sm"
                        onClick={() => { if (confirm(t('common.confirm_delete'))) deleteMutation.mutate(session.id); }}>
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

      <Dialog open={showModal} onOpenChange={(open) => { if (!open) closeModal(); else setShowModal(true); }}>
        <DialogContent aria-labelledby="admin-session-dialog-title">
          <DialogHeader>
            <DialogTitle id="admin-session-dialog-title">{editingId ? t('admin.edit_session') : t('admin.create_session')}</DialogTitle>
          </DialogHeader>
            <form onSubmit={handleSubmit} className="mt-4 space-y-4">
              <Input label={t('admin.session_title')} value={form.title} onChange={e => setForm({ ...form, title: e.target.value })} required />
              <Input label={t('admin.session_description')} value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} />
              <div className="grid grid-cols-2 gap-4">
                <Select label={t('admin.session_type')} options={[
                  { value: 'individual', label: t('session_types.individual') },
                  { value: 'family', label: t('session_types.family') },
                  { value: 'group', label: t('session_types.group') },
                  { value: 'corporate', label: t('session_types.corporate') },
                ]} value={form.session_type} onChange={e => setForm({ ...form, session_type: e.target.value })} />
                <Select label={t('admin.session_status')} options={statusOptions.slice(1)} value={form.status}
                  onChange={e => setForm({ ...form, status: e.target.value })} />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <Input label={t('admin.scheduled_at')} type="datetime-local" value={form.scheduled_at}
                  onChange={e => setForm({ ...form, scheduled_at: e.target.value })} required />
                <Input label={t('admin.duration_minutes')} type="number" value={form.duration_minutes}
                  onChange={e => setForm({ ...form, duration_minutes: Number(e.target.value) })} required />
              </div>
              <div className="space-y-2">
                <Select label={t('admin.relationship')} options={relationshipOptions.map((r) => ({
                  value: r.id, label: relationshipLabel(r, t),
                }))} value={form.relationship_id}
                  onChange={e => setForm({ ...form, relationship_id: e.target.value })} required />
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
