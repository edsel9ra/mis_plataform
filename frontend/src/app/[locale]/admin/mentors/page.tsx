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

interface Mentor {
  id: string;
  name: string;
  last_name: string | null;
  email: string;
  client_type: string;
  personality_assessment: { completed_at: string | null; results?: { factors?: Record<string, number> } } | null;
  active_mentees_count: number;
  mentor_relationships: { id: string; status: string }[];
}

interface PaginatedResponse {
  data: Mentor[];
  total: number;
  current_page: number;
  last_page: number;
}

const TYPE_OPTIONS = [
  { value: '', label: 'Todos' },
  { value: 'personal', label: 'Personal' },
  { value: 'familiar', label: 'Familiar' },
  { value: 'grupal', label: 'Grupal' },
  { value: 'empresa', label: 'Empresa' },
];

const emptyForm = {
  name: '', last_name: '', email: '', password: '',
  sex: 'N', birth_date: '', client_type: 'personal', locale: 'es',
};

export default function AdminMentors() {
  const t = useTranslations();
  const queryClient = useQueryClient();
  const [showModal, setShowModal] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState(emptyForm);

  const { data, isLoading } = useQuery<PaginatedResponse>({
    queryKey: ['admin-mentors'],
    queryFn: () => api.get<PaginatedResponse>('/admin/mentors'),
  });

  const createMutation = useMutation({
    mutationFn: (data: typeof emptyForm) => api.post('/admin/mentors', data),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin-mentors'] }); closeModal(); },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<typeof emptyForm> }) => api.put(`/admin/mentors/${id}`, data),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin-mentors'] }); closeModal(); },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/admin/mentors/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-mentors'] }),
  });

  function openCreate() {
    setEditingId(null);
    setForm(emptyForm);
    setShowModal(true);
  }

  function openEdit(mentor: Mentor) {
    setEditingId(mentor.id);
    setForm({
      name: mentor.name, last_name: mentor.last_name || '', email: mentor.email, password: '',
      sex: 'N', birth_date: '', client_type: mentor.client_type, locale: 'es',
    });
    setShowModal(true);
  }

  function closeModal() { setShowModal(false); setEditingId(null); setForm(emptyForm); }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (editingId) {
      updateMutation.mutate({ id: editingId, data: form.password ? form : { ...form, password: undefined } });
    } else {
      createMutation.mutate(form);
    }
  }

  if (isLoading) return <PageSpinner />;

  return (
    <div>
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">{t('admin.mentors')}</h1>
        <div className="flex items-center gap-4">
          <span className="text-sm text-gray-500">{data?.total ?? 0} {t('common.total')}</span>
          <Button onClick={openCreate}>+ {t('common.create')}</Button>
        </div>
      </div>

      <div className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {data?.data?.map((mentor) => (
          <Card key={mentor.id}>
            <CardHeader>
              <div className="flex items-start justify-between">
                <div>
                  <CardTitle className="text-base">{mentor.name} {mentor.last_name}</CardTitle>
                  <p className="mt-1 text-sm text-gray-500">{mentor.email}</p>
                  <p className="text-xs text-gray-400">{t(`client_types.${mentor.client_type}`)}</p>
                </div>
                <Badge variant="success">{mentor.active_mentees_count} activos</Badge>
              </div>
            </CardHeader>

            <div className="space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-gray-500">{t('sessions.title')}</span>
                <span className="font-medium text-gray-900">
                  {mentor.mentor_relationships?.filter(r => r.status === 'active').length ?? 0} activas
                </span>
              </div>

              {mentor.personality_assessment?.results?.factors && (
                <div>
                  <p className="mb-1 text-sm font-medium text-gray-700">{t('personality.results')}</p>
                  <div className="flex flex-wrap gap-1">
                    {Object.entries(mentor.personality_assessment.results.factors).map(([key, val]) => (
                      <span key={key} className="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                        {key}: {Math.round(val as number)}
                      </span>
                    ))}
                  </div>
                </div>
              )}

              <div className="flex gap-2 pt-2">
                <Button variant="outline" size="sm" onClick={() => openEdit(mentor)}>{t('common.edit')}</Button>
                <Button variant="danger" size="sm"
                  onClick={() => { if (confirm(t('common.confirm_delete'))) deleteMutation.mutate(mentor.id); }}>
                  {t('common.delete')}
                </Button>
              </div>
            </div>
          </Card>
        ))}
      </div>

      {(!data?.data || data.data.length === 0) && (
        <p className="mt-8 text-center text-gray-400">{t('common.no_results')}</p>
      )}

      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={closeModal}>
          <div className="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" onClick={e => e.stopPropagation()}>
            <h2 className="text-lg font-bold text-gray-900">{editingId ? 'Editar Mentor' : 'Crear Mentor'}</h2>
            <form onSubmit={handleSubmit} className="mt-4 space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <Input label="Nombre" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required />
                <Input label="Apellidos" value={form.last_name} onChange={e => setForm({ ...form, last_name: e.target.value })} />
              </div>
              <Input label="Email" type="email" value={form.email} onChange={e => setForm({ ...form, email: e.target.value })} required />
              <Input label={editingId ? 'Nueva contraseña (dejar vacío)' : 'Contraseña'} type="password"
                value={form.password} onChange={e => setForm({ ...form, password: e.target.value })} required={!editingId} />
              <Select label="Tipo de cliente" options={TYPE_OPTIONS.slice(1)} value={form.client_type}
                onChange={e => setForm({ ...form, client_type: e.target.value })} />
              <div className="grid grid-cols-2 gap-4">
                <Select label="Sexo" options={[
                  { value: 'M', label: 'Masculino' }, { value: 'F', label: 'Femenino' }, { value: 'N', label: 'Prefiero no decirlo' },
                ]} value={form.sex} onChange={e => setForm({ ...form, sex: e.target.value })} />
                <Select label="Idioma" options={[
                  { value: 'es', label: 'Español' }, { value: 'en', label: 'English' }, { value: 'pt', label: 'Português' },
                ]} value={form.locale} onChange={e => setForm({ ...form, locale: e.target.value })} />
              </div>
              <Input label="Fecha de nacimiento" type="date" value={form.birth_date}
                onChange={e => setForm({ ...form, birth_date: e.target.value })} />
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
