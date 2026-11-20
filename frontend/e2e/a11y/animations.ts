import type { Page } from '@playwright/test';

export const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

/**
 * Finite entrance animations (fade-in, slide-up, …) blend colors while
 * running; audit the settled state. Infinite loops (orbs, drift) are
 * allowed to keep running.
 */
export async function waitForFiniteAnimations(page: Page): Promise<void> {
  await page.waitForFunction(() => Array.from(document.getAnimations())
    .every((animation) => animation.playState === 'finished'
      || animation.playState === 'idle'
      || (animation instanceof CSSAnimation
        && animation.effect?.getComputedTiming().iterations === Infinity)));
}
