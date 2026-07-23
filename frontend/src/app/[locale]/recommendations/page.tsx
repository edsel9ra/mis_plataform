'use client';

import { useQuery } from '@tanstack/react-query';
import { useTranslations } from 'next-intl';
import Link from 'next/link';
import { api } from '@/lib/api';
import { RequireAuth } from '@/components/auth/RequireAuth';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PageSpinner } from '@/components/ui/spinner';
import {
  Users,
  Calendar,
  ClipboardCheck,
  AlertTriangle,
  BarChart3,
  Star,
  Brain,
} from 'lucide-react';

interface Recommendations {
  recommended_mentors: any[];
  recommended_sessions: any[];
  recommended_evaluations: any[];
  user_ocean: Record<string, number> | null;
}

const OCEAN_NAMES: Record<string, string> = {
  O: 'Apertura',
  C: 'Responsabilidad',
  E: 'Extraversión',
  A: 'Amabilidad',
  N: 'Neuroticismo',
};

const OCEAN_COLORS: Record<string, string> = {
  O: 'bg-violet-500',
  C: 'bg-blue-500',
  E: 'bg-emerald-500',
  A: 'bg-amber-500',
  N: 'bg-rose-500',
};

export default function RecommendationsPage() {
  const t = useTranslations();

  const { data, isLoading, error } = useQuery<Recommendations>({
    queryKey: ['personality-recommendations'],
    queryFn: () => api.get<Recommendations>('/personality/recommendations'),
    retry: 1,
  });

  if (isLoading) return <PageSpinner />;

  if (error || !data) {
    return (
      <RequireAuth>
        <div className="flex flex-col items-center justify-center py-20 text-center">
          <AlertTriangle className="h-12 w-12 text-amber-500" />
          <h2 className="mt-4 text-xl font-bold text-gray-900">Completa tu test de personalidad</h2>
          <p className="mt-2 text-gray-500">Necesitas completar el test para recibir recomendaciones.</p>
          <Link href="/onboarding/test-personalidad">
            <Button className="mt-6">Realizar Test</Button>
          </Link>
        </div>
      </RequireAuth>
    );
  }

  const hasData = (arr: any[]) => arr && arr.length > 0;

  return (
    <RequireAuth>
      <div>
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">Recomendaciones Personalizadas</h1>
        <p className="mt-1 text-gray-500">
          Basadas en los resultados de tu test de personalidad Big Five
        </p>
      </div>

      {data.user_ocean && (
        <Card className="mb-8">
          <CardHeader>
            <div className="flex items-center gap-3">
              <Brain className="h-6 w-6 text-primary-600" />
              <CardTitle>Tu perfil OCEAN</CardTitle>
            </div>
          </CardHeader>
          <div className="grid gap-4 sm:grid-cols-5">
            {Object.entries(data.user_ocean).map(([key, val]) => (
              <div key={key} className="text-center">
                <span className="text-lg font-bold text-gray-800">{key}</span>
                <p className="text-xs text-gray-500">{OCEAN_NAMES[key] || key}</p>
                <div className="mt-2 h-2 rounded-full bg-gray-100">
                  <div
                    className={`h-2 rounded-full ${OCEAN_COLORS[key] || 'bg-gray-400'}`}
                    style={{ width: `${Math.min(100, val)}%` }}
                  />
                </div>
                <span className="mt-1 text-sm font-semibold text-gray-700">{Math.round(val)}</span>
              </div>
            ))}
          </div>
        </Card>
      )}

      <div className="space-y-8">
        {/* Recommended Mentors */}
        <section>
          <div className="mb-4 flex items-center gap-2">
            <Users className="h-5 w-5 text-primary-600" />
            <h2 className="text-xl font-bold text-gray-900">Mentores Recomendados</h2>
          </div>

          {!hasData(data.recommended_mentors) ? (
            <p className="text-sm text-gray-400">
              No hay mentores disponibles. Completa tu test para obtener mejores coincidencias.
            </p>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {data.recommended_mentors.map((mentor: any) => (
                <Card key={mentor.id}>
                  <CardHeader>
                    <div className="flex items-start justify-between">
                      <div>
                        <CardTitle className="text-base">
                          {mentor.name} {mentor.last_name ?? ''}
                        </CardTitle>
                        <p className="text-sm text-gray-500">{mentor.email}</p>
                      </div>
                      {mentor.compatibility_score && (
                        <div className="flex items-center gap-1 rounded-full bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700">
                          <Star className="h-3 w-3" />
                          {mentor.compatibility_score}%
                        </div>
                      )}
                    </div>
                  </CardHeader>
                  <div className="space-y-2">
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-500">Mentorías activas</span>
                      <span className="font-medium">{mentor.active_mentees_count ?? 0}</span>
                    </div>
                    {mentor.personality_assessment?.results?.factors && (
                      <div className="flex flex-wrap gap-1">
                        {Object.entries(mentor.personality_assessment.results.factors).map(([k, v]: [string, any]) => (
                          <span key={k} className="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">
                            {k}: {Math.round(v)}
                          </span>
                        ))}
                      </div>
                    )}
                    <Link href={`/mentors/${mentor.id}`}>
                      <Button variant="outline" size="sm" className="w-full">Ver perfil</Button>
                    </Link>
                  </div>
                </Card>
              ))}
            </div>
          )}
        </section>

        {/* Recommended Sessions */}
        <section>
          <div className="mb-4 flex items-center gap-2">
            <Calendar className="h-5 w-5 text-primary-600" />
            <h2 className="text-xl font-bold text-gray-900">Sesiones Próximas</h2>
          </div>

          {!hasData(data.recommended_sessions) ? (
            <p className="text-sm text-gray-400">
              No tienes sesiones programadas. Solicita una mentoría para comenzar.
            </p>
          ) : (
            <div className="space-y-3">
              {data.recommended_sessions.map((session: any) => (
                <Card key={session.id}>
                  <div className="flex items-center justify-between">
                    <div>
                      <CardTitle className="text-base">{session.title}</CardTitle>
                      <p className="text-sm text-gray-500">
                        {session.relationship?.mentor?.name} — {new Date(session.scheduled_at).toLocaleDateString()}
                      </p>
                    </div>
                    <Badge variant={
                      session.status === 'scheduled' ? 'primary' :
                      session.status === 'in_progress' ? 'warning' : 'default'
                    }>
                      {session.status}
                    </Badge>
                  </div>
                </Card>
              ))}
            </div>
          )}

          {!hasData(data.recommended_sessions) && hasData(data.recommended_mentors) && (
            <Link href="/mentors">
              <Button variant="outline" className="mt-3">Explorar mentores</Button>
            </Link>
          )}
        </section>

        {/* Recommended Evaluations */}
        <section>
          <div className="mb-4 flex items-center gap-2">
            <ClipboardCheck className="h-5 w-5 text-primary-600" />
            <h2 className="text-xl font-bold text-gray-900">Evaluaciones Recomendadas</h2>
          </div>

          {!hasData(data.recommended_evaluations) ? (
            <p className="text-sm text-gray-400">
              No hay evaluaciones recomendadas disponibles en este momento.
            </p>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2">
              {data.recommended_evaluations.map((evalItem: any) => (
                <Card key={evalItem.id}>
                  <CardHeader>
                    <div className="flex items-start gap-3">
                      <BarChart3 className="mt-1 h-5 w-5 shrink-0 text-primary-600" />
                      <div>
                        <CardTitle className="text-base">{evalItem.title}</CardTitle>
                        <p className="mt-1 text-sm text-gray-500">{evalItem.description}</p>
                        <p className="mt-1 text-xs text-primary-600 italic">{evalItem.reason}</p>
                      </div>
                    </div>
                  </CardHeader>
                </Card>
              ))}
            </div>
          )}
        </section>
      </div>
      </div>
    </RequireAuth>
  );
}
