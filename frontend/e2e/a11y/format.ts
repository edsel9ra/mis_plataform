interface AxeViolation {
  id: string;
  impact?: string | null;
  nodes: Array<{ target: unknown[] }>;
}

function flattenTarget(target: unknown[]): string {
  return target
    .flatMap((part) => (Array.isArray(part) ? part : [part]))
    .map(String)
    .join(' > ');
}

export function formatViolations(route: string, viewport: number, violations: AxeViolation[]): string {
  if (violations.length === 0) return '';

  const lines = violations.map((violation) => {
    const selectors = violation.nodes.map((node) => flattenTarget(node.target)).join(', ');
    return `- ${violation.id} (${violation.impact ?? 'n/a'}): ${selectors}`;
  });

  return `${route} @ ${viewport}px: ${violations.length} violation(s)\n${lines.join('\n')}`;
}
