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
  Users,
} from 'lucide-react';

const clientTypes = ['personal', 'familiar', 'grupal', 'empresa'] as const;

const clientTypeStyles = {
  personal: 'bg-primary-700 text-paper-soft',
  familiar: 'bg-clay-500 text-paper-soft',
  grupal: 'bg-ocean-o text-paper-soft',
  empresa: 'bg-ocean-a text-paper-soft',
} as const;

const stagger = (index: number) => ({ animationDelay: `${80 + index * 110}ms` });

export default function HomePage() {
  const t = useTranslations();
  const locale = useLocale();

  const metrics = [
    { label: t('landing.metric_ocean'), value: t('landing.metric_ocean_value') },
    { label: t('landing.metric_matching'), value: t('landing.metric_matching_value') },
    { label: t('landing.metric_paths'), value: t('landing.metric_paths_value') },
  ];

  const capabilities = [
    {
      title: t('landing.diagnostic_title'),
      description: t('landing.diagnostic_description'),
      icon: Brain,
    },
    {
      title: t('landing.compatible_mentors_title'),
      description: t('landing.compatible_mentors_description'),
      icon: Users,
    },
    {
      title: t('landing.actionable_follow_up_title'),
      description: t('landing.actionable_follow_up_description'),
      icon: LineChart,
    },
  ];

  const steps = [
    t('landing.step_profile'),
    t('landing.step_personality'),
    t('landing.step_sessions'),
  ];

  const traits = [
    { label: t('personality.openness'), value: 82, bar: 'bg-ocean-o', dot: '#7c5cc4' },
    { label: t('personality.conscientiousness'), value: 76, bar: 'bg-ocean-c', dot: '#2c6555' },
    { label: t('personality.agreeableness'), value: 88, bar: 'bg-ocean-a', dot: '#c99a2e' },
  ];

  return (
    <div className="relative min-h-screen overflow-x-hidden bg-paper text-ink">
      <div className="grain-layer absolute inset-0 z-0" aria-hidden="true" />

      <div className="relative z-10">
        <header className="sticky top-0 z-40 border-b border-ink/10 bg-paper/85 backdrop-blur-md">
          <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <Link
              href={localePath('/', locale)}
              className="group flex items-center gap-3"
              aria-label={t('app.name')}
            >
              <span className="flex h-10 w-10 items-center justify-center rounded-none border border-primary-700 bg-primary-700 font-display text-xl font-semibold text-paper-soft transition-colors group-hover:bg-primary-800">
                M
              </span>
              <span className="font-display text-lg font-semibold tracking-tight">MIS</span>
            </Link>

            <nav
              className="flex items-center gap-2 sm:gap-5"
              aria-label={t('nav.main')}
            >
              <Link
                href={localePath('/mentors', locale)}
                className="hidden text-sm font-medium text-ink-soft transition hover:text-ink sm:inline-flex"
              >
                {t('nav.mentors')}
              </Link>
              <Link
                href={localePath('/login', locale)}
                className="rounded-sm px-3 py-2 text-sm font-medium text-ink-soft transition hover:bg-ink/5 hover:text-ink"
              >
                {t('nav.login')}
              </Link>
              <Link
                href={localePath('/register', locale)}
                className="rounded-sm bg-ink px-4 py-2 text-sm font-semibold text-paper transition hover:bg-ink-soft"
              >
                {t('nav.register')}
              </Link>
            </nav>
          </div>
        </header>

        <main>
          {/* ——— Héroe editorial ——— */}
          <section className="relative px-4 pb-16 pt-14 sm:px-6 lg:px-8 lg:pb-24 lg:pt-20">
            <div
              className="pointer-events-none absolute -right-32 -top-24 h-96 w-96 rounded-full bg-clay-200/40 blur-3xl animate-drift"
              aria-hidden="true"
            />
            <div
              className="pointer-events-none absolute -left-24 bottom-6 h-72 w-[36rem] rounded-full bg-primary-200/50 blur-3xl animate-drift"
              style={{ animationDelay: '-8s' }}
              aria-hidden="true"
            />

            <div className="relative mx-auto grid max-w-7xl gap-14 lg:grid-cols-12 lg:items-center">
              <div className="lg:col-span-7">
                <p
                  className="eyebrow flex items-center gap-3 text-primary-700 animate-reveal-up"
                  style={stagger(0)}
                >
                  <span className="inline-block h-px w-10 origin-left bg-primary-700 animate-line-grow" aria-hidden="true" />
                  {t('dashboard.ai_badge')}
                </p>

                <h1
                  className="mt-6 max-w-3xl font-display text-5xl font-semibold leading-[1.04] tracking-tight sm:text-6xl lg:text-7xl animate-reveal-up"
                  style={stagger(1)}
                >
                  {t('landing.hero_title')}
                </h1>

                <p
                  className="mt-7 max-w-xl text-lg leading-8 text-ink-soft animate-reveal-up"
                  style={stagger(2)}
                >
                  {t('landing.hero_description')}
                </p>

                <div
                  className="mt-10 flex flex-col gap-3 sm:flex-row animate-reveal-up"
                  style={stagger(3)}
                >
                  <Link
                    href={localePath('/register', locale)}
                    className="inline-flex items-center justify-center rounded-sm bg-primary-700 px-6 py-3 text-base font-semibold text-paper-soft shadow-[4px_4px_0_0_#193530] transition hover:-translate-y-0.5 hover:bg-primary-800"
                  >
                    {t('landing.start_now')}
                    <ArrowRight className="ml-2 h-4 w-4" aria-hidden="true" />
                  </Link>
                  <Link
                    href={localePath('/mentors', locale)}
                    className="inline-flex items-center justify-center rounded-sm border border-ink/25 px-6 py-3 text-base font-semibold text-ink transition hover:border-ink hover:bg-ink/5"
                  >
                    {t('landing.explore_mentors')}
                  </Link>
                </div>

                <dl
                  className="mt-12 grid max-w-xl grid-cols-3 divide-x divide-ink/15 animate-reveal-up"
                  style={stagger(4)}
                >
                  {metrics.map((metric) => (
                    <div key={metric.label} className="px-4 first:pl-0 last:pr-0">
                      <dd className="font-display text-xl font-semibold leading-none">
                        {metric.value}
                      </dd>
                      <dt className="eyebrow mt-2 text-ink-soft">{metric.label}</dt>
                    </div>
                  ))}
                </dl>
              </div>

              {/* ——— Dossier de perfil ——— */}
              <div
                className="relative mx-auto w-full max-w-md lg:col-span-5 animate-reveal-up"
                style={stagger(2)}
              >
                <div className="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-ocean-o/10 blur-2xl" aria-hidden="true" />
                <div className="absolute -left-8 bottom-10 h-28 w-28 rounded-full bg-clay-300/20 blur-2xl" aria-hidden="true" />

                <div className="relative border border-ink/15 bg-paper-soft p-6 shadow-[8px_8px_0_0_#e6dfd1] sm:p-7">
                  {/* registros de imprenta */}
                  <span className="absolute left-2 top-2 h-3 w-3 border-l border-t border-primary-700" aria-hidden="true" />
                  <span className="absolute right-2 top-2 h-3 w-3 border-r border-t border-primary-700" aria-hidden="true" />
                  <span className="absolute bottom-2 left-2 h-3 w-3 border-b border-l border-primary-700" aria-hidden="true" />
                  <span className="absolute bottom-2 right-2 h-3 w-3 border-b border-r border-primary-700" aria-hidden="true" />

                  <div className="flex items-center justify-between gap-3">
                    <p className="eyebrow text-primary-700">{t('landing.smart_view')}</p>
                    <ThinkingOrb size="lg" decorative />
                  </div>

                  <h2 className="mt-1 font-display text-2xl font-semibold tracking-tight">
                    {t('landing.active_profile')}
                  </h2>
                  <p className="eyebrow mt-1 text-ink-soft">
                    {t('landing.metric_ocean')} · {t('landing.metric_ocean_value')}
                  </p>

                  <div className="mt-6 space-y-4">
                    {traits.map((trait) => (
                      <div key={trait.label}>
                        <div className="flex items-baseline justify-between gap-3">
                          <span className="text-sm font-medium text-ink-soft">{trait.label}</span>
                          <span className="font-display text-lg font-semibold">{trait.value}%</span>
                        </div>
                        <div className="mt-2 h-px w-full bg-ink/15">
                          <div className="relative h-px" style={{ width: `${trait.value}%` }}>
                            <span className={`absolute inset-y-0 left-0 right-0 ${trait.bar}`} aria-hidden="true" />
                            <span
                              className="absolute -right-1 top-1/2 h-2.5 w-2.5 -translate-y-1/2 rounded-full border border-paper-soft"
                              style={{ backgroundColor: trait.dot }}
                              aria-hidden="true"
                            />
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>

                  <div className="mt-6 grid gap-3 sm:grid-cols-2">
                    <div className="border border-primary-700/25 bg-primary-700/10 p-4">
                      <span className="flex h-8 w-8 items-center justify-center bg-primary-700 text-paper-soft">
                        <CalendarCheck className="h-4 w-4" aria-hidden="true" />
                      </span>
                      <p className="mt-3 text-sm font-semibold text-primary-900">
                        {t('landing.recommended_session')}
                      </p>
                    </div>
                    <div className="border border-clay-500/25 bg-clay-500/10 p-4">
                      <span className="flex h-8 w-8 items-center justify-center bg-clay-500 text-paper-soft">
                        <GraduationCap className="h-4 w-4" aria-hidden="true" />
                      </span>
                      <p className="mt-3 text-sm font-semibold text-clay-900">
                        {t('landing.suggested_path')}
                      </p>
                    </div>
                  </div>
                </div>

                <div className="mt-4 flex translate-x-8 items-center justify-end">
                  <span className="inline-block rotate-2 border border-ink/30 bg-paper px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.22em] text-ink-soft shadow-sm">
                    {t('landing.metric_ocean_value')} · IPIP-NEO-120
                  </span>
                </div>
              </div>
            </div>
          </section>

          {/* ——— El ciclo: capacidades ——— */}
          <section className="border-y border-ink/10 bg-white/60 px-4 py-20 sm:px-6 lg:px-8 lg:py-24">
            <div className="mx-auto max-w-7xl">
              <div className="max-w-2xl">
                <p className="eyebrow text-primary-700">{t('landing.system_label')}</p>
                <h2 className="mt-3 font-display text-4xl font-semibold tracking-tight sm:text-5xl">
                  {t('landing.cycle_title')}
                </h2>
              </div>

              <div className="mt-12 grid gap-px overflow-hidden border border-ink/10 bg-ink/10 md:grid-cols-3">
                {capabilities.map(({ title, description, icon: Icon }, index) => (
                  <article
                    key={title}
                    className="group relative bg-paper p-7 transition-colors duration-300 hover:bg-paper-soft sm:p-9"
                  >
                    <span className="font-display text-sm font-semibold text-ink-soft">
                      {String(index + 1).padStart(2, '0')}
                    </span>
                    <span className="mt-10 flex h-12 w-12 items-center justify-center rounded-full border border-ink/20 text-primary-700 transition-all duration-300 group-hover:border-primary-700 group-hover:bg-primary-700 group-hover:text-paper-soft">
                      <Icon className="h-6 w-6" aria-hidden="true" />
                    </span>
                    <h3 className="mt-6 font-display text-2xl font-semibold tracking-tight">
                      {title}
                    </h3>
                    <p className="mt-3 text-sm leading-7 text-ink-soft">{description}</p>
                  </article>
                ))}
              </div>
            </div>
          </section>

          {/* ——— Contextos de mentoría ——— */}
          <section className="px-4 py-20 sm:px-6 lg:px-8 lg:py-24">
            <div className="mx-auto max-w-7xl">
              <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                  <p className="eyebrow text-primary-700">{t('landing.context_heading')}</p>
                  <h2 className="mt-3 max-w-xl font-display text-4xl font-semibold tracking-tight sm:text-5xl">
                    {t('client_types.select_title')}
                  </h2>
                </div>
                <p className="max-w-md text-sm leading-7 text-ink-soft">
                  {t('landing.context_description')}
                </p>
              </div>

              <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                {clientTypes.map((type, index) => (
                  <Link
                    key={type}
                    href={localePath(`/register/${type}`, locale)}
                    className="group flex flex-col border border-ink/15 bg-paper p-6 transition-all duration-300 hover:-translate-y-1 hover:border-ink hover:shadow-[6px_6px_0_0_#e6dfd1]"
                  >
                    <div className="flex items-start justify-between">
                      <span
                        className={`flex h-11 w-11 items-center justify-center font-display text-lg font-semibold ${clientTypeStyles[type]}`}
                      >
                        {String.fromCharCode(65 + index)}
                      </span>
                      <span
                        className="my-1 h-5 w-px bg-ink/20 transition-colors group-hover:bg-primary-700"
                        aria-hidden="true"
                      />
                    </div>
                    <h3 className="mt-8 font-display text-2xl font-semibold tracking-tight">
                      {t(`client_types.${type}`)}
                    </h3>
                    <p className="mt-2 flex-1 text-sm leading-6 text-ink-soft">
                      {t(`client_types.${type}_desc`)}
                    </p>
                    <span className="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-primary-700">
                      {t('landing.configure_plan')}
                      <ArrowRight
                        className="h-4 w-4 transition-transform group-hover:translate-x-1"
                        aria-hidden="true"
                      />
                    </span>
                  </Link>
                ))}
              </div>
            </div>
          </section>

          {/* ——— Cómo funciona ——— */}
          <section className="px-4 pb-20 sm:px-6 lg:px-8 lg:pb-24">
            <div className="mx-auto grid max-w-7xl gap-12 border border-ink bg-ink p-6 text-paper lg:grid-cols-[0.9fr_1.1fr] lg:p-12">
              <div>
                <p className="eyebrow text-clay-300">{t('landing.how_it_works')}</p>
                <h2 className="mt-4 font-display text-4xl font-semibold leading-[1.05] tracking-tight sm:text-5xl">
                  {t('landing.evaluation_to_action')}
                </h2>
                <p className="mt-6 text-sm leading-7 text-paper-soft/80">
                  {t('landing.how_description')}
                </p>
                <div className="mt-10 hidden h-px w-full origin-left bg-paper/20 animate-line-grow lg:block" aria-hidden="true" />
              </div>

              <ol className="divide-y divide-paper/15">
                {steps.map((step, index) => (
                  <li key={step} className="group flex items-start gap-6 py-5 first:pt-0 last:pb-0">
                    <span className="font-display text-3xl font-semibold text-clay-300">
                      {String(index + 1).padStart(2, '0')}
                    </span>
                    <div className="pt-1.5">
                      <p className="font-display text-xl font-medium tracking-tight">{step}</p>
                      <p className="mt-1.5 text-sm leading-6 text-paper-soft/70">
                        {t('landing.step_support')}
                      </p>
                    </div>
                  </li>
                ))}
              </ol>
            </div>
          </section>

          {/* ——— Seguridad ——— */}
          <section className="px-4 pb-24 sm:px-6 lg:px-8">
            <div className="mx-auto flex max-w-7xl flex-col gap-8 border border-ink/15 bg-paper-soft p-8 lg:flex-row lg:items-center lg:justify-between lg:p-10">
              <div className="flex items-start gap-5">
                <span className="flex h-12 w-12 shrink-0 items-center justify-center bg-ink text-paper">
                  <ShieldCheck className="h-6 w-6" aria-hidden="true" />
                </span>
                <div>
                  <h2 className="font-display text-3xl font-semibold tracking-tight">
                    {t('landing.secure_title')}
                  </h2>
                  <p className="mt-2 max-w-xl text-sm leading-7 text-ink-soft">
                    {t('landing.secure_description')}
                  </p>
                </div>
              </div>
              <Link
                href={localePath('/register', locale)}
                className="inline-flex shrink-0 items-center justify-center rounded-sm bg-primary-700 px-6 py-3 text-sm font-semibold text-paper-soft shadow-[4px_4px_0_0_#193530] transition hover:bg-primary-800"
              >
                {t('landing.create_account')}
                <CheckCircle2 className="ml-2 h-4 w-4" aria-hidden="true" />
              </Link>
            </div>
          </section>
        </main>

        <footer className="border-t border-ink/10 px-4 py-8 sm:px-6 lg:px-8">
          <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 sm:flex-row">
            <p className="flex items-center gap-2 font-display text-base font-semibold tracking-tight">
              <span className="flex h-6 w-6 items-center justify-center bg-primary-700 font-display text-xs text-paper-soft">
                M
              </span>
              MIS
            </p>
            <p className="eyebrow text-ink-soft">
              {t('landing.metric_ocean')} · {t('landing.metric_matching')} · {t('landing.metric_paths')}
            </p>
          </div>
        </footer>
      </div>
    </div>
  );
}