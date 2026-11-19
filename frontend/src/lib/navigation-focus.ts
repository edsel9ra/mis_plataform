const NAVIGATION_FOCUS_KEY = 'mis:focus-after-navigation';

/**
 * Marks that the next route change should move focus to the page heading.
 * The marker lives in sessionStorage because the source page unmounts before
 * the destination heading exists.
 */
export function requestFocusAfterNavigation() {
  if (typeof window === 'undefined') return;
  try {
    window.sessionStorage.setItem(NAVIGATION_FOCUS_KEY, '1');
  } catch {
    // sessionStorage unavailable; focus management is skipped.
  }
}

/** Returns true once when a focus request was pending, consuming the marker. */
export function consumeNavigationFocus(): boolean {
  if (typeof window === 'undefined') return false;
  try {
    const pending = window.sessionStorage.getItem(NAVIGATION_FOCUS_KEY) === '1';
    if (pending) window.sessionStorage.removeItem(NAVIGATION_FOCUS_KEY);
    return pending;
  } catch {
    return false;
  }
}
