'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { useLocale, useTranslations } from 'next-intl';
import { api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { normalizePaginatedResponse, type PaginatedResponse, type RawPaginatedResponse } from '@/lib/pagination';
import { AppShell } from '@/components/layout/AppShell';
import { useAuth } from '@/components/auth/AuthProvider';
import { Input } from '@/components/ui/input';
import { Alert } from '@/components/ui/alert';
import { Avatar } from '@/components/ui/avatar';
import { PaginationControls } from '@/components/ui/pagination';
import { PageSpinner } from '@/components/ui/spinner';
import { localePath } from '@/i18n/paths';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import type { UserSkill } from '@/lib/mentor-skills';

interface Mentor {
  id: string;
  full_name: string;
  avatar: string | null;
  bio: string | null;
  client_type: string;
  active_mentees_count?: number;
  skills?: UserSkill[];
}

export default function MentorsPage() {
  const t = useTranslations();
  const locale = useLocale();
  const { user, isLoading: authLoading } = useAuth();
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const debouncedSearch = useDebouncedValue(search.trim());

  useEffect(() => setPage(1), [debouncedSearch, user?.client_type]);

  const { data, isLoading, isFetching, error } = useQuery<PaginatedResponse<Mentor>>({
    queryKey: ['mentors', { search: debouncedSearch, client_type: user?.client_type, page }],
    queryFn: async ({ signal }) => {
      const response = await api.get<RawPaginatedResponse<Mentor>>('/mentors', {
        search: debouncedSearch || undefined,
        page,
        per_page: 12,
      client_type: user?.client_type || undefined,
      }, { signal });

      return normalizePaginatedResponse(response, { page, perPage: 12 });
    },
    enabled: !authLoading,
  });

  if (authLoading || isLoading) return user ? <AppShell><PageSpinner /></AppShell> : <PageSpinner />;

  const mentors = data?.data ?? [];
  const content = (
    <div className="mx-auto max-w-7xl px-4 py-8">
      <h1 className="text-2xl font-bold text-gray-900">{t('nav.mentors')}</h1>

      <div className="mt-6">
        <Input
          aria-label={t('common.search')}
          placeholder={t('common.search')}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="max-w-md"
        />
      </div>

       {error && <Alert className="mt-6" variant="error">{apiErrorMessage(error, (key) => t(key)) || t('common.load_error')}</Alert>}

      <div className={`mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 ${isFetching ? 'opacity-60' : ''}`} aria-busy={isFetching}>
        {mentors.map((mentor) => (
          <Link
            key={mentor.id}
            href={localePath(`/mentors/${mentor.id}`, locale)}
            className="rounded-2xl border bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg"
          >
            <div className="flex items-center gap-4">
              <Avatar
                src={mentor.avatar}
                alt={mentor.full_name}
                size="md"
                fallback={mentor.full_name.charAt(0)}
                className="rounded-2xl bg-primary-100 text-lg font-bold text-primary-700"
              />
              <div>
                <h3 className="font-semibold text-gray-900">{mentor.full_name}</h3>
                <p className="text-sm text-gray-500">{t(`client_types.${mentor.client_type}`)}</p>
              </div>
            </div>
            {mentor.bio && <p className="mt-4 line-clamp-2 text-sm leading-6 text-gray-500">{mentor.bio}</p>}
            <div className="mt-4 flex flex-wrap gap-2">
              {(mentor.skills ?? []).map((skill, index) => (
                <span key={skill.id ?? index} className="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                  {skill.skill?.name}
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

      {data && (
        <div className="mt-8 rounded-2xl border bg-white shadow-sm">
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
    </div>
  );

  return user ? <AppShell>{content}</AppShell> : content;
}
