'use client';

import { useEffect } from 'react';
import { usePathname } from 'next/navigation';
import { consumeNavigationFocus } from '@/lib/navigation-focus';

const RETRY_BUDGET_MS = 1000;

/**
 * Moves focus to the first <h1> inside <main> after a route change when a
 * source page requested it via requestFocusAfterNavigation(). The heading is
 * made programmatically focusable (tabIndex={-1}) when needed, and we retry
 * with requestAnimationFrame for a short window because the heading may render
 * asynchronously.
 */
export function NavigationFocus() {
  const pathname = usePathname();

  useEffect(() => {
    if (!consumeNavigationFocus()) return;

    const start = performance.now();
    let frame = 0;

    const tryFocus = () => {
      const heading = document.querySelector('main h1');
      if (heading instanceof HTMLElement) {
        if (heading.tabIndex < 0) heading.tabIndex = -1;
        heading.focus();
        return;
      }
      if (performance.now() - start < RETRY_BUDGET_MS) {
        frame = requestAnimationFrame(tryFocus);
      }
    };

    frame = requestAnimationFrame(tryFocus);
    return () => cancelAnimationFrame(frame);
  }, [pathname]);

  return null;
}
