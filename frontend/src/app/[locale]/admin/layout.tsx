'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, type ReactNode } from 'react';
import { useTranslations } from 'next-intl';
import { useAuth } from '@/components/auth/AuthProvider';
import { cn } from '@/lib/utils';
import {
  LayoutDashboard,
  Users,
  GraduationCap,
  Calendar,
  ClipboardCheck,
  LogOut,
  ArrowLeft,
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
  const router = useRouter();
  const { user, isLoading, logout } = useAuth();
  const pathname = usePathname();

  const isAdmin = user && (user.role === 'super_admin' || user.role === 'admin');

  useEffect(() => {
    if (isLoading) return;
    if (!user) {
      router.replace('/login');
    } else if (!isAdmin) {
      router.replace('/dashboard');
    }
  }, [user, isLoading, router, isAdmin]);

  if (isLoading || !user || !isAdmin) {
    return (
      <div className="flex h-screen items-center justify-center text-gray-500">
        {t('common.loading')}
      </div>
    );
  }

  return (
    <div className="flex min-h-screen">
      <aside className="flex w-64 flex-col border-r bg-gray-900">
        <div className="p-4">
          <Link href="/admin" className="text-lg font-bold text-white">
            MIS Admin
          </Link>
        </div>
        <nav className="flex-1 px-2" aria-label="Admin navigation">
          <ul className="space-y-1">
            {adminNavItems.map(({ href, label, icon: Icon }) => (
              <li key={href}>
                <Link
                  href={href}
                  className={cn(
                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                    pathname === href || pathname.startsWith(href + '/')
                      ? 'bg-gray-800 text-white'
                      : 'text-gray-400 hover:bg-gray-800 hover:text-white',
                  )}
                  aria-current={pathname === href ? 'page' : undefined}
                >
                  <Icon className="h-5 w-5 shrink-0" aria-hidden="true" />
                  {t(label)}
                </Link>
              </li>
            ))}
          </ul>
        </nav>
        <div className="border-t border-gray-700 p-4">
          <Link
            href="/dashboard"
            className="mb-3 flex items-center gap-2 text-sm text-gray-400 hover:text-white transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            {t('nav.dashboard')}
          </Link>
          <div className="flex items-center justify-between">
            <span className="truncate text-sm font-medium text-gray-300">{user?.name}</span>
            <button
              onClick={logout}
              className="flex items-center gap-1 text-sm text-red-400 hover:text-red-300"
              aria-label={t('nav.logout')}
            >
              <LogOut className="h-4 w-4" />
            </button>
          </div>
        </div>
      </aside>
      <main className="flex-1 overflow-auto bg-gray-50 p-8">
        {children}
      </main>
    </div>
  );
}
