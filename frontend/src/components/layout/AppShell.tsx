'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useState, type ReactNode } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { useAuth } from '@/components/auth/AuthProvider';
import { SessionVerificationFallback } from '@/components/auth/SessionVerificationFallback';
import { PageSpinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { localePath } from '@/i18n/paths';
import {
  AlertTriangle,
  Award,
  BookOpen,
  Brain,
  Calendar,
  ClipboardCheck,
  ChevronLeft,
  ChevronRight,
  LayoutDashboard,
  LogOut,
  Menu,
  MessageSquare,
  Shield,
  Sparkles,
  User,
  Users,
  X,
} from 'lucide-react';

const baseNavItems = [
  { href: '/dashboard', label: 'nav.dashboard', icon: LayoutDashboard },
  { href: '/recommendations', label: 'nav.recommendations', icon: Sparkles },
  { href: '/evaluations', label: 'nav.evaluations', icon: ClipboardCheck },
  { href: '/mentors', label: 'nav.mentors', icon: Users },
  { href: '/sessions', label: 'nav.sessions', icon: Calendar },
  { href: '/messages', label: 'nav.messages', icon: MessageSquare },
  { href: '/learning-paths', label: 'nav.learning', icon: BookOpen },
  { href: '/certificates', label: 'nav.certificates', icon: Award },
  { href: '/profile', label: 'nav.profile', icon: User },
];

export function AppShell({ children }: { children: ReactNode }) {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const { user, isLoading, authError, logout } = useAuth();
  const [isMobileNavOpen, setIsMobileNavOpen] = useState(false);
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(false);

  const isAdmin = user && (user.role === 'super_admin' || user.role === 'admin');
  const navItems = isAdmin
    ? [...baseNavItems, { href: '/admin', label: 'nav.admin', icon: Shield }]
    : user?.role === 'mentor'
      ? baseNavItems.map((item) => item.href === '/dashboard'
        ? { ...item, href: '/dashboard/mentor', label: 'nav.assigned_users' }
        : item)
      : baseNavItems;
  const fullName = [user?.name, user?.last_name].filter(Boolean).join(' ') || user?.email || 'MIS';
  const sidebarToggleLabel = t(isSidebarCollapsed ? 'common.show_navigation' : 'common.hide_navigation');

  useEffect(() => {
    if (isLoading) return;
    if (!user && !authError) {
      router.replace(localePath('/login', locale));
    }
  }, [authError, isLoading, locale, router, user]);

  useEffect(() => {
    setIsMobileNavOpen(false);
  }, [pathname]);

  if (authError) {
    return <SessionVerificationFallback />;
  }

  if (isLoading || !user) {
    return <PageSpinner />;
  }

  return (
    <div className="min-h-screen bg-slate-50 lg:flex">
      <a
        href="#app-content"
        className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-slate-950 focus:shadow-lg"
      >
        {t('common.skip_to_content')}
      </a>

      <header className="sticky top-0 z-40 flex items-center justify-between border-b border-slate-800 bg-slate-950 px-4 py-3 text-white shadow-lg shadow-slate-950/10 lg:hidden">
        <Link href={localePath('/dashboard', locale)} className="flex items-center gap-2 font-bold">
          <Brain className="h-6 w-6 text-primary-400" aria-hidden="true" />
          MIS
        </Link>
        <button
          type="button"
          onClick={() => setIsMobileNavOpen(true)}
          className="rounded-lg p-2 text-slate-200 transition hover:bg-slate-800 focus-visible:ring-white"
          aria-label={t('common.open_menu')}
          aria-expanded={isMobileNavOpen}
          aria-controls="app-sidebar"
        >
          <Menu className="h-6 w-6" aria-hidden="true" />
        </button>
      </header>

      <button
        type="button"
        className={cn(
          'fixed inset-0 z-40 bg-slate-950/70 backdrop-blur-sm transition-opacity duration-300 ease-out lg:hidden',
          isMobileNavOpen ? 'opacity-100' : 'pointer-events-none opacity-0',
        )}
        onClick={() => setIsMobileNavOpen(false)}
        aria-label={t('common.close')}
        aria-hidden={!isMobileNavOpen}
        tabIndex={isMobileNavOpen ? 0 : -1}
      />

      <aside
        id="app-sidebar"
        className={cn(
          'fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-white/10 bg-slate-950 text-white shadow-2xl transition-[transform,width] duration-300 ease-out',
          'lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:translate-x-0 lg:shadow-none',
          isSidebarCollapsed ? 'lg:w-20' : 'lg:w-64',
          isMobileNavOpen ? 'translate-x-0' : '-translate-x-full',
        )}
        aria-label={t('nav.dashboard')}
      >
        <div className={cn('border-b border-white/10 p-5 transition-[padding] duration-300', isSidebarCollapsed && 'lg:p-4')}>
          <div className={cn('flex items-start justify-between gap-3', isSidebarCollapsed && 'lg:flex-col lg:items-center lg:justify-center')}>
            <Link
              href={localePath('/dashboard', locale)}
              className={cn('flex min-w-0 items-center gap-3 transition-all duration-300', isSidebarCollapsed && 'lg:justify-center')}
              aria-label={t('nav.dashboard')}
            >
              <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary-600 shadow-lg shadow-primary-950/40">
                <Brain className="h-6 w-6 text-white" aria-hidden="true" />
              </span>
              <span className={cn('min-w-0 transition-opacity duration-200', isSidebarCollapsed && 'lg:hidden')}>
                <span className="block text-base font-bold leading-tight">MIS</span>
                <span className="block text-xs text-slate-400">Mentoria sistemica</span>
              </span>
            </Link>
            <button
              type="button"
              className="hidden rounded-lg p-2 text-slate-400 transition hover:bg-white/10 hover:text-white lg:inline-flex"
              onClick={() => setIsSidebarCollapsed((collapsed) => !collapsed)}
              aria-label={sidebarToggleLabel}
              aria-expanded={!isSidebarCollapsed}
              aria-controls="app-sidebar"
              title={sidebarToggleLabel}
            >
              {isSidebarCollapsed ? (
                <ChevronRight className="h-5 w-5" aria-hidden="true" />
              ) : (
                <ChevronLeft className="h-5 w-5" aria-hidden="true" />
              )}
            </button>
            <button
              type="button"
              className="rounded-lg p-2 text-slate-400 transition hover:bg-white/10 hover:text-white lg:hidden"
              onClick={() => setIsMobileNavOpen(false)}
              aria-label={t('common.close')}
            >
              <X className="h-5 w-5" aria-hidden="true" />
            </button>
          </div>

          <div
            className={cn(
              'mt-6 max-h-40 overflow-hidden rounded-2xl border border-primary-400/20 bg-primary-500/10 p-4 transition-all duration-300 ease-out',
              isSidebarCollapsed && 'lg:mt-0 lg:max-h-0 lg:border-0 lg:p-0 lg:opacity-0',
            )}
          >
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-primary-200">
              {t('dashboard.ai_badge')}
            </p>
            <p className="mt-2 text-sm text-slate-300">{t('dashboard.shell_hint')}</p>
          </div>
        </div>

        <nav className={cn('flex-1 overflow-y-auto p-3 transition-[padding] duration-300', isSidebarCollapsed && 'lg:px-2')} aria-label={t('nav.main')}>
          <ul className="space-y-1">
            {navItems.map(({ href, label, icon: Icon }) => {
              const localizedHref = localePath(href, locale);
              const isActive = pathname === localizedHref || pathname.startsWith(localizedHref + '/');

              return (
                <li key={href}>
                  <Link
                    href={localizedHref}
                    className={cn(
                      'group flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200',
                      isActive
                        ? 'bg-white text-slate-950 shadow-lg shadow-slate-950/20'
                        : 'text-slate-300 hover:bg-white/10 hover:text-white',
                      isSidebarCollapsed && 'lg:justify-center lg:px-2',
                    )}
                    aria-current={isActive ? 'page' : undefined}
                    title={isSidebarCollapsed ? t(label) : undefined}
                  >
                    <Icon className="h-5 w-5 shrink-0 transition-transform duration-200 group-hover:scale-105" aria-hidden="true" />
                    <span className={cn('truncate transition-opacity duration-200', isSidebarCollapsed && 'lg:hidden')}>
                      {t(label)}
                    </span>
                  </Link>
                </li>
              );
            })}
          </ul>
        </nav>

        <div className={cn('border-t border-white/10 p-4 transition-[padding] duration-300', isSidebarCollapsed && 'lg:p-3')}>
          <div className={cn('flex items-center gap-3 rounded-2xl bg-white/5 p-3 transition-all duration-300', isSidebarCollapsed && 'lg:flex-col lg:justify-center lg:gap-2 lg:px-2')}>
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary-500 to-violet-500 text-sm font-bold">
              {fullName.charAt(0)}
            </div>
            <div className={cn('min-w-0 flex-1 transition-opacity duration-200', isSidebarCollapsed && 'lg:hidden')}>
              <span className="block truncate text-sm font-medium text-white">{fullName}</span>
              <span className="block truncate text-xs text-slate-400">{user.email}</span>
            </div>
            <button
              type="button"
              onClick={() => void logout().catch(() => {})}
              className="rounded-lg p-2 text-red-300 transition hover:bg-red-950/60 hover:text-red-200"
              aria-label={t('nav.logout')}
            >
              <LogOut className="h-4 w-4" aria-hidden="true" />
            </button>
          </div>
        </div>
      </aside>

      <main id="app-content" className="min-w-0 flex-1 overflow-auto bg-slate-50 p-4 transition-[padding] duration-300 sm:p-6 lg:p-8">
        {user.role !== 'mentor' && !user.personality_assessment?.completed_at && (
          <div className="mb-6 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 shadow-sm">
            <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
            <p>
              {t('personality.banner_text')}{' '}
              <Link href={localePath('/onboarding/test-personalidad', locale)} className="font-semibold underline hover:text-amber-950">
                {t('personality.banner_link')}
              </Link>
            </p>
          </div>
        )}
        {children}
      </main>
    </div>
  );
}
