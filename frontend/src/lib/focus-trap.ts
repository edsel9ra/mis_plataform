/** Element to move focus to so Tab/Shift+Tab wraps inside a container, or null to let the browser move focus. */
export function trapFocusTarget<T>(
  focusables: readonly T[],
  active: T | null,
  shiftKey: boolean,
): T | null {
  if (focusables.length === 0) return null;

  const index = active === null ? -1 : focusables.indexOf(active);
  if (index < 0) return shiftKey ? focusables[focusables.length - 1] : focusables[0];
  if (shiftKey && index === 0) return focusables[focusables.length - 1];
  if (!shiftKey && index === focusables.length - 1) return focusables[0];
  return null;
}
