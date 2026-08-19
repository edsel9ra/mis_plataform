'use client';

import { useQuery } from '@tanstack/react-query';
import { useLocale, useTranslations } from 'next-intl';
import { AppShell } from '@/components/layout/AppShell';
import { useAuth } from '@/components/auth/AuthProvider';
import { api } from '@/lib/api';
import { Badge } from '@/components/ui/badge';
import { Alert } from '@/components/ui/alert';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { PageSpinner } from '@/components/ui/spinner';
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

interface PaginatedCertificates {
  data: Certificate[];
}

export default function CertificatesPage() {
  const t = useTranslations();
  const locale = useLocale();
  const { user } = useAuth();
  const { data, isLoading, error } = useQuery<PaginatedCertificates>({
    queryKey: ['certificates'],
    queryFn: ({ signal }) => api.get<PaginatedCertificates>('/certificates', undefined, { signal }),
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
          <Alert variant="error">{t('common.load_error')}</Alert>
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
                    <Badge variant={certificate.revoked ? 'danger' : 'success'}>
                      {certificate.revoked ? t('certificates.revoked') : t('certificates.valid')}
                    </Badge>
                  </div>
                </CardHeader>
                <div className="space-y-4 text-sm text-gray-600">
                  {certificate.description && <p className="leading-6">{certificate.description}</p>}
                  <div className="rounded-2xl bg-gray-50 p-4">
                    <div className="flex items-center gap-2 font-semibold text-gray-900">
                      <ShieldCheck className="h-4 w-4 text-primary-600" aria-hidden="true" />
                      {certificate.type}
                    </div>
                  </div>
                </div>
              </Card>
            ))}
          </div>
        )}
      </div>
    </AppShell>
  );
}

function formatDate(value: string, locale: string): string {
  return new Date(value).toLocaleDateString(locale, { dateStyle: 'medium' });
}
