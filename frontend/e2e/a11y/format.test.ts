import { describe, expect, it } from 'vitest';
import { formatViolations } from './format';

describe('formatViolations', () => {
  it('returns an empty string when there are no violations', () => {
    expect(formatViolations('/admin/users', 320, [])).toBe('');
  });

  it('formats one violation with two nodes', () => {
    const text = formatViolations('/admin/users', 1280, [
      {
        id: 'select-name',
        impact: 'critical',
        nodes: [
          { target: ['#role-filter'] },
          { target: ['#type-filter'] },
        ],
      },
    ]);

    expect(text).toBe(
      '/admin/users @ 1280px: 1 violation(s)\n'
      + '- select-name (critical): #role-filter, #type-filter',
    );
  });

  it('renders a null impact as n/a', () => {
    const text = formatViolations('/dashboard', 320, [
      { id: 'region', impact: null, nodes: [{ target: ['aside.secondary'] }] },
    ]);

    expect(text).toContain('- region (n/a): aside.secondary');
  });

  it('flattens nested targets joined with >', () => {
    const text = formatViolations('/dashboard', 1280, [
      { id: 'color-contrast', impact: 'serious', nodes: [{ target: [['#iframe', '#inner']] }] },
    ]);

    expect(text).toContain('- color-contrast (serious): #iframe > #inner');
  });
});
