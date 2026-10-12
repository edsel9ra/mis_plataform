'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useLocale, useTranslations } from 'next-intl';
import { AppShell } from '@/components/layout/AppShell';
import { useAuth } from '@/components/auth/AuthProvider';
import { api } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import { normalizePaginatedResponse, type PaginatedResponse, type RawPaginatedResponse } from '@/lib/pagination';
import { Badge } from '@/components/ui/badge';
import { Alert } from '@/components/ui/alert';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { PageSpinner } from '@/components/ui/spinner';
import { PaginationControls } from '@/components/ui/pagination';
import { Award, ShieldCheck } from 'lucide-react';

interface Certificate {
  id: string;
  title: string;
  description?: string | null;
  type: string;
  revoked: boolean;
  issued_at: string;
  expires_at?: string | null;
}

export default function CertificatesPage() {
  const t = useTranslations();
  const locale = useLocale();
  const { user } = useAuth();
  const [page, setPage] = useState(1);
  const perPage = 12;
  const { data, isLoading, isFetching, error } = useQuery<PaginatedResponse<Certificate>>({
    queryKey: ['certificates', { page, per_page: perPage }],
    queryFn: async ({ signal }) => {
      const response = await api.get<RawPaginatedResponse<Certificate>>('/certificates', {
        page,
        per_page: perPage,
      }, { signal });

      return normalizePaginatedResponse(response, { page, perPage });
    },
    enabled: !!user,
  });

  const certificates = data?.data ?? [];

  return (
    <AppShell>
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-950">{t('nav.certificates')}</h1>
          <p className="mt-1 text-sm text-gray-500">{t('certificates.subtitle')}</p>
        </div>

        {isLoading ? (
          <PageSpinner />
        ) : error ? (
          <Alert variant="error">{apiErrorMessage(error, (key) => t(key)) || t('common.load_error')}</Alert>
        ) : certificates.length === 0 ? (
          <Card className="border-dashed border-gray-300 bg-white text-center shadow-sm">
            <Award className="mx-auto h-10 w-10 text-primary-500" aria-hidden="true" />
            <h2 className="mt-4 text-lg font-semibold text-gray-950">{t('certificates.empty_title')}</h2>
            <p className="mt-2 text-sm text-gray-500">{t('certificates.empty_desc')}</p>
          </Card>
        ) : (
          <div className="grid gap-4 lg:grid-cols-2">
            {certificates.map((certificate) => (
              <Card key={certificate.id} className="border-0 shadow-sm">
                <CardHeader>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                      <CardTitle>{certificate.title}</CardTitle>
                      <p className="mt-1 text-sm text-gray-500">{formatDate(certificate.issued_at, locale)}</p>
                    </div>
                    <Badge variant={certificateStatus(certificate) === 'revoked' ? 'danger' : certificateStatus(certificate) === 'expired' ? 'warning' : 'success'}>
                      {certificateStatus(certificate) === 'revoked'
                        ? t('certificates.revoked')
                        : certificateStatus(certificate) === 'expired'
                          ? t('certificates.expired')
                          : t('certificates.valid')}
                    </Badge>
                  </div>
                </CardHeader>
                <div className="space-y-4 text-sm text-gray-600">
                  {certificate.description && <p className="leading-6">{certificate.description}</p>}
                  <div className="rounded-2xl bg-gray-50 p-4">
                    <div className="flex items-center gap-2 font-semibold text-gray-900">
                      <ShieldCheck className="h-4 w-4 text-primary-600" aria-hidden="true" />
                      {certificateTypeLabel(certificate.type, t)}
                    </div>
                    {certificate.expires_at && (
                      <p className="mt-2 text-xs text-gray-500">
                        {t('certificates.expires')}: {formatDate(certificate.expires_at, locale)}
                      </p>
                    )}
                  </div>
                </div>
              </Card>
            ))}
          </div>
        )}
        {data && (
          <div className="rounded-2xl border border-gray-200 bg-white shadow-sm">
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
    </AppShell>
  );
}

function certificateStatus(certificate: Certificate): 'valid' | 'revoked' | 'expired' {
  if (certificate.revoked) return 'revoked';
  if (certificate.expires_at && Date.parse(certificate.expires_at) < Date.now()) return 'expired';

  return 'valid';
}

function formatDate(value: string, locale: string): string {
  return new Date(value).toLocaleDateString(locale, { dateStyle: 'medium' });
}

function certificateTypeLabel(type: string, t: ReturnType<typeof useTranslations>): string {
  const labels: Record<string, string> = {
    completion: t('certificate_types.completion'),
    skill: t('certificate_types.skill'),
    mentorship_hours: t('certificate_types.mentorship_hours'),
  };

  return labels[type] ?? t('common.not_applicable');
}
