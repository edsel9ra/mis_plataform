'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect } from 'react';
import { useTranslations } from 'next-intl';
import { useAuth } from '@/components/auth/AuthProvider';
import { cn } from '@/lib/utils';
import {
  LayoutDashboard,
  Users,
  Calendar,
  MessageSquare,
  BookOpen,
  Award,
  User,
  LogOut,
  Shield,
  AlertTriangle,
  Sparkles,
} from 'lucide-react';

const baseNavItems = [
  { href: '/dashboard', label: 'nav.dashboard', icon: LayoutDashboard },
  { href: '/recommendations', label: 'nav.recommendations', icon: Sparkles },
  { href: '/mentors', label: 'nav.mentors', icon: Users },
  { href: '/sessions', label: 'nav.sessions', icon: Calendar },
  { href: '/messages', label: 'nav.messages', icon: MessageSquare },
  { href: '/learning-paths', label: 'nav.learning', icon: BookOpen },
  { href: '/certificates', label: 'nav.certificates', icon: Award },
  { href: '/profile', label: 'nav.profile', icon: User },
];

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  const t = useTranslations();
  const router = useRouter();
  const { user, isLoading, logout } = useAuth();
  const pathname = usePathname();

  const isAdmin = user && (user.role === 'super_admin' || user.role === 'admin');

  const navItems = isAdmin
    ? [...baseNavItems, { href: '/admin', label: 'nav.admin', icon: Shield }]
    : baseNavItems;

  useEffect(() => {
    if (isLoading) return;
    if (!user) {
      router.replace('/login');
    }
  }, [user, isLoading, router]);

  return (
    <div className="flex min-h-screen">
      <aside key="sidebar" className="flex w-64 flex-col border-r bg-white">
        <div key="logo" className="p-4">
          <Link href="/dashboard" className="text-lg font-bold text-primary-600">
            {t('app.name')}
          </Link>
        </div>
        <nav key="nav" className="flex-1 px-2" aria-label="Main navigation">
          <ul className="space-y-1">
            {navItems.map(({ href, label, icon: Icon }) => (
              <li key={href}>
                <Link
                  href={href}
                  className={cn(
                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                    pathname === href || pathname.startsWith(href + '/')
                      ? 'bg-primary-50 text-primary-700'
                      : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
                  )}
                  aria-current={pathname === href ? 'page' : undefined}
                >
                  <Icon className="h-5 w-5" aria-hidden="true" />
                  {t(label)}
                </Link>
              </li>
            ))}
          </ul>
        </nav>
        <div key="user" className="border-t p-4">
          <div className="flex items-center justify-between">
            <span className="text-sm font-medium text-gray-600 truncate">{user?.name}</span>
            <button
              onClick={logout}
              className="flex items-center gap-1 text-sm text-red-600 hover:text-red-700"
              aria-label={t('nav.logout')}
            >
              <LogOut className="h-4 w-4" />
            </button>
          </div>
        </div>
      </aside>
      <main key="main" className="flex-1 overflow-auto bg-gray-50 p-8">
        {user && !user.personality_assessment?.completed_at && (
          <div key="banner" className="mb-6 flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <AlertTriangle className="h-5 w-5 shrink-0" aria-hidden="true" />
            <p>
              {t('personality.banner_text')}{' '}
              <Link href="/onboarding/test-personalidad" className="font-medium underline hover:text-amber-900">
                {t('personality.banner_link')}
              </Link>
            </p>
          </div>
        )}
        <div key="page">{children}</div>
      </main>
    </div>
  );
}
