'use client';

import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { localePath } from '@/i18n/paths';
import { ThinkingOrb } from '@/components/ui/thinking-orb';
import {
  ArrowRight,
  Brain,
  CalendarCheck,
  CheckCircle2,
  GraduationCap,
  LineChart,
  ShieldCheck,
  Sparkles,
  Users,
} from 'lucide-react';

const clientTypes = ['personal', 'familiar', 'grupal', 'empresa'] as const;

const clientTypeStyles = {
  personal: 'from-blue-500 to-cyan-500',
  familiar: 'from-emerald-500 to-teal-500',
  grupal: 'from-violet-500 to-purple-500',
  empresa: 'from-amber-500 to-orange-500',
};

export default function HomePage() {
  const t = useTranslations();
  const locale = useLocale();

  const metrics = [
    { label: 'Perfil OCEAN', value: 'Big Five' },
    { label: 'Matching', value: 'IA' },
    { label: 'Rutas', value: '5+' },
  ];

  const capabilities = [
    {
      title: 'Diagnostico neuroconductual',
      description: 'Activa un perfil OCEAN y evaluaciones complementarias para priorizar necesidades reales.',
      icon: Brain,
    },
    {
      title: 'Mentores compatibles',
      description: 'Relaciona usuarios, familias, cohortes y empresas con mentores segun contexto y objetivos.',
      icon: Users,
    },
    {
      title: 'Seguimiento accionable',
      description: 'Sesiones, progreso, rutas de aprendizaje y recomendaciones conviven en un mismo flujo.',
      icon: LineChart,
    },
  ];

  const steps = [
    'Crea tu perfil y selecciona el tipo de mentoría.',
    'Completa Big Five para activar recomendaciones.',
    'Agenda sesiones y avanza por rutas personalizadas.',
  ];

  return (
    <div className="min-h-screen bg-slate-50 text-slate-950">
      <header className="sticky top-0 z-40 border-b border-white/10 bg-slate-950/95 text-white shadow-lg shadow-slate-950/10 backdrop-blur">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
          <Link href={localePath('/', locale)} className="flex items-center gap-3 font-bold">
            <span className="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary-600 shadow-lg shadow-primary-950/40">
              <Brain className="h-5 w-5" aria-hidden="true" />
            </span>
            <span className="hidden sm:block">MIS</span>
          </Link>
          <nav className="flex items-center gap-2 sm:gap-4" aria-label={t('nav.main')}>
            <Link href={localePath('/mentors', locale)} className="hidden text-sm font-medium text-slate-300 transition hover:text-white sm:inline-flex">
              {t('nav.mentors')}
            </Link>
            <Link href={localePath('/login', locale)} className="rounded-lg px-3 py-2 text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white">
              {t('nav.login')}
            </Link>
            <Link
              href={localePath('/register', locale)}
              className="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-primary-50"
            >
              {t('nav.register')}
            </Link>
          </nav>
        </div>
      </header>

      <main>
        <section className="relative overflow-hidden bg-[radial-gradient(circle_at_top_left,#1d4ed8_0,#111827_42%,#020617_100%)] px-4 py-20 text-white sm:px-6 lg:px-8 lg:py-28">
          <div className="absolute -right-28 -top-28 h-96 w-96 rounded-full bg-primary-500/30 blur-3xl" aria-hidden="true" />
          <div className="absolute bottom-0 left-1/2 h-72 w-[42rem] -translate-x-1/2 rounded-full bg-violet-500/10 blur-3xl" aria-hidden="true" />

          <div className="relative z-10 mx-auto grid max-w-7xl gap-12 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
            <div>
              <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-primary-100">
                <Sparkles className="h-3.5 w-3.5" aria-hidden="true" />
                {t('dashboard.ai_badge')}
              </div>
              <h1 className="mt-6 max-w-4xl text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                Mentoría integral con perfil OCEAN, IA y seguimiento real.
              </h1>
              <p className="mt-6 max-w-2xl text-lg leading-8 text-slate-300">
                MIS conecta diagnóstico, mentores, sesiones y rutas de aprendizaje para convertir datos neuroconductuales en acciones concretas.
              </p>
              <div className="mt-10 flex flex-col gap-3 sm:flex-row">
                <Link
                  href={localePath('/register', locale)}
                  className="inline-flex items-center justify-center rounded-xl bg-primary-500 px-6 py-3 text-base font-semibold text-white shadow-lg shadow-primary-950/30 transition hover:-translate-y-0.5 hover:bg-primary-400"
                >
                  Comenzar ahora
                  <ArrowRight className="ml-2 h-4 w-4" aria-hidden="true" />
                </Link>
                <Link
                  href={localePath('/mentors', locale)}
                  className="inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/10 px-6 py-3 text-base font-semibold text-white backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/15"
                >
                  Explorar mentores
                </Link>
              </div>
              <div className="mt-10 grid max-w-xl grid-cols-3 gap-3">
                {metrics.map((metric) => (
                  <div key={metric.label} className="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                    <p className="text-2xl font-bold">{metric.value}</p>
                    <p className="mt-1 text-xs text-slate-300">{metric.label}</p>
                  </div>
                ))}
              </div>
            </div>

            <div className="relative mx-auto w-full max-w-xl">
              <div className="absolute -left-10 top-10 h-28 w-28 rounded-full bg-primary-400/20 blur-2xl" aria-hidden="true" />
              <div className="rounded-[2rem] border border-white/10 bg-white/10 p-4 shadow-2xl shadow-slate-950/40 backdrop-blur-xl">
                <div className="rounded-[1.5rem] bg-slate-950/80 p-5 ring-1 ring-white/10">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm text-slate-400">Vista inteligente</p>
                      <h2 className="mt-1 text-xl font-bold">Perfil activo</h2>
                    </div>
                    <ThinkingOrb size="lg" tone="inverse" decorative />
                  </div>
                  <div className="mt-6 grid gap-3">
                    {[
                      { label: 'Apertura', value: 82, color: 'bg-violet-400' },
                      { label: 'Responsabilidad', value: 76, color: 'bg-blue-400' },
                      { label: 'Amabilidad', value: 88, color: 'bg-emerald-400' },
                    ].map((trait) => (
                      <div key={trait.label} className="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <div className="flex items-center justify-between text-sm">
                          <span className="text-slate-300">{trait.label}</span>
                          <span className="font-semibold text-white">{trait.value}%</span>
                        </div>
                        <div className="mt-3 h-2 rounded-full bg-white/10">
                          <div className={`h-2 rounded-full ${trait.color}`} style={{ width: `${trait.value}%` }} />
                        </div>
                      </div>
                    ))}
                  </div>
                  <div className="mt-5 grid gap-3 sm:grid-cols-2">
                    <div className="rounded-2xl bg-primary-500/15 p-4 text-primary-100 ring-1 ring-primary-300/20">
                      <CalendarCheck className="h-5 w-5" aria-hidden="true" />
                      <p className="mt-3 text-sm font-semibold">Sesión recomendada</p>
                    </div>
                    <div className="rounded-2xl bg-violet-500/15 p-4 text-violet-100 ring-1 ring-violet-300/20">
                      <GraduationCap className="h-5 w-5" aria-hidden="true" />
                      <p className="mt-3 text-sm font-semibold">Ruta sugerida</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-20 sm:px-6 lg:px-8">
          <div className="mx-auto max-w-7xl">
            <div className="max-w-2xl">
              <p className="text-sm font-semibold uppercase tracking-[0.18em] text-primary-600">Sistema MIS</p>
              <h2 className="mt-3 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">Todo el ciclo de mentoría en una sola plataforma.</h2>
            </div>
            <div className="mt-10 grid gap-6 md:grid-cols-3">
              {capabilities.map(({ title, description, icon: Icon }) => (
                <div key={title} className="group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-primary-200 hover:shadow-xl hover:shadow-slate-200/80">
                  <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 transition-transform duration-300 group-hover:scale-110">
                    <Icon className="h-6 w-6" aria-hidden="true" />
                  </span>
                  <h3 className="mt-5 text-lg font-bold text-slate-950">{title}</h3>
                  <p className="mt-3 text-sm leading-6 text-slate-600">{description}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section className="bg-white px-4 py-20 sm:px-6 lg:px-8">
          <div className="mx-auto max-w-7xl">
            <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
              <div>
                <p className="text-sm font-semibold uppercase tracking-[0.18em] text-primary-600">Elige tu contexto</p>
                <h2 className="mt-3 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">{t('client_types.select_title')}</h2>
              </div>
              <p className="max-w-xl text-sm leading-6 text-slate-600">Cada tipo activa rutas, sesiones y evaluaciones ajustadas al sistema que quieres desarrollar.</p>
            </div>
            <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
              {clientTypes.map((type) => (
                <Link
                  key={type}
                  href={localePath(`/register?type=${type}`, locale)}
                  className="group rounded-3xl border border-slate-200 bg-slate-50 p-6 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:bg-white hover:shadow-xl hover:shadow-slate-200/80"
                >
                  <span className={`flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br ${clientTypeStyles[type]} text-white shadow-lg shadow-slate-200`}>
                    <Users className="h-5 w-5" aria-hidden="true" />
                  </span>
                  <h3 className="mt-5 text-lg font-bold text-slate-950">{t(`client_types.${type}`)}</h3>
                  <p className="mt-2 text-sm leading-6 text-slate-600">{t(`client_types.${type}_desc`)}</p>
                  <span className="mt-5 inline-flex items-center text-sm font-semibold text-primary-600">
                    Configurar plan
                    <ArrowRight className="ml-1 h-4 w-4 transition-transform group-hover:translate-x-1" aria-hidden="true" />
                  </span>
                </Link>
              ))}
            </div>
          </div>
        </section>

        <section className="px-4 py-20 sm:px-6 lg:px-8">
          <div className="mx-auto grid max-w-7xl gap-10 rounded-[2rem] bg-slate-950 p-6 text-white shadow-xl shadow-slate-200 sm:p-8 lg:grid-cols-[0.9fr_1.1fr] lg:p-10">
            <div>
              <p className="text-sm font-semibold uppercase tracking-[0.18em] text-primary-200">Como funciona</p>
              <h2 className="mt-3 text-3xl font-bold tracking-tight">De evaluación a acción.</h2>
              <p className="mt-4 text-sm leading-6 text-slate-300">La plataforma evita diagnósticos aislados: convierte resultados en mentorías, sesiones y aprendizaje medible.</p>
            </div>
            <div className="grid gap-4">
              {steps.map((step, index) => (
                <div key={step} className="flex items-start gap-4 rounded-2xl border border-white/10 bg-white/5 p-4">
                  <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-500 text-sm font-bold">{index + 1}</span>
                  <div>
                    <p className="font-semibold">{step}</p>
                    <p className="mt-1 text-sm text-slate-400">Acompañado por recomendaciones y seguimiento dentro del dashboard.</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section className="px-4 pb-20 sm:px-6 lg:px-8">
          <div className="mx-auto flex max-w-7xl flex-col gap-6 rounded-[2rem] border border-primary-100 bg-primary-50 p-6 sm:p-8 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex items-start gap-4">
              <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white text-primary-600 shadow-sm">
                <ShieldCheck className="h-6 w-6" aria-hidden="true" />
              </span>
              <div>
                <h2 className="text-2xl font-bold text-slate-950">Comienza con un perfil seguro y accionable.</h2>
                <p className="mt-2 text-sm leading-6 text-slate-600">Sesión protegida, datos centralizados y experiencia preparada para usuarios, mentores y administradores.</p>
              </div>
            </div>
            <Link href={localePath('/register', locale)} className="inline-flex items-center justify-center rounded-xl bg-primary-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-primary-200 transition hover:bg-primary-700">
              Crear cuenta
              <CheckCircle2 className="ml-2 h-4 w-4" aria-hidden="true" />
            </Link>
          </div>
        </section>
      </main>
    </div>
  );
}
