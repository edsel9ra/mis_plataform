'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useLocale, useTranslations } from 'next-intl';
import { api } from '@/lib/api';
import { apiIsoToDatetimeLocal, datetimeLocalToApiIso } from '@/lib/datetime';
import { Card, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PageSpinner } from '@/components/ui/spinner';
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

interface PaginatedResponse {
  data: Session[];
  total: number;
  current_page: number;
  last_page: number;
}

const STATUS_BADGE: Record<string, 'primary' | 'success' | 'warning' | 'danger' | 'default'> = {
  scheduled: 'primary', in_progress: 'warning', completed: 'success', canceled: 'danger',
};

const STATUS_OPTIONS = [
  { value: '', label: 'Todos' },
  { value: 'scheduled', label: 'Programadas' },
  { value: 'in_progress', label: 'En Progreso' },
  { value: 'completed', label: 'Completadas' },
  { value: 'canceled', label: 'Canceladas' },
];

const emptyForm = {
  title: '', description: '', session_type: 'individual',
  scheduled_at: '', duration_minutes: 60, status: 'scheduled' as string,
  relationship_id: '',
};

export default function AdminSessions() {
  const t = useTranslations();
  const locale = useLocale();
  const queryClient = useQueryClient();
  const [statusFilter, setStatusFilter] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [showModal, setShowModal] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState(emptyForm);

  const { data, isLoading } = useQuery<PaginatedResponse>({
    queryKey: ['admin-sessions', statusFilter, search, page],
    queryFn: () => api.get<PaginatedResponse>('/admin/sessions', {
      status: statusFilter || undefined, search: search || undefined, page,
    }),
  });

  const { data: relationshipsData } = useQuery<{ data: RelationshipOption[] }>({
    queryKey: ['admin-relationships-list'],
    queryFn: () => api.get('/relationships'),
  });

  const createMutation = useMutation({
    mutationFn: (data: typeof emptyForm) => api.post('/admin/sessions', data),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin-sessions'] }); closeModal(); },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<typeof emptyForm> }) => api.put(`/admin/sessions/${id}`, data),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin-sessions'] }); closeModal(); },
  });

  const updateStatus = useMutation({
    mutationFn: ({ id, status }: { id: string; status: string }) =>
      api.put(`/admin/sessions/${id}/status`, { status }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-sessions'] }),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/admin/sessions/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-sessions'] }),
  });

  function openCreate() { setEditingId(null); setForm(emptyForm); setShowModal(true); }

  function openEdit(session: Session) {
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

  const sessionStats = [
    { label: 'Programadas', value: data?.data?.filter((item) => item.status === 'scheduled').length ?? 0, icon: CalendarClock, color: 'bg-blue-50 text-blue-700' },
    { label: 'En progreso', value: data?.data?.filter((item) => item.status === 'in_progress').length ?? 0, icon: Clock, color: 'bg-amber-50 text-amber-700' },
    { label: 'Completadas', value: data?.data?.filter((item) => item.status === 'completed').length ?? 0, icon: CheckCircle2, color: 'bg-emerald-50 text-emerald-700' },
  ];

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-950">{t('admin.sessions')}</h1>
          <p className="mt-1 text-sm text-gray-500">Programa, completa y audita sesiones creadas desde relaciones activas.</p>
        </div>
        <div className="flex items-center gap-4">
          <span className="text-sm text-gray-500">{data?.total ?? 0} {t('common.total')}</span>
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
              <Input placeholder="Buscar sesion" value={search}
                onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
            </div>
            <div className="w-44">
            <Select options={STATUS_OPTIONS} value={statusFilter}
              onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} />
            </div>
          </div>
        </CardHeader>

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
                    {mentorName(session.relationship?.mentor)}
                  </td>
                  <td className="px-4 py-3">
                    <Badge variant={STATUS_BADGE[session.status] || 'default'}>{session.status}</Badge>
                  </td>
                  <td className="px-4 py-3 text-gray-600">
                    {new Date(session.scheduled_at).toLocaleDateString(locale)}
                  </td>
                  <td className="px-4 py-3 text-gray-600">{session.duration_minutes}m</td>
                  <td className="px-4 py-3">
                    <div className="flex gap-2">
                      <Button variant="outline" size="sm" onClick={() => openEdit(session)}>{t('common.edit')}</Button>
                      {session.status === 'scheduled' && (
                        <Button variant="outline" size="sm"
                          onClick={() => updateStatus.mutate({ id: session.id, status: 'completed' })}>
                          Completar
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

        {data && data.last_page > 1 && (
          <div className="flex items-center justify-between border-t px-4 py-3">
            <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(p => p - 1)}>
              {t('common.previous')}
            </Button>
            <span className="text-sm text-gray-600">
              {t('common.page')} {data.current_page} {t('common.of')} {data.last_page}
            </span>
            <Button variant="outline" size="sm" disabled={page >= data.last_page} onClick={() => setPage(p => p + 1)}>
              {t('common.next')}
            </Button>
          </div>
        )}
      </Card>

      <Dialog open={showModal} onOpenChange={(open) => { if (!open) closeModal(); else setShowModal(true); }}>
        <DialogContent aria-labelledby="admin-session-dialog-title">
          <DialogHeader>
            <DialogTitle id="admin-session-dialog-title">{editingId ? 'Editar Sesión' : 'Crear Sesión'}</DialogTitle>
          </DialogHeader>
            <form onSubmit={handleSubmit} className="mt-4 space-y-4">
              <Input label="Título" value={form.title} onChange={e => setForm({ ...form, title: e.target.value })} required />
              <Input label="Descripción" value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} />
              <div className="grid grid-cols-2 gap-4">
                <Select label="Tipo" options={[
                  { value: 'individual', label: 'Individual' },
                  { value: 'family', label: 'Familiar' },
                  { value: 'group', label: 'Grupal' },
                  { value: 'corporate', label: 'Corporativa' },
                ]} value={form.session_type} onChange={e => setForm({ ...form, session_type: e.target.value })} />
                <Select label="Estado" options={STATUS_OPTIONS.slice(1)} value={form.status}
                  onChange={e => setForm({ ...form, status: e.target.value })} />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <Input label="Fecha y hora" type="datetime-local" value={form.scheduled_at}
                  onChange={e => setForm({ ...form, scheduled_at: e.target.value })} required />
                <Input label="Duración (min)" type="number" value={form.duration_minutes}
                  onChange={e => setForm({ ...form, duration_minutes: Number(e.target.value) })} required />
              </div>
              <Select label="Relación" options={(relationshipsData?.data || []).map((r) => ({
                value: r.id, label: relationshipLabel(r),
              }))} value={form.relationship_id}
                onChange={e => setForm({ ...form, relationship_id: e.target.value })} required />
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

function mentorName(mentor?: RelationshipOption['mentor']): string {
  return mentor?.full_name || [mentor?.name, mentor?.last_name].filter(Boolean).join(' ') || 'Mentor';
}

function relationshipLabel(relationship: RelationshipOption): string {
  const source = relationship.source?.name
    || relationship.source?.full_name
    || relationship.source?.family_name
    || relationship.source?.legal_name
    || relationship.source?.id
    || relationship.type;

  return `${mentorName(relationship.mentor)} · ${source}`;
}
