'use client';

import { useEffect, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslations } from 'next-intl';
import { useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { normalizePaginatedResponse, type PaginatedResponse, type RawPaginatedResponse } from '@/lib/pagination';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { useAuth } from '@/components/auth/AuthProvider';
import { Card, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PageSpinner } from '@/components/ui/spinner';
import { Alert } from '@/components/ui/alert';
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

const CLIENT_TYPES = clientTypeOptions;
const USER_ROLES = ['super_admin', 'admin', 'company_admin', 'employee', 'mentor', 'mentee'] as const;
const PLATFORM_ADMIN_ROLES = ['super_admin', 'admin'] as const;

const ROLE_BADGE: Record<string, 'primary' | 'success' | 'warning' | 'default' | 'danger'> = {
  super_admin: 'danger',
  admin: 'primary',
  company_admin: 'warning',
  mentor: 'success',
};

const adminUserSchema = z.object({
  name: z.string().min(1, 'admin.validation.name_required').max(255, 'admin.validation.max_255'),
  last_name: z.string().max(255, 'admin.validation.max_255').optional(),
  email: z.string().email('admin.validation.email_invalid'),
  password: z.string().max(255, 'admin.validation.max_255').optional(),
  sex: z.enum(sexOptions, {
    required_error: 'admin.validation.sex_required',
    invalid_type_error: 'admin.validation.sex_invalid',
  }),
  birth_date: z.string().optional(),
  client_type: z.enum(CLIENT_TYPES, { invalid_type_error: 'admin.validation.type_invalid' }).nullable().optional(),
  role: z.enum(USER_ROLES, { invalid_type_error: 'admin.validation.role_invalid' }),
  locale: z.enum(['es', 'en', 'pt'], { invalid_type_error: 'admin.validation.language_invalid' }),
}).superRefine((data, ctx) => {
  if (!isPlatformAdminRole(data.role) && !data.client_type) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
       message: 'admin.validation.type_required',
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
  const [mutationError, setMutationError] = useState<unknown>(null);
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

  const roleOptions = [
    { value: '', label: t('common.all') },
    { value: 'super_admin', label: t('roles.super_admin') },
    { value: 'admin', label: t('roles.admin') },
    { value: 'company_admin', label: t('roles.company_admin') },
    { value: 'employee', label: t('roles.employee') },
    { value: 'mentor', label: t('roles.mentor') },
    { value: 'mentee', label: t('roles.mentee') },
  ];
  const typeOptions = [
    { value: '', label: t('common.all') },
    { value: 'personal', label: t('client_types.personal') },
    { value: 'familiar', label: t('client_types.familiar') },
    { value: 'grupal', label: t('client_types.grupal') },
    { value: 'empresa', label: t('client_types.empresa') },
  ];

  const assignableRoleOptions = currentUser?.role === 'super_admin'
    ? roleOptions.filter((option) => option.value)
    : roleOptions.filter((option) => option.value && option.value !== 'super_admin');
  const selectedRole = useWatch({ control, name: 'role', defaultValue: emptyForm.role });
  const debouncedSearch = useDebouncedValue(search.trim());

  useEffect(() => setPage(1), [debouncedSearch, roleFilter, typeFilter]);

  const { data, isLoading, isFetching, error } = useQuery<PaginatedResponse<User>>({
    queryKey: ['admin-users', debouncedSearch, roleFilter, typeFilter, page],
    queryFn: async ({ signal }) => {
      const response = await api.get<RawPaginatedResponse<User>>('/admin/users', {
      search: debouncedSearch || undefined,
      role: roleFilter || undefined,
      client_type: typeFilter || undefined,
      per_page: 20, page,
      }, { signal });

      return normalizePaginatedResponse(response, { page, perPage: 20 });
    },
  });

  const createMutation = useMutation({
    mutationFn: (data: AdminUserForm) => api.post('/admin/users', normalizedPayload(data)),
    onSuccess: () => { setMutationError(null); queryClient.invalidateQueries({ queryKey: ['admin-users'] }); closeModal(); },
    onError: (error) => setMutationError(error),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: AdminUserForm }) => api.put(`/admin/users/${id}`, normalizedPayload(data)),
    onSuccess: () => { setMutationError(null); queryClient.invalidateQueries({ queryKey: ['admin-users'] }); closeModal(); },
    onError: (error) => setMutationError(error),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/admin/users/${id}`),
    onSuccess: () => { setMutationError(null); queryClient.invalidateQueries({ queryKey: ['admin-users'] }); },
    onError: (error) => setMutationError(error),
  });

  function openCreate() {
    setMutationError(null);
    setEditingId(null);
    reset(emptyForm);
    setShowModal(true);
  }

  function openEdit(user: User) {
    setMutationError(null);
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
       setError('password', { message: 'admin.validation.password_required' });
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
          <span className="text-sm text-gray-500">{data?.meta.total ?? 0} {t('common.total')}</span>
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
               <Select options={roleOptions} value={roleFilter}
                onChange={(e) => { setRoleFilter(e.target.value); setPage(1); }} />
            </div>
             <div className="w-40">
               <Select options={typeOptions} value={typeFilter}
                onChange={(e) => { setTypeFilter(e.target.value); setPage(1); }} />
            </div>
          </div>
        </CardHeader>

        {Boolean(error || mutationError) && (
          <Alert variant="error" className="mx-4 mb-4">
            {apiErrorMessage(error ?? mutationError, (key) => t(key)) || t('common.load_error')}
          </Alert>
        )}

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
                   <td className="px-4 py-3"><Badge variant={ROLE_BADGE[user.role] || 'default'}>{roleLabel(user.role, t)}</Badge></td>
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

        {data && data.meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t px-4 py-3">
            <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(p => p - 1)}>
              {t('common.previous')}
            </Button>
            <span className="text-sm text-gray-600">{t('common.page')} {data.meta.current_page} {t('common.of')} {data.meta.last_page}</span>
            <Button variant="outline" size="sm" disabled={page >= data.meta.last_page || isFetching} onClick={() => setPage(p => p + 1)}>
              {t('common.next')}
            </Button>
          </div>
        )}
      </Card>

      <Dialog open={showModal} onOpenChange={(open) => { if (!open) closeModal(); else setShowModal(true); }}>
        <DialogContent aria-labelledby="admin-user-dialog-title">
          <DialogHeader>
             <DialogTitle id="admin-user-dialog-title">{editingId ? t('admin.edit_user') : t('admin.create_user')}</DialogTitle>
          </DialogHeader>
            <form onSubmit={handleSubmit(submitForm)} className="mt-4 space-y-4">
              <div className="grid grid-cols-2 gap-4">
                 <Input label={t('admin.name')} error={validationMessage(errors.name?.message, t)} {...register('name')} />
                 <Input label={t('admin.last_name')} error={validationMessage(errors.last_name?.message, t)} {...register('last_name')} />
               </div>
               <Input label={t('auth.email')} type="email" error={validationMessage(errors.email?.message, t)} {...register('email')} />
               <Input label={editingId ? t('admin.new_password_optional') : t('auth.password')} type="password"
                 error={validationMessage(errors.password?.message, t)} {...register('password')} />
               <div className="grid grid-cols-2 gap-4">
                 <Select label={t('admin.role')} options={assignableRoleOptions} error={validationMessage(errors.role?.message, t)} {...register('role')} />
                 {isPlatformAdminRole(selectedRole) ? (
                   <div className="space-y-1">
                     <span className="block text-sm font-medium text-gray-700">{t('admin.type')}</span>
                    <div className="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                      {t('common.not_applicable')}
                    </div>
                  </div>
                 ) : (
                   <Select label={t('admin.type')} options={typeOptions.slice(1)} error={validationMessage(errors.client_type?.message, t)} {...register('client_type')} />
                 )}
               </div>
               <div className="grid grid-cols-2 gap-4">
                 <Select label={t('auth.sex')} options={[
                   { value: 'M', label: t('auth.sex_m') }, { value: 'F', label: t('auth.sex_f') }, { value: 'N', label: t('auth.sex_n') },
                 ]} error={validationMessage(errors.sex?.message, t)} {...register('sex')} />
                 <Select label={t('admin.language')} options={[
                   { value: 'es', label: t('languages.es') }, { value: 'en', label: t('languages.en') }, { value: 'pt', label: t('languages.pt') },
                 ]} error={validationMessage(errors.locale?.message, t)} {...register('locale')} />
               </div>
               <Input label={t('auth.birth_date')} type="date" error={validationMessage(errors.birth_date?.message, t)} {...register('birth_date')} />
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

function validationMessage(message: string | undefined, t: ReturnType<typeof useTranslations>): string | undefined {
  return message ? t(message) : undefined;
}

function roleLabel(role: string, t: ReturnType<typeof useTranslations>): string {
  const labels: Record<string, string> = {
    super_admin: t('roles.super_admin'),
    admin: t('roles.admin'),
    company_admin: t('roles.company_admin'),
    employee: t('roles.employee'),
    mentor: t('roles.mentor'),
    mentee: t('roles.mentee'),
  };

  return labels[role] ?? t('common.not_applicable');
}
