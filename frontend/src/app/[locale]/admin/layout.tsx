'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useState, type ReactNode } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { useAuth } from '@/components/auth/AuthProvider';
import { SessionVerificationFallback } from '@/components/auth/SessionVerificationFallback';
import { localePath } from '@/i18n/paths';
import { cn } from '@/lib/utils';
import {
  LayoutDashboard,
  Users,
  GraduationCap,
  Calendar,
  ClipboardCheck,
  LogOut,
  ArrowLeft,
  ChevronLeft,
  ChevronRight,
  Menu,
  ShieldCheck,
  X,
} from 'lucide-react';

const adminNavItems = [
  { href: '/admin', label: 'admin.overview', icon: LayoutDashboard },
  { href: '/admin/users', label: 'admin.users', icon: Users },
  { href: '/admin/mentors', label: 'admin.mentors', icon: GraduationCap },
  { href: '/admin/sessions', label: 'admin.sessions', icon: Calendar },
  { href: '/admin/evaluations', label: 'admin.evaluations', icon: ClipboardCheck },
];

export default function AdminLayout({ children }: { children: ReactNode }) {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const { user, isLoading, authError, logout } = useAuth();
  const pathname = usePathname();
  const [isNavOpen, setIsNavOpen] = useState(false);
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(false);

  const isAdmin = user && (user.role === 'super_admin' || user.role === 'admin');
  const sidebarToggleLabel = t(isSidebarCollapsed ? 'common.show_navigation' : 'common.hide_navigation');

  useEffect(() => {
    if (isLoading) return;
    if (!user && !authError) {
      router.replace(localePath('/login', locale));
    } else if (user && !isAdmin) {
      router.replace(localePath('/dashboard', locale));
    }
  }, [user, isLoading, authError, router, isAdmin, locale]);

  useEffect(() => {
    setIsNavOpen(false);
  }, [pathname]);

  if (authError) {
    return <SessionVerificationFallback />;
  }

  if (isLoading || !user || !isAdmin) {
    return (
      <div className="flex h-screen items-center justify-center text-gray-500">
        {t('common.loading')}
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-100 lg:flex">
      <header className="sticky top-0 z-40 flex items-center justify-between border-b border-slate-800 bg-slate-950 px-4 py-3 text-white lg:hidden">
        <Link href={localePath('/admin', locale)} className="flex items-center gap-2 font-bold">
          <ShieldCheck className="h-5 w-5 text-primary-300" aria-hidden="true" />
          MIS Admin
        </Link>
        <button
          type="button"
          onClick={() => setIsNavOpen(true)}
          className="rounded-lg p-2 text-slate-200 transition hover:bg-white/10"
          aria-label={t('common.open_menu')}
          aria-expanded={isNavOpen}
          aria-controls="admin-sidebar"
        >
          <Menu className="h-6 w-6" aria-hidden="true" />
        </button>
      </header>

      <button
        type="button"
        className={cn(
          'fixed inset-0 z-40 bg-slate-950/70 backdrop-blur-sm transition-opacity duration-300 lg:hidden',
          isNavOpen ? 'opacity-100' : 'pointer-events-none opacity-0',
        )}
        onClick={() => setIsNavOpen(false)}
        aria-label={t('common.close')}
      />

      <aside
        id="admin-sidebar"
        className={cn(
          'fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-white/10 bg-slate-950 text-white shadow-2xl transition-[transform,width] duration-300 lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:translate-x-0 lg:shadow-none',
          isSidebarCollapsed ? 'lg:w-20' : 'lg:w-72',
          isNavOpen ? 'translate-x-0' : '-translate-x-full',
        )}
      >
        <div className={cn('border-b border-white/10 p-5 transition-[padding] duration-300', isSidebarCollapsed && 'lg:p-4')}>
          <div className={cn('flex items-start justify-between gap-3', isSidebarCollapsed && 'lg:flex-col lg:items-center lg:justify-center')}>
            <Link
              href={localePath('/admin', locale)}
              className={cn('flex min-w-0 items-center gap-3 transition-all duration-300', isSidebarCollapsed && 'lg:justify-center')}
              aria-label="MIS Admin"
            >
              <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary-600 shadow-lg shadow-primary-950/40">
                <ShieldCheck className="h-6 w-6 text-white" aria-hidden="true" />
              </span>
              <span className={cn('min-w-0 transition-opacity duration-200', isSidebarCollapsed && 'lg:hidden')}>
                <span className="block text-base font-bold leading-tight">MIS Admin</span>
                <span className="block text-xs text-slate-400">{user.role === 'super_admin' ? 'Super admin' : 'Admin'}</span>
              </span>
            </Link>
            <button
              type="button"
              className="hidden rounded-lg p-2 text-slate-400 transition hover:bg-white/10 hover:text-white lg:inline-flex"
              onClick={() => setIsSidebarCollapsed((collapsed) => !collapsed)}
              aria-label={sidebarToggleLabel}
              aria-expanded={!isSidebarCollapsed}
              aria-controls="admin-sidebar"
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
              onClick={() => setIsNavOpen(false)}
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
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-primary-200">Admin operativo</p>
            <p className="mt-2 text-sm text-slate-300">Usuarios, mentores, sesiones y evaluaciones configurables.</p>
          </div>
        </div>
        <nav className={cn('flex-1 overflow-y-auto p-3 transition-[padding] duration-300', isSidebarCollapsed && 'lg:px-2')} aria-label="Admin navigation">
          <ul className="space-y-1">
            {adminNavItems.map(({ href, label, icon: Icon }) => (
              <li key={href}>
                <Link
                  href={localePath(href, locale)}
                  className={cn(
                    'group flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200',
                    pathname === localePath(href, locale) || pathname.startsWith(localePath(href, locale) + '/')
                      ? 'bg-white text-slate-950 shadow-lg shadow-slate-950/20'
                      : 'text-slate-300 hover:bg-white/10 hover:text-white',
                    isSidebarCollapsed && 'lg:justify-center lg:px-2',
                  )}
                  aria-current={pathname === localePath(href, locale) ? 'page' : undefined}
                  title={isSidebarCollapsed ? t(label) : undefined}
                >
                  <Icon className="h-5 w-5 shrink-0 transition-transform duration-200 group-hover:scale-105" aria-hidden="true" />
                  <span className={cn('truncate transition-opacity duration-200', isSidebarCollapsed && 'lg:hidden')}>
                    {t(label)}
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        </nav>
        <div className={cn('border-t border-white/10 p-4 transition-[padding] duration-300', isSidebarCollapsed && 'lg:p-3')}>
          <Link
            href={localePath('/dashboard', locale)}
            className={cn(
              'mb-3 flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-slate-300 transition-all duration-200 hover:bg-white/10 hover:text-white',
              isSidebarCollapsed && 'lg:justify-center lg:px-2',
            )}
            title={isSidebarCollapsed ? t('nav.dashboard') : undefined}
          >
            <ArrowLeft className="h-4 w-4 shrink-0" />
            <span className={cn('truncate transition-opacity duration-200', isSidebarCollapsed && 'lg:hidden')}>
              {t('nav.dashboard')}
            </span>
          </Link>
          <div className={cn('flex items-center justify-between gap-3 rounded-2xl bg-white/5 p-3 transition-all duration-300', isSidebarCollapsed && 'lg:flex-col lg:justify-center lg:gap-2 lg:px-2')}>
            <div className={cn('min-w-0 transition-opacity duration-200', isSidebarCollapsed && 'lg:hidden')}>
              <span className="block truncate text-sm font-medium text-white">{user?.name}</span>
              <span className="block truncate text-xs text-slate-400">{user.email}</span>
            </div>
            <button
              onClick={() => void logout().catch(() => {})}
              className="rounded-lg p-2 text-red-300 transition hover:bg-red-950/60 hover:text-red-200"
              aria-label={t('nav.logout')}
            >
              <LogOut className="h-4 w-4" />
            </button>
          </div>
        </div>
      </aside>
      <main className="min-w-0 flex-1 overflow-auto p-4 sm:p-6 lg:p-8">
        {children}
      </main>
    </div>
  );
}
