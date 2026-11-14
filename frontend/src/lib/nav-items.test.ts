import { describe, expect, it } from 'vitest';

import { buildNavItems } from './nav-items';

const hrefs = (items: { href: string }[]) => items.map((item) => item.href);

describe('buildNavItems (T017-06)', () => {
  it('gives mentors /availability right after /sessions, keeping the mentor remaps and extras', () => {
    const items = buildNavItems('mentor', false);
    const list = hrefs(items);

    expect(list.indexOf('/availability')).toBe(list.indexOf('/sessions') + 1);
    expect(items.find((item) => item.href === '/availability')?.label).toBe('nav.availability');
    expect(list).toContain('/dashboard/mentor');
    expect(list).toContain('/dashboard/mentor/progress');
    expect(list).toContain('/dashboard/mentor/challenges');
    expect(list).toContain('/dashboard/mentor/feedback');
    expect(list).not.toContain('/admin');
  });

  it('does not give /availability to mentees or other roles', () => {
    for (const role of ['mentee', 'employee', 'company_admin', undefined]) {
      expect(hrefs(buildNavItems(role, false))).not.toContain('/availability');
    }
  });

  it('does not give /availability to admins (they keep the admin item)', () => {
    const list = hrefs(buildNavItems('admin', true));
    expect(list).not.toContain('/availability');
    expect(list[list.length - 1]).toBe('/admin');
  });
});
