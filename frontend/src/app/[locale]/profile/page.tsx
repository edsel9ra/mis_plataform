'use client';

import { useLocale, useTranslations } from 'next-intl';
import { useRouter } from 'next/navigation';
import { useEffect, useState } from 'react';
import { useAuth } from '@/components/auth/AuthProvider';
import { AppShell } from '@/components/layout/AppShell';
import { Card } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { localePath } from '@/i18n/paths';
import { toSkillInputs, type MentorSkillInput } from '@/lib/mentor-skills';
import { localeOptions } from '@/lib/schemas/auth';
import { MentorSkillsEditor } from '@/components/mentors/MentorSkillsEditor';
import { useSkillTags, useUpdateOwnMentorSkills } from '@/hooks/use-mentor-skills';

export default function ProfilePage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const { user, refreshUser } = useAuth();
  const [isSavingLocale, setIsSavingLocale] = useState(false);
  const [localeError, setLocaleError] = useState<unknown>(null);
  const [mentorSkills, setMentorSkills] = useState<MentorSkillInput[]>([]);
  const [skillsSaved, setSkillsSaved] = useState(false);
  const isMentor = user?.role === 'mentor';
  const skillTagsQuery = useSkillTags(isMentor);
  const updateSkills = useUpdateOwnMentorSkills();

  useEffect(() => {
    if (isMentor) setMentorSkills(toSkillInputs(user?.skills));
  }, [isMentor, user?.id, user?.skills]);

  async function changeLocale(nextLocale: string) {
    if (!localeOptions.includes(nextLocale as (typeof localeOptions)[number]) || nextLocale === locale) return;

    setIsSavingLocale(true);
    setLocaleError(null);

    try {
      await api.put('/profile/locale', { locale: nextLocale });
      await refreshUser();
      router.replace(localePath('/profile', nextLocale));
    } catch (error) {
      setLocaleError(error);
    } finally {
      setIsSavingLocale(false);
    }
  }

  async function saveMentorSkills() {
    setSkillsSaved(false);

    try {
      await updateSkills.mutateAsync(mentorSkills);
      await refreshUser();
      setSkillsSaved(true);
    } catch {
      setSkillsSaved(false);
    }
  }

  return (
    <AppShell>
      <div className="mx-auto max-w-2xl space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-950">{t('nav.profile')}</h1>
          <p className="mt-1 text-sm text-gray-500">{t('profile.subtitle')}</p>
        </div>
        <Card className="border-0 shadow-sm">
          <div className="flex items-center gap-4">
            <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-100 text-2xl font-bold text-primary-700">
              {user?.name?.charAt(0)}
            </div>
            <div>
              <h2 className="text-xl font-semibold text-gray-950">{user?.name} {user?.last_name}</h2>
              <p className="text-gray-500">{user?.email}</p>
              <p className="text-sm text-gray-400">
                {user?.client_type ? clientTypeLabel(user.client_type, t) : t('common.not_applicable')}
                {' · '}
                {user?.role ? roleLabel(user.role, t) : t('common.not_applicable')}
              </p>
            </div>
          </div>
        </Card>
        <Card className="border-0 shadow-sm">
          <h2 className="text-lg font-semibold text-gray-950">{t('profile.language')}</h2>
          <p className="mt-1 text-sm text-gray-500">{t('profile.language_description')}</p>
          <div className="mt-4 max-w-sm">
            <Select
              label={t('profile.language')}
              value={user?.locale ?? locale}
              disabled={isSavingLocale}
              onChange={(event) => void changeLocale(event.target.value)}
              options={[
                { value: 'es', label: t('languages.es') },
                { value: 'en', label: t('languages.en') },
                { value: 'pt', label: t('languages.pt') },
              ]}
            />
          </div>
          {Boolean(localeError) && (
            <Alert variant="error" className="mt-4">
              {apiErrorMessage(localeError, (key) => t(key)) || t('profile.language_error')}
            </Alert>
          )}
        </Card>
        {isMentor && (
          <Card className="border-0 shadow-sm">
            <div>
              <h2 className="text-lg font-semibold text-gray-950">{t('mentors.skills')}</h2>
              <p className="mt-1 text-sm text-gray-500">{t('mentors.skills_description')}</p>
            </div>
            {skillTagsQuery.isLoading ? (
              <p className="mt-4 text-sm text-gray-500">{t('common.loading')}</p>
            ) : skillTagsQuery.error ? (
              <Alert variant="error" className="mt-4">
                {apiErrorMessage(skillTagsQuery.error, (key) => t(key)) || t('common.load_error')}
              </Alert>
            ) : (
              <div className="mt-4">
                <MentorSkillsEditor
                  catalog={skillTagsQuery.data ?? []}
                  value={mentorSkills}
                  disabled={updateSkills.isPending}
                  onChange={(value) => {
                    setSkillsSaved(false);
                    setMentorSkills(value);
                  }}
                />
                <div className="mt-5 flex flex-wrap items-center gap-3">
                  <Button type="button" loading={updateSkills.isPending} onClick={() => void saveMentorSkills()}>
                    {t('common.save')}
                  </Button>
                  {skillsSaved && <Alert variant="success">{t('mentors.skills_saved')}</Alert>}
                </div>
                {updateSkills.error && (
                  <Alert variant="error" className="mt-4">
                    {apiErrorMessage(updateSkills.error, (key) => t(key)) || t('mentors.skills_error')}
                  </Alert>
                )}
              </div>
            )}
          </Card>
        )}
      </div>
    </AppShell>
  );
}

function clientTypeLabel(value: string, t: ReturnType<typeof useTranslations>): string {
  const labels: Record<string, string> = {
    personal: t('client_types.personal'),
    familiar: t('client_types.familiar'),
    grupal: t('client_types.grupal'),
    empresa: t('client_types.empresa'),
  };

  return labels[value] ?? t('common.not_applicable');
}

function roleLabel(value: string, t: ReturnType<typeof useTranslations>): string {
  const labels: Record<string, string> = {
    super_admin: t('roles.super_admin'),
    admin: t('roles.admin'),
    company_admin: t('roles.company_admin'),
    employee: t('roles.employee'),
    mentor: t('roles.mentor'),
    mentee: t('roles.mentee'),
  };

  return labels[value] ?? t('common.not_applicable');
}
