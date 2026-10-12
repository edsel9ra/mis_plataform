'use client';

import { useQuery } from '@tanstack/react-query';
import { useLocale, useTranslations } from 'next-intl';
import { api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { AppShell } from '@/components/layout/AppShell';
import { useAuth } from '@/components/auth/AuthProvider';
import { Badge } from '@/components/ui/badge';
import { Alert } from '@/components/ui/alert';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { PageSpinner } from '@/components/ui/spinner';
import { BookOpen, Clock, Layers, Route } from 'lucide-react';

interface LearningPath {
  id: string;
  title: string | Record<string, string>;
  description?: string | Record<string, string> | null;
  level?: string | null;
  estimated_hours?: number | null;
  modules?: { id: string; resources?: { id: string }[] }[];
  user_progress?: { progress: number | string; status: string }[];
}

export default function LearningPathsPage() {
  const t = useTranslations();
  const locale = useLocale();
  const { user } = useAuth();
  const { data, isLoading, error } = useQuery<LearningPath[] | { data: LearningPath[] }>({
    queryKey: ['learning-paths', user?.client_type],
    queryFn: ({ signal }) => api.get<LearningPath[] | { data: LearningPath[] }>(
      '/learning-paths',
      { client_type: user?.client_type || undefined },
      { signal },
    ),
    enabled: !!user,
  });

  const paths = Array.isArray(data) ? data : data?.data ?? [];

  return (
    <AppShell>
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-950">{t('nav.learning')}</h1>
          <p className="mt-1 text-sm text-gray-500">{t('learning.subtitle')}</p>
        </div>

        {isLoading ? (
          <PageSpinner />
        ) : error ? (
          <Alert variant="error">{apiErrorMessage(error, (key) => t(key)) || t('common.load_error')}</Alert>
        ) : paths.length === 0 ? (
          <Card className="border-dashed border-gray-300 bg-white text-center shadow-sm">
            <Route className="mx-auto h-10 w-10 text-primary-500" aria-hidden="true" />
            <h2 className="mt-4 text-lg font-semibold text-gray-950">{t('learning.empty_title')}</h2>
            <p className="mt-2 text-sm text-gray-500">{t('learning.empty_desc')}</p>
          </Card>
        ) : (
          <div className="grid gap-4 lg:grid-cols-2">
            {paths.map((path) => {
              const progress = Number(path.user_progress?.[0]?.progress ?? 0);
              const modulesCount = path.modules?.length ?? 0;
              const resourcesCount = (path.modules ?? []).reduce(
                (total: number, pathModule: { resources?: { id: string }[] }) => total + (pathModule.resources?.length ?? 0),
                0,
              );

              return (
                <Card key={path.id} className="border-0 shadow-sm">
                  <CardHeader>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                      <div>
                        <CardTitle>{localizedText(path.title, locale)}</CardTitle>
                        {path.level && <p className="mt-1 text-sm text-gray-500">{learningLevelLabel(path.level, t)}</p>}
                      </div>
                      <Badge variant={progress >= 100 ? 'success' : progress > 0 ? 'primary' : 'default'}>
                        {progress >= 100 ? t('common.completed') : progress > 0 ? t('learning.in_progress') : t('learning.not_started')}
                      </Badge>
                    </div>
                  </CardHeader>
                  {path.description && <p className="text-sm leading-6 text-gray-600">{localizedText(path.description, locale)}</p>}
                  <div className="mt-5 h-2 rounded-full bg-gray-100">
                    <div className="h-2 rounded-full bg-primary-600" style={{ width: `${Math.max(0, Math.min(100, progress))}%` }} />
                  </div>
                  <div className="mt-5 grid gap-3 text-sm text-gray-600 sm:grid-cols-3">
                    <Metric icon={BookOpen} label={t('learning.modules')} value={modulesCount} />
                    <Metric icon={Layers} label={t('learning.resources')} value={resourcesCount} />
                    <Metric icon={Clock} label={t('learning.hours')} value={path.estimated_hours ?? 0} />
                  </div>
                </Card>
              );
            })}
          </div>
        )}
      </div>
    </AppShell>
  );
}

function Metric({ icon: Icon, label, value }: { icon: typeof BookOpen; label: string; value: number }) {
  return (
    <div className="rounded-2xl bg-gray-50 p-3">
      <Icon className="h-4 w-4 text-primary-600" aria-hidden="true" />
      <p className="mt-2 text-xs text-gray-500">{label}</p>
      <p className="font-semibold text-gray-950">{value}</p>
    </div>
  );
}

function localizedText(value: string | Record<string, string>, locale: string): string {
  if (typeof value === 'string') return value;

  return value[locale] ?? value.es ?? value.en ?? value.pt ?? '';
}

function learningLevelLabel(level: string, t: ReturnType<typeof useTranslations>): string {
  const labels: Record<string, string> = {
    beginner: t('learning_levels.beginner'),
    intermediate: t('learning_levels.intermediate'),
    advanced: t('learning_levels.advanced'),
    expert: t('learning_levels.expert'),
  };

  return labels[level] ?? t('common.not_applicable');
}
