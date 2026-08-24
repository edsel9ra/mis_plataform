'use client';

import { useEffect, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslations } from 'next-intl';
import { api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { normalizePaginatedResponse, type PaginatedResponse, type RawPaginatedResponse } from '@/lib/pagination';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import {
  useSkillTags,
  useUpdateMentorSkills,
} from '@/hooks/use-mentor-skills';
import { toSkillInputs, type MentorSkillInput, type UserSkill } from '@/lib/mentor-skills';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PageSpinner } from '@/components/ui/spinner';
import { PaginationControls } from '@/components/ui/pagination';
import { Alert } from '@/components/ui/alert';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { MentorSkillsEditor } from '@/components/mentors/MentorSkillsEditor';
import { SkillTagCatalogManager } from '@/components/mentors/SkillTagCatalogManager';

interface Mentor {
  id: string;
  name: string;
  last_name: string | null;
  email: string;
  sex: string | null;
  birth_date: string | null;
  client_type: string;
  locale: string;
  personality_assessment?: { completed_at: string | null; results?: { factors?: Record<string, number> } } | null;
  skills?: UserSkill[];
  active_mentees_count: number;
  mentor_relationships: { id: string; status: string }[];
}

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
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [page, setPage] = useState(1);
  const [mutationError, setMutationError] = useState<unknown>(null);
  const [skillsMentor, setSkillsMentor] = useState<Mentor | null>(null);
  const [mentorSkills, setMentorSkills] = useState<MentorSkillInput[]>([]);
  const debouncedSearch = useDebouncedValue(search.trim());
  const skillTagsQuery = useSkillTags();
  const updateSkillsMutation = useUpdateMentorSkills(skillsMentor?.id ?? null);
  const typeOptions = [
    { value: '', label: t('common.all') },
    { value: 'personal', label: t('client_types.personal') },
    { value: 'familiar', label: t('client_types.familiar') },
    { value: 'grupal', label: t('client_types.grupal') },
    { value: 'empresa', label: t('client_types.empresa') },
  ];

  useEffect(() => setPage(1), [debouncedSearch, typeFilter]);

  const { data, isLoading, isFetching, error } = useQuery<PaginatedResponse<Mentor>>({
    queryKey: ['admin-mentors', debouncedSearch, typeFilter, page],
    queryFn: async ({ signal }) => {
      const response = await api.get<RawPaginatedResponse<Mentor>>('/admin/mentors', {
        search: debouncedSearch || undefined,
      client_type: typeFilter || undefined,
        page,
        per_page: 12,
      }, { signal });

      return normalizePaginatedResponse(response, { page, perPage: 12 });
    },
  });

  const createMutation = useMutation({
    mutationFn: (data: typeof emptyForm) => api.post('/admin/mentors', data),
    onSuccess: () => { setMutationError(null); queryClient.invalidateQueries({ queryKey: ['admin-mentors'] }); closeModal(); },
    onError: (error) => setMutationError(error),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<typeof emptyForm> }) => api.put(`/admin/mentors/${id}`, data),
    onSuccess: () => { setMutationError(null); queryClient.invalidateQueries({ queryKey: ['admin-mentors'] }); closeModal(); },
    onError: (error) => setMutationError(error),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => api.delete(`/admin/mentors/${id}`),
    onSuccess: () => { setMutationError(null); queryClient.invalidateQueries({ queryKey: ['admin-mentors'] }); },
    onError: (error) => setMutationError(error),
  });

  function openCreate() {
    setMutationError(null);
    setEditingId(null);
    setForm(emptyForm);
    setShowModal(true);
  }

  function openEdit(mentor: Mentor) {
    setMutationError(null);
    setEditingId(mentor.id);
    setForm({
      name: mentor.name, last_name: mentor.last_name || '', email: mentor.email, password: '',
      sex: ['M', 'F', 'N'].includes(mentor.sex ?? '') ? mentor.sex ?? 'N' : 'N',
      birth_date: mentor.birth_date?.slice(0, 10) ?? '',
      client_type: mentor.client_type,
      locale: ['es', 'en', 'pt'].includes(mentor.locale) ? mentor.locale : 'es',
    });
    setShowModal(true);
  }

  function closeModal() { setShowModal(false); setEditingId(null); setForm(emptyForm); }

  function openSkills(mentor: Mentor) {
    setSkillsMentor(mentor);
    setMentorSkills(toSkillInputs(mentor.skills));
  }

  function closeSkills() {
    setSkillsMentor(null);
    setMentorSkills([]);
    updateSkillsMutation.reset();
  }

  function saveSkills() {
    updateSkillsMutation.mutate(mentorSkills, { onSuccess: closeSkills });
  }

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
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-950">{t('admin.mentors')}</h1>
           <p className="mt-1 text-sm text-gray-500">{t('admin.mentors_description')}</p>
        </div>
        <div className="flex items-center gap-4">
          <span className="text-sm text-gray-500">{data?.meta.total ?? 0} {t('common.total')}</span>
          <Button onClick={openCreate}>+ {t('common.create')}</Button>
        </div>
      </div>

      <Card className="border-0 shadow-sm ring-1 ring-slate-100">
        <div className="flex flex-wrap gap-4">
          <div className="min-w-[220px] flex-1">
            <Input aria-label={t('common.search')} placeholder={t('common.search')} value={search} onChange={(e) => setSearch(e.target.value)} />
          </div>
          <div className="w-44">
             <Select options={typeOptions} value={typeFilter} onChange={(e) => setTypeFilter(e.target.value)} />
          </div>
        </div>
      </Card>

      {Boolean(error || mutationError) && (
        <Alert variant="error">
          {apiErrorMessage(error ?? mutationError, (key) => t(key)) || t('common.load_error')}
        </Alert>
      )}

      <SkillTagCatalogManager />

      <div className={`grid gap-6 sm:grid-cols-2 xl:grid-cols-3 ${isFetching ? 'opacity-60' : ''}`} aria-busy={isFetching}>
        {data?.data?.map((mentor) => (
          <Card key={mentor.id} className="border-0 shadow-sm ring-1 ring-slate-100 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl">
            <CardHeader>
              <div className="flex items-start justify-between">
                <div>
                  <CardTitle className="text-base">{mentor.name} {mentor.last_name}</CardTitle>
                  <p className="mt-1 text-sm text-gray-500">{mentor.email}</p>
                  <p className="text-xs text-gray-400">{t(`client_types.${mentor.client_type}`)}</p>
                </div>
                 <Badge variant="success">{t('admin.active_mentees_count', { count: mentor.active_mentees_count })}</Badge>
              </div>
            </CardHeader>

            <div className="space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-gray-500">{t('sessions.title')}</span>
                <span className="font-medium text-gray-900">
                   {t('admin.active_relationships_count', { count: mentor.mentor_relationships?.filter(r => r.status === 'active').length ?? 0 })}
                </span>
              </div>

               {mentor.personality_assessment?.results?.factors && (
                <div>
                  <p className="mb-1 text-sm font-medium text-gray-700">{t('personality.results')}</p>
                  <div className="flex flex-wrap gap-1">
                    {Object.entries(mentor.personality_assessment.results.factors).map(([key, val]) => (
                      <span key={key} className="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                         {oceanLabel(key, t)}: {Math.round(val as number)}
                      </span>
                    ))}
                  </div>
                </div>
               )}

              {mentor.skills && mentor.skills.length > 0 && (
                <div>
                  <p className="mb-1 text-sm font-medium text-gray-700">{t('mentors.skills')}</p>
                  <div className="flex flex-wrap gap-1">
                    {mentor.skills.map((skill) => (
                      <Badge key={skill.id} variant="primary">
                        {skill.skill?.name ?? t('common.not_applicable')}
                      </Badge>
                    ))}
                  </div>
                </div>
              )}

              <div className="flex gap-2 pt-2">
                <Button variant="outline" size="sm" onClick={() => openEdit(mentor)}>{t('common.edit')}</Button>
                <Button variant="outline" size="sm" onClick={() => openSkills(mentor)}>{t('admin.manage_specialties')}</Button>
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

      {data && (
        <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
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
        </div>
      )}

      <Dialog open={showModal} onOpenChange={(open) => { if (!open) closeModal(); else setShowModal(true); }}>
        <DialogContent aria-labelledby="admin-mentor-dialog-title">
          <DialogHeader>
           <DialogTitle id="admin-mentor-dialog-title">{editingId ? t('admin.edit_mentor') : t('admin.create_mentor')}</DialogTitle>
          </DialogHeader>
            <form onSubmit={handleSubmit} className="mt-4 space-y-4">
              <div className="grid grid-cols-2 gap-4">
                 <Input label={t('admin.name')} value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required />
                 <Input label={t('admin.last_name')} value={form.last_name} onChange={e => setForm({ ...form, last_name: e.target.value })} />
               </div>
               <Input label={t('auth.email')} type="email" value={form.email} onChange={e => setForm({ ...form, email: e.target.value })} required />
               <Input label={editingId ? t('admin.new_password_optional_short') : t('auth.password')} type="password"
                 value={form.password} onChange={e => setForm({ ...form, password: e.target.value })} required={!editingId} />
               <Select label={t('admin.client_type')} options={typeOptions.slice(1)} value={form.client_type}
                 onChange={e => setForm({ ...form, client_type: e.target.value })} />
               <div className="grid grid-cols-2 gap-4">
                 <Select label={t('auth.sex')} options={[
                   { value: 'M', label: t('auth.sex_m') }, { value: 'F', label: t('auth.sex_f') }, { value: 'N', label: t('auth.sex_n') },
                 ]} value={form.sex} onChange={e => setForm({ ...form, sex: e.target.value })} />
                 <Select label={t('admin.language')} options={[
                   { value: 'es', label: t('languages.es') }, { value: 'en', label: t('languages.en') }, { value: 'pt', label: t('languages.pt') },
                 ]} value={form.locale} onChange={e => setForm({ ...form, locale: e.target.value })} />
               </div>
               <Input label={t('auth.birth_date')} type="date" value={form.birth_date}
                onChange={e => setForm({ ...form, birth_date: e.target.value })} />
              <DialogFooter>
                <Button variant="outline" type="button" onClick={closeModal}>{t('common.cancel')}</Button>
                <Button type="submit" loading={createMutation.isPending || updateMutation.isPending}>
                  {editingId ? t('common.save') : t('common.create')}
                </Button>
              </DialogFooter>
            </form>
        </DialogContent>
      </Dialog>

      <Dialog open={Boolean(skillsMentor)} onOpenChange={(open) => { if (!open) closeSkills(); }}>
        <DialogContent aria-labelledby="mentor-skills-dialog-title" className="max-w-2xl">
          <DialogHeader>
            <DialogTitle id="mentor-skills-dialog-title">
              {t('admin.manage_specialties')}{skillsMentor ? ` · ${skillsMentor.name} ${skillsMentor.last_name ?? ''}` : ''}
            </DialogTitle>
          </DialogHeader>
          {skillTagsQuery.isLoading ? (
            <PageSpinner />
          ) : skillTagsQuery.error ? (
            <Alert variant="error">
              {apiErrorMessage(skillTagsQuery.error, (key) => t(key)) || t('common.load_error')}
            </Alert>
          ) : (
            <MentorSkillsEditor
              catalog={skillTagsQuery.data ?? []}
              value={mentorSkills}
              disabled={updateSkillsMutation.isPending}
              onChange={setMentorSkills}
            />
          )}
          {updateSkillsMutation.error && (
            <Alert variant="error" className="mt-4">
              {apiErrorMessage(updateSkillsMutation.error, (key) => t(key)) || t('mentors.skills_error')}
            </Alert>
          )}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={closeSkills}>{t('common.cancel')}</Button>
            <Button
              type="button"
              loading={updateSkillsMutation.isPending}
              disabled={skillTagsQuery.isLoading || Boolean(skillTagsQuery.error)}
              onClick={saveSkills}
            >
              {t('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function oceanLabel(key: string, t: ReturnType<typeof useTranslations>): string {
  const labels: Record<string, string> = {
    O: t('personality.openness'),
    C: t('personality.conscientiousness'),
    E: t('personality.extraversion'),
    A: t('personality.agreeableness'),
    N: t('personality.neuroticism'),
  };

  return labels[key] ?? t('common.not_applicable');
}
