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

interface Assessment {
  id: string;
  user_id: string;
  test_version: string;
  completed_at: string | null;
  results: { factors?: Record<string, number> } | null;
  raw_scores: Record<string, number> | null;
  user: { id: string; name: string; last_name: string | null; email: string };
}

interface PaginatedResponse {
  data: Assessment[];
  total: number;
  current_page: number;
  last_page: number;
}

const emptyForm = {
  user_id: '', test_version: 'ipip-neo-120' as string,
  answers: '{}', results: '{}', raw_scores: '{}',
  completed_at: '',
};

export default function AdminEvaluations() {
  const t = useTranslations();
  const queryClient = useQueryClient();
  const [selected, setSelected] = useState<Assessment | null>(null);
  const [page, setPage] = useState(1);
  const [showModal, setShowModal] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState(emptyForm);

  const { data, isLoading } = useQuery<PaginatedResponse>({
    queryKey: ['admin-assessments', page],
    queryFn: () => api.get<PaginatedResponse>('/admin/assessments', { page }),
  });

  const { data: usersData } = useQuery<{ data: { id: string; name: string; email: string }[] }>({
    queryKey: ['admin-users-brief'],
    queryFn: () => api.get('/admin/users', { per_page: 200 }),
  });

  const createMutation = useMutation({
    mutationFn: (data: typeof emptyForm) => api.post('/admin/assessments', {
      ...data,
      answers: JSON.parse(data.answers || '{}'),
      results: JSON.parse(data.results || '{}'),
      raw_scores: JSON.parse(data.raw_scores || '{}'),
    }),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin-assessments'] }); closeModal(); },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<typeof emptyForm> }) =>
      api.put(`/admin/assessments/${id}`, {
        ...data,
        answers: data.answers ? JSON.parse(data.answers) : undefined,
        results: data.results ? JSON.parse(data.results) : undefined,
        raw_scores: data.raw_scores ? JSON.parse(data.raw_scores) : undefined,
      }),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin-assessments'] }); closeModal(); },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/admin/assessments/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-assessments'] }),
  });

  function openCreate() { setEditingId(null); setForm(emptyForm); setShowModal(true); }

  function openEdit(assessment: Assessment) {
    setEditingId(assessment.id);
    setForm({
      user_id: assessment.user_id, test_version: assessment.test_version,
      answers: JSON.stringify(assessment.raw_scores || {}),
      results: JSON.stringify(assessment.results || {}),
      raw_scores: JSON.stringify(assessment.raw_scores || {}),
      completed_at: assessment.completed_at || '',
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
        <h1 className="text-2xl font-bold text-gray-900">{t('admin.evaluations')}</h1>
        <div className="flex items-center gap-4">
          <span className="text-sm text-gray-500">{data?.total ?? 0} {t('common.total')}</span>
          <Button onClick={openCreate}>+ {t('common.create')}</Button>
        </div>
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>{t('admin.evaluations')}</CardTitle>
          </CardHeader>
          <div className="space-y-2">
            {data?.data?.map((assessment) => (
              <button
                key={assessment.id}
                onClick={() => setSelected(assessment)}
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
                  {assessment.completed_at ? new Date(assessment.completed_at).toLocaleDateString() : '—'}
                </p>
              </button>
            ))}
          </div>

          {data && data.last_page > 1 && (
            <div className="mt-4 flex items-center justify-between border-t pt-4">
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

        {selected && (
          <Card>
            <CardHeader>
              <div className="flex items-start justify-between">
                <div>
                  <CardTitle>{selected.user.name} {selected.user.last_name ?? ''}</CardTitle>
                  <p className="text-sm text-gray-500">{selected.user.email}</p>
                </div>
                <div className="flex gap-2">
                  <Button variant="outline" size="sm" onClick={() => openEdit(selected)}>{t('common.edit')}</Button>
                  <Button variant="danger" size="sm"
                    onClick={() => { if (confirm(t('common.confirm_delete'))) deleteMutation.mutate(selected.id); }}>
                    {t('common.delete')}
                  </Button>
                </div>
              </div>
            </CardHeader>

            <div className="space-y-4">
              <div className="flex justify-between text-sm">
                <span className="text-gray-500">{t('common.status')}</span>
                {selected.completed_at
                  ? <Badge variant="success">{t('common.completed')}</Badge>
                  : <Badge variant="warning">{t('common.pending')}</Badge>}
              </div>

              {selected.completed_at && (
                <div className="flex justify-between text-sm">
                  <span className="text-gray-500">{t('sessions.date')}</span>
                  <span className="text-gray-900">{new Date(selected.completed_at).toLocaleDateString()}</span>
                </div>
              )}

              <div className="flex justify-between text-sm">
                <span className="text-gray-500">Versión del test</span>
                <span className="text-gray-900">{selected.test_version}</span>
              </div>

              {selected.results?.factors && (
                <div>
                  <p className="mb-2 text-sm font-medium text-gray-700">{t('personality.results')}</p>
                  <div className="space-y-2">
                    {Object.entries(selected.results.factors).map(([key, val]) => (
                      <div key={key} className="flex items-center justify-between">
                        <span className="text-sm text-gray-600">{key}</span>
                        <div className="flex items-center gap-2">
                          <div className="h-2 w-32 rounded-full bg-gray-100">
                            <div className="h-2 rounded-full bg-primary-500"
                              style={{ width: `${Math.min(100, (val as number))}%` }} />
                          </div>
                          <span className="text-sm font-medium text-gray-900">{Math.round(val as number)}</span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </Card>
        )}
      </div>

      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={closeModal}>
          <div className="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" onClick={e => e.stopPropagation()}>
            <h2 className="text-lg font-bold text-gray-900">{editingId ? 'Editar Evaluación' : 'Crear Evaluación'}</h2>
            <form onSubmit={handleSubmit} className="mt-4 space-y-4">
              <Select label="Usuario" options={(usersData?.data || []).map((u: any) => ({
                value: u.id, label: `${u.name} (${u.email})`,
              }))} value={form.user_id}
                onChange={e => setForm({ ...form, user_id: e.target.value })} required={!editingId} />
              <Select label="Versión del test" options={[
                { value: 'ipip-neo-120', label: 'IPIP-NEO-120' },
                { value: 'ipip-neo-300', label: 'IPIP-NEO-300' },
              ]} value={form.test_version} onChange={e => setForm({ ...form, test_version: e.target.value })} />
              <Input label="Resultados (JSON)" value={form.results}
                onChange={e => setForm({ ...form, results: e.target.value })} />
              <Input label="Puntajes brutos (JSON)" value={form.raw_scores}
                onChange={e => setForm({ ...form, raw_scores: e.target.value })} />
              <Input label="Completado en" type="datetime-local" value={form.completed_at}
                onChange={e => setForm({ ...form, completed_at: e.target.value })} />
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
