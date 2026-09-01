'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, type ReactNode } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { Activity, ClipboardList, LayoutDashboard, MessageSquare, ShieldCheck } from 'lucide-react';
import { useAuth } from '@/components/auth/AuthProvider';
import { PageSpinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { localePath } from '@/i18n/paths';

const workspaceNavigation = [
  { href: '/dashboard/mentor', label: 'mentor_workspace.navigation_overview', icon: LayoutDashboard },
  { href: '/dashboard/mentor/challenges', label: 'mentor_workspace.navigation_challenges', icon: ClipboardList },
  { href: '/dashboard/mentor/feedback', label: 'mentor_workspace.navigation_feedback', icon: MessageSquare },
];

export default function MentorWorkspaceLayout({ children }: { children: ReactNode }) {
  const t = useTranslations();
  const locale = useLocale();
  const pathname = usePathname();
  const router = useRouter();
  const { user, isLoading } = useAuth();

  useEffect(() => {
    if (!isLoading && user && user.role !== 'mentor') {
      router.replace(localePath('/dashboard', locale));
    }
  }, [isLoading, locale, router, user]);

  if (isLoading || !user || user.role !== 'mentor') return <PageSpinner />;

  return (
    <div className="mx-auto max-w-7xl space-y-6">
      <header className="overflow-hidden rounded-3xl bg-[radial-gradient(circle_at_top_left,#312e81_0,#0f172a_55%,#020617_100%)] p-5 text-white shadow-xl shadow-slate-200 sm:p-7">
        <div className="flex flex-col justify-between gap-5 md:flex-row md:items-end">
          <div>
            <div className="flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.18em] text-primary-200">
              <Activity className="h-4 w-4" aria-hidden="true" />
              {t('mentor_workspace.eyebrow')}
            </div>
            <h1 className="mt-2 text-2xl font-bold sm:text-3xl">{t('mentor_workspace.title')}</h1>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-300">{t('mentor_workspace.subtitle')}</p>
          </div>
          <div className="flex items-center gap-2 rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-sm text-slate-200 backdrop-blur">
            <ShieldCheck className="h-5 w-5 text-emerald-300" aria-hidden="true" />
            {t('mentor_workspace.private_workspace')}
          </div>
        </div>
      </header>

      <nav className="-mx-1 overflow-x-auto px-1" aria-label={t('mentor_workspace.navigation_label')}>
        <div className="flex min-w-max gap-2 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm">
          {workspaceNavigation.map(({ href, label, icon: Icon }) => {
            const localizedHref = localePath(href, locale);
            const active = href === '/dashboard/mentor' ? pathname === localizedHref : pathname === localizedHref || pathname.startsWith(`${localizedHref}/`);
            return (
              <Link
                key={href}
                href={localizedHref}
                className={cn(
                  'inline-flex min-h-11 items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                  active ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950',
                )}
                aria-current={active ? 'page' : undefined}
              >
                <Icon className="h-4 w-4" aria-hidden="true" />
                {t(label)}
              </Link>
            );
          })}
        </div>
      </nav>

      {children}
    </div>
  );
}
