import {
  Award,
  BookOpen,
  Calendar,
  CalendarClock,
  ClipboardCheck,
  LayoutDashboard,
  ListChecks,
  MessageSquare,
  Shield,
  Sparkles,
  TrendingUp,
  User,
  Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export interface NavItem {
  href: string;
  /** i18n key resolved by the shell. */
  label: string;
  icon: LucideIcon;
}

const baseNavItems: NavItem[] = [
  { href: '/dashboard', label: 'nav.dashboard', icon: LayoutDashboard },
  { href: '/dashboard/progreso', label: 'nav.progress', icon: TrendingUp },
  { href: '/recommendations', label: 'nav.recommendations', icon: Sparkles },
  { href: '/evaluations', label: 'nav.evaluations', icon: ClipboardCheck },
  { href: '/mentors', label: 'nav.mentors', icon: Users },
  { href: '/sessions', label: 'nav.sessions', icon: Calendar },
  { href: '/messages', label: 'nav.messages', icon: MessageSquare },
  { href: '/learning-paths', label: 'nav.learning', icon: BookOpen },
  { href: '/certificates', label: 'nav.certificates', icon: Award },
  { href: '/profile', label: 'nav.profile', icon: User },
];

const availabilityItem: NavItem = { href: '/availability', label: 'nav.availability', icon: CalendarClock };
const myChallengesItem: NavItem = { href: '/challenges', label: 'nav.my_challenges', icon: ListChecks };

export function buildNavItems(role: string | undefined, isAdmin: boolean): NavItem[] {
  if (isAdmin) {
    return [...baseNavItems, { href: '/admin', label: 'nav.admin', icon: Shield }];
  }

  if (role === 'mentor') {
    const mentorItems = baseNavItems.flatMap((item) => {
      const mapped = item.href === '/dashboard'
        ? { ...item, href: '/dashboard/mentor', label: 'nav.assigned_users' }
        : item.href === '/dashboard/progreso'
        ? { ...item, href: '/dashboard/mentor/progress' }
        : item;
      return item.href === '/sessions' ? [mapped, availabilityItem] : [mapped];
    });

    return [
      ...mentorItems,
      { href: '/dashboard/mentor/challenges', label: 'nav.mentor_challenges', icon: ListChecks },
      { href: '/dashboard/mentor/feedback', label: 'nav.mentor_feedback', icon: MessageSquare },
    ];
  }

  if (role === 'mentee') {
    return baseNavItems.flatMap((item) =>
      item.href === '/dashboard/progreso' ? [item, myChallengesItem] : [item],
    );
  }

  return baseNavItems;
}
