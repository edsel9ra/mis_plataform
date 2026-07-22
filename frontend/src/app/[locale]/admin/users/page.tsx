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

interface User {
  id: string;
  name: string;
  last_name: string | null;
  email: string;
  role: string;
  client_type: string;
  sex: string | null;
  locale: string;
  personality_assessment: { completed_at: string | null } | null;
}

interface PaginatedResponse {
  data: User[];
  current_page: number;
  last_page: number;
  total: number;
}

const ROLE_OPTIONS = [
  { value: '', label: 'Todos' },
  { value: 'super_admin', label: 'Super Admin' },
  { value: 'admin', label: 'Admin' },
  { value: 'company_admin', label: 'Company Admin' },
  { value: 'employee', label: 'Employee' },
  { value: 'mentor', label: 'Mentor' },
  { value: 'mentee', label: 'Mentee' },
];

const TYPE_OPTIONS = [
  { value: '', label: 'Todos' },
  { value: 'personal', label: 'Personal' },
  { value: 'familiar', label: 'Familiar' },
  { value: 'grupal', label: 'Grupal' },
  { value: 'empresa', label: 'Empresa' },
];

const ROLE_BADGE: Record<string, 'primary' | 'success' | 'warning' | 'default' | 'danger'> = {
  super_admin: 'danger',
  admin: 'primary',
  company_admin: 'warning',
  mentor: 'success',
};

const emptyForm = {
  name: '', last_name: '', email: '', password: '',
  sex: 'N', birth_date: '', client_type: 'personal', role: 'mentee', locale: 'es',
};

export default function AdminUsers() {
  const t = useTranslations();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState('');
  const [roleFilter, setRoleFilter] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [page, setPage] = useState(1);
  const [showModal, setShowModal] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState(emptyForm);

  const { data, isLoading } = useQuery<PaginatedResponse>({
    queryKey: ['admin-users', search, roleFilter, typeFilter, page],
    queryFn: () => api.get<PaginatedResponse>('/admin/users', {
      search: search || undefined,
      role: roleFilter || undefined,
      client_type: typeFilter || undefined,
      per_page: 20, page,
    }),
  });

  const createMutation = useMutation({
    mutationFn: (data: typeof form) => api.post('/admin/users', data),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin-users'] }); closeModal(); },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<typeof form> }) => api.put(`/admin/users/${id}`, data),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin-users'] }); closeModal(); },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/admin/users/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-users'] }),
  });

  function openCreate() {
    setEditingId(null);
    setForm(emptyForm);
    setShowModal(true);
  }

  function openEdit(user: User) {
    setEditingId(user.id);
    setForm({
      name: user.name, last_name: user.last_name || '', email: user.email, password: '',
      sex: user.sex || 'N', birth_date: '', client_type: user.client_type, role: user.role, locale: user.locale,
    });
    setShowModal(true);
  }

  function closeModal() { setShowModal(false); setEditingId(null); setForm(emptyForm); }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    const payload = editingId
      ? { id: editingId, data: form.password ? form : { ...form, password: undefined } }
      : form;
    if (editingId) {
      updateMutation.mutate(payload as { id: string; data: Partial<typeof form> });
    } else {
      createMutation.mutate(form);
    }
  }

  if (isLoading) return <PageSpinner />;

  return (
    <div>
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">{t('admin.users')}</h1>
        <div className="flex items-center gap-4">
          <span className="text-sm text-gray-500">{data?.total ?? 0} {t('common.total')}</span>
          <Button onClick={openCreate}>+ {t('common.create')}</Button>
        </div>
      </div>

      <Card className="mt-6">
        <CardHeader>
          <div className="flex flex-wrap gap-4">
            <div className="flex-1 min-w-[200px]">
              <Input placeholder={t('common.search')} value={search}
                onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
            </div>
            <div className="w-40">
              <Select options={ROLE_OPTIONS} value={roleFilter}
                onChange={(e) => { setRoleFilter(e.target.value); setPage(1); }} />
            </div>
            <div className="w-40">
              <Select options={TYPE_OPTIONS} value={typeFilter}
                onChange={(e) => { setTypeFilter(e.target.value); setPage(1); }} />
            </div>
          </div>
        </CardHeader>

        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-left text-gray-500">
                <th className="px-4 py-3 font-medium">{t('auth.name')}</th>
                <th className="px-4 py-3 font-medium">{t('auth.email')}</th>
                <th className="px-4 py-3 font-medium">{t('admin.role')}</th>
                <th className="px-4 py-3 font-medium">{t('admin.type')}</th>
                <th className="px-4 py-3 font-medium">{t('personality.results')}</th>
                <th className="px-4 py-3 font-medium">{t('common.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {data?.data?.map((user) => (
                <tr key={user.id} className="border-b hover:bg-gray-50">
                  <td className="px-4 py-3 font-medium text-gray-900">{user.name} {user.last_name}</td>
                  <td className="px-4 py-3 text-gray-600">{user.email}</td>
                  <td className="px-4 py-3"><Badge variant={ROLE_BADGE[user.role] || 'default'}>{user.role}</Badge></td>
                  <td className="px-4 py-3 text-gray-600">{t(`client_types.${user.client_type}`)}</td>
                  <td className="px-4 py-3">
                    {user.personality_assessment?.completed_at
                      ? <Badge variant="success">{t('common.completed')}</Badge>
                      : <Badge variant="warning">{t('common.pending')}</Badge>}
                  </td>
                  <td className="px-4 py-3 flex gap-2">
                    <Button variant="outline" size="sm" onClick={() => openEdit(user)}>{t('common.edit')}</Button>
                    <Button variant="danger" size="sm"
                      onClick={() => { if (confirm(t('common.confirm_delete'))) deleteMutation.mutate(user.id); }}>
                      {t('common.delete')}
                    </Button>
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
            <span className="text-sm text-gray-600">{t('common.page')} {data.current_page} {t('common.of')} {data.last_page}</span>
            <Button variant="outline" size="sm" disabled={page >= data.last_page} onClick={() => setPage(p => p + 1)}>
              {t('common.next')}
            </Button>
          </div>
        )}
      </Card>

      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={closeModal}>
          <div className="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" onClick={e => e.stopPropagation()}>
            <h2 className="text-lg font-bold text-gray-900">{editingId ? 'Editar Usuario' : 'Crear Usuario'}</h2>
            <form onSubmit={handleSubmit} className="mt-4 space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <Input label="Nombre" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required />
                <Input label="Apellidos" value={form.last_name} onChange={e => setForm({ ...form, last_name: e.target.value })} />
              </div>
              <Input label="Email" type="email" value={form.email} onChange={e => setForm({ ...form, email: e.target.value })} required />
              <Input label={editingId ? 'Nueva contraseña (dejar vacío para mantener)' : 'Contraseña'} type="password"
                value={form.password} onChange={e => setForm({ ...form, password: e.target.value })}
                required={!editingId} />
              <div className="grid grid-cols-2 gap-4">
                <Select label="Rol" options={ROLE_OPTIONS.slice(1)} value={form.role}
                  onChange={e => setForm({ ...form, role: e.target.value })} />
                <Select label="Tipo" options={TYPE_OPTIONS.slice(1)} value={form.client_type}
                  onChange={e => setForm({ ...form, client_type: e.target.value })} />
              </div>
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
