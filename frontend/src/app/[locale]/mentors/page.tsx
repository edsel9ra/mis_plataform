'use client';

import { useDeferredValue, useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { useLocale, useTranslations } from 'next-intl';
import { api } from '@/lib/api';
import { AppShell } from '@/components/layout/AppShell';
import { useAuth } from '@/components/auth/AuthProvider';
import { Input } from '@/components/ui/input';
import { PageSpinner } from '@/components/ui/spinner';
import { localePath } from '@/i18n/paths';

interface Mentor {
  id: string;
  full_name: string;
  avatar: string | null;
  bio: string | null;
  client_type: string;
  active_mentees_count?: number;
  skills?: { skill_tag?: { name: string } }[];
}

export default function MentorsPage() {
  const t = useTranslations();
  const locale = useLocale();
  const { user, isLoading: authLoading } = useAuth();
  const [search, setSearch] = useState('');
  const deferredSearch = useDeferredValue(search);

  const { data, isLoading } = useQuery<{ data: Mentor[] }>({
    queryKey: ['mentors', deferredSearch, user?.client_type],
    queryFn: () => api.get('/mentors', {
      search: deferredSearch || undefined,
      client_type: user?.client_type || undefined,
    }),
    enabled: !authLoading,
  });

  if (authLoading || isLoading) return user ? <AppShell><PageSpinner /></AppShell> : <PageSpinner />;

  const mentors = data?.data ?? [];
  const content = (
    <div className="mx-auto max-w-7xl px-4 py-8">
      <h1 className="text-2xl font-bold text-gray-900">{t('nav.mentors')}</h1>

      <div className="mt-6">
        <Input
          placeholder={t('common.search')}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="max-w-md"
        />
      </div>

      <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {mentors.map((mentor) => (
          <Link
            key={mentor.id}
            href={localePath(`/mentors/${mentor.id}`, locale)}
            className="rounded-2xl border bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg"
          >
            <div className="flex items-center gap-4">
              <div className="flex h-12 w-12 items-center justify-center overflow-hidden rounded-2xl bg-primary-100 text-lg font-bold text-primary-700">
                {mentor.avatar ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={mentor.avatar} alt="" className="h-full w-full object-cover" />
                ) : (
                  mentor.full_name.charAt(0)
                )}
              </div>
              <div>
                <h3 className="font-semibold text-gray-900">{mentor.full_name}</h3>
                <p className="text-sm text-gray-500">{t(`client_types.${mentor.client_type}`)}</p>
              </div>
            </div>
            {mentor.bio && <p className="mt-4 line-clamp-2 text-sm leading-6 text-gray-500">{mentor.bio}</p>}
            <div className="mt-4 flex flex-wrap gap-2">
              {(mentor.skills ?? []).map((skill, index) => (
                <span key={skill.skill_tag?.name ?? index} className="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                  {skill.skill_tag?.name}
                </span>
              ))}
            </div>
            <p className="mt-4 text-xs font-medium text-gray-500">
              {t('mentors.active_mentees')}: {mentor.active_mentees_count ?? 0}
            </p>
          </Link>
        ))}
      </div>

      {mentors.length === 0 && (
        <p className="mt-8 text-center text-gray-400">{t('common.no_results')}</p>
      )}
    </div>
  );

  return user ? <AppShell>{content}</AppShell> : content;
}
