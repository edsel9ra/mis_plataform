'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslations } from 'next-intl';
import { useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { api } from '@/lib/api';
import { useAuth } from '@/components/auth/AuthProvider';
import { Card, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PageSpinner } from '@/components/ui/spinner';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { clientTypeOptions, sexOptions } from '@/lib/schemas/auth';

interface User {
  id: string;
  name: string;
  last_name: string | null;
  email: string;
  role: string;
  client_type: string | null;
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

const CLIENT_TYPES = clientTypeOptions;
const USER_ROLES = ['super_admin', 'admin', 'company_admin', 'employee', 'mentor', 'mentee'] as const;
const PLATFORM_ADMIN_ROLES = ['super_admin', 'admin'] as const;

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

const adminUserSchema = z.object({
  name: z.string().min(1, 'Nombre requerido').max(255, 'Máximo 255 caracteres'),
  last_name: z.string().max(255, 'Máximo 255 caracteres').optional(),
  email: z.string().email('Email inválido'),
  password: z.string().max(255, 'Máximo 255 caracteres').optional(),
  sex: z.enum(sexOptions),
  birth_date: z.string().optional(),
  client_type: z.enum(CLIENT_TYPES).nullable().optional(),
  role: z.enum(USER_ROLES),
  locale: z.enum(['es', 'en', 'pt']),
}).superRefine((data, ctx) => {
  if (!isPlatformAdminRole(data.role) && !data.client_type) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'Tipo requerido',
      path: ['client_type'],
    });
  }
});

type AdminUserForm = z.infer<typeof adminUserSchema>;

const emptyForm: AdminUserForm = {
  name: '', last_name: '', email: '', password: '',
  sex: 'N', birth_date: '', client_type: 'personal', role: 'mentee', locale: 'es',
};

export default function AdminUsers() {
  const t = useTranslations();
  const { user: currentUser } = useAuth();
  const queryClient = useQueryClient();
  const [search, setSearch] = useState('');
  const [roleFilter, setRoleFilter] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [page, setPage] = useState(1);
  const [showModal, setShowModal] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const {
    register,
    handleSubmit,
    reset,
    setError,
    control,
    formState: { errors },
  } = useForm<AdminUserForm>({
    resolver: zodResolver(adminUserSchema),
    reValidateMode: 'onBlur',
    defaultValues: emptyForm,
  });

  const assignableRoleOptions = currentUser?.role === 'super_admin'
    ? ROLE_OPTIONS.filter((option) => option.value)
    : ROLE_OPTIONS.filter((option) => option.value && option.value !== 'super_admin');
  const selectedRole = useWatch({ control, name: 'role', defaultValue: emptyForm.role });

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
    mutationFn: (data: AdminUserForm) => api.post('/admin/users', normalizedPayload(data)),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin-users'] }); closeModal(); },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: AdminUserForm }) => api.put(`/admin/users/${id}`, normalizedPayload(data)),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admin-users'] }); closeModal(); },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/admin/users/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin-users'] }),
  });

  function openCreate() {
    setEditingId(null);
    reset(emptyForm);
    setShowModal(true);
  }

  function openEdit(user: User) {
    setEditingId(user.id);
    reset({
      name: user.name, last_name: user.last_name || '', email: user.email, password: '',
      sex: (['M', 'F', 'N'].includes(user.sex ?? '') ? user.sex : 'N') as AdminUserForm['sex'],
      birth_date: '',
      client_type: (isClientType(user.client_type) ? user.client_type : 'personal') as AdminUserForm['client_type'],
      role: (isUserRole(user.role) ? user.role : 'mentee') as AdminUserForm['role'],
      locale: (['es', 'en', 'pt'].includes(user.locale) ? user.locale : 'es') as AdminUserForm['locale'],
    });
    setShowModal(true);
  }

  function closeModal() { setShowModal(false); setEditingId(null); reset(emptyForm); }

  function submitForm(form: AdminUserForm) {
    if (!editingId && !form.password) {
      setError('password', { message: 'Contraseña requerida' });
      return;
    }

    if (editingId) {
      updateMutation.mutate({ id: editingId, data: form });
    } else {
      createMutation.mutate(form);
    }
  }

  function normalizedPayload(form: AdminUserForm) {
    const payload = {
      ...form,
      client_type: isPlatformAdminRole(form.role) ? null : form.client_type,
      last_name: form.last_name || undefined,
      birth_date: form.birth_date || undefined,
      password: form.password || undefined,
    };

    return payload;
  }

  function clientTypeLabel(clientType: string | null) {
    return clientType ? t(`client_types.${clientType}`) : t('common.not_applicable');
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
                  <td className="px-4 py-3 text-gray-600">{clientTypeLabel(user.client_type)}</td>
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

      <Dialog open={showModal} onOpenChange={(open) => { if (!open) closeModal(); else setShowModal(true); }}>
        <DialogContent aria-labelledby="admin-user-dialog-title">
          <DialogHeader>
            <DialogTitle id="admin-user-dialog-title">{editingId ? 'Editar Usuario' : 'Crear Usuario'}</DialogTitle>
          </DialogHeader>
            <form onSubmit={handleSubmit(submitForm)} className="mt-4 space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <Input label="Nombre" error={errors.name?.message} {...register('name')} />
                <Input label="Apellidos" error={errors.last_name?.message} {...register('last_name')} />
              </div>
              <Input label="Email" type="email" error={errors.email?.message} {...register('email')} />
              <Input label={editingId ? 'Nueva contraseña (dejar vacío para mantener)' : 'Contraseña'} type="password"
                error={errors.password?.message} {...register('password')} />
              <div className="grid grid-cols-2 gap-4">
                <Select label="Rol" options={assignableRoleOptions} error={errors.role?.message} {...register('role')} />
                {isPlatformAdminRole(selectedRole) ? (
                  <div className="space-y-1">
                    <span className="block text-sm font-medium text-gray-700">Tipo</span>
                    <div className="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                      {t('common.not_applicable')}
                    </div>
                  </div>
                ) : (
                  <Select label="Tipo" options={TYPE_OPTIONS.slice(1)} error={errors.client_type?.message} {...register('client_type')} />
                )}
              </div>
              <div className="grid grid-cols-2 gap-4">
                <Select label="Sexo" options={[
                  { value: 'M', label: 'Masculino' }, { value: 'F', label: 'Femenino' }, { value: 'N', label: 'Prefiero no decirlo' },
                ]} error={errors.sex?.message} {...register('sex')} />
                <Select label="Idioma" options={[
                  { value: 'es', label: 'Español' }, { value: 'en', label: 'English' }, { value: 'pt', label: 'Português' },
                ]} error={errors.locale?.message} {...register('locale')} />
              </div>
              <Input label="Fecha de nacimiento" type="date" error={errors.birth_date?.message} {...register('birth_date')} />
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

function isClientType(value: string | null): value is (typeof CLIENT_TYPES)[number] {
  return typeof value === 'string' && CLIENT_TYPES.includes(value as (typeof CLIENT_TYPES)[number]);
}

function isUserRole(value: string): value is AdminUserForm['role'] {
  return USER_ROLES.includes(value as AdminUserForm['role']);
}

function isPlatformAdminRole(role: string | undefined): boolean {
  return PLATFORM_ADMIN_ROLES.includes(role as (typeof PLATFORM_ADMIN_ROLES)[number]);
}
