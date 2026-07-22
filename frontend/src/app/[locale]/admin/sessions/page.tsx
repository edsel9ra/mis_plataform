'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslations } from 'next-intl';
import { api } from '@/lib/api';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PageSpinner } from '@/components/ui/spinner';

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
    mentor: { id: string; name: string; last_name: string | null };
    source: { name?: string; id?: string };
  };
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
  title: '', description: '', session_type: 'one_on_one',
  scheduled_at: '', duration_minutes: 60, status: 'scheduled' as string,
  relationship_id: '',
};

export default function AdminSessions() {
  const t = useTranslations();
  const queryClient = useQueryClient();
  const [statusFilter, setStatusFilter] = useState('');
  const [page, setPage] = useState(1);
  const [showModal, setShowModal] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState(emptyForm);

  const { data, isLoading } = useQuery<PaginatedResponse>({
    queryKey: ['admin-sessions', statusFilter, page],
    queryFn: () => api.get<PaginatedResponse>('/admin/sessions', {
      status: statusFilter || undefined, page,
    }),
  });

  const { data: relationshipsData } = useQuery<{ data: { id: string; mentor: { name: string } }[] }>({
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
      scheduled_at: session.scheduled_at.slice(0, 16), duration_minutes: session.duration_minutes,
      status: session.status, relationship_id: session.relationship_id,
    });
    setShowModal(true);
  }

  function closeModal() { setShowModal(false); setEditingId(null); setForm(emptyForm); }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (editingId) {
      updateMutation.mutate({ id: editingId, data: form });
    } else {
      createMutation.mutate(form);
    }
  }

  if (isLoading) return <PageSpinner />;

  return (
    <div>
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">{t('admin.sessions')}</h1>
        <div className="flex items-center gap-4">
          <span className="text-sm text-gray-500">{data?.total ?? 0} {t('common.total')}</span>
          <Button onClick={openCreate}>+ {t('common.create')}</Button>
        </div>
      </div>

      <Card className="mt-6">
        <CardHeader>
          <div className="w-44">
            <Select options={STATUS_OPTIONS} value={statusFilter}
              onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} />
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
                    {session.relationship?.mentor?.name} {session.relationship?.mentor?.last_name ?? ''}
                  </td>
                  <td className="px-4 py-3">
                    <Badge variant={STATUS_BADGE[session.status] || 'default'}>{session.status}</Badge>
                  </td>
                  <td className="px-4 py-3 text-gray-600">
                    {new Date(session.scheduled_at).toLocaleDateString()}
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

      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={closeModal}>
          <div className="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" onClick={e => e.stopPropagation()}>
            <h2 className="text-lg font-bold text-gray-900">{editingId ? 'Editar Sesión' : 'Crear Sesión'}</h2>
            <form onSubmit={handleSubmit} className="mt-4 space-y-4">
              <Input label="Título" value={form.title} onChange={e => setForm({ ...form, title: e.target.value })} required />
              <Input label="Descripción" value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} />
              <div className="grid grid-cols-2 gap-4">
                <Select label="Tipo" options={[
                  { value: 'one_on_one', label: 'One-on-One' },
                  { value: 'group', label: 'Grupal' },
                  { value: 'workshop', label: 'Taller' },
                  { value: 'assessment', label: 'Evaluación' },
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
              <Select label="Relación" options={(relationshipsData?.data || []).map((r: any) => ({
                value: r.id, label: r.mentor?.name || r.id,
              }))} value={form.relationship_id}
                onChange={e => setForm({ ...form, relationship_id: e.target.value })} required />
              <div className="flex justify-end gap-3 pt-2">
                <Button variant="outline" type="button" onClick={closeModal}>{t('common.cancel')}</Button>
                <Button type="submit" loading={createMutation.isPending || updateMutation.isPending}>
                  {editingId ? t('common.save') : t('common.create')}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
