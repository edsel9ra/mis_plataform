import { describe, expect, it } from 'vitest';

import { CalendarSessionMapper, type CalendarSessionInput } from './scheduling';

const now = '2026-06-10T12:00:00.000Z';

function baseSession(overrides: Partial<CalendarSessionInput> = {}): CalendarSessionInput {
  return {
    id: '11111111-2222-3333-4444-555555555555',
    title: 'Mentoría',
    status: 'scheduled',
    scheduled_at: '2026-06-10T15:00:00.000Z',
    duration_minutes: 45,
    meet_link: null,
    session_type: 'individual',
    relationship: { source_type: 'user', source: { name: null } },
    attendees: [],
    ...overrides,
  };
}

describe('CalendarSessionMapper.toCalendarItem', () => {
  it('maps duration and attendee names', () => {
    const item = CalendarSessionMapper.toCalendarItem(
      baseSession({
        attendees: [
          { id: 'a', user: { id: 'u1', name: 'Ana', full_name: 'Ana García' } },
          { id: 'b', user: { id: 'u2', name: null, full_name: null } },
        ],
      }),
      'UTC',
      now,
    );

    expect(item.durationMinutes).toBe(45);
    expect(item.attendeeNames).toEqual(['Ana García']);
  });

  it('meet state available when meet_link present', () => {
    const item = CalendarSessionMapper.toCalendarItem(
      baseSession({ meet_link: 'https://meet.example/x' }),
      'UTC',
      now,
    );
    expect(item.meet).toEqual({ state: 'available', link: 'https://meet.example/x' });
  });

  it('meet state pending for future scheduled without link', () => {
    const item = CalendarSessionMapper.toCalendarItem(
      baseSession({ scheduled_at: '2026-06-10T15:00:00.000Z' }),
      'UTC',
      now,
    );
    expect(item.meet).toEqual({ state: 'pending', link: null });
  });

  it('meet state unavailable for past scheduled without link', () => {
    const item = CalendarSessionMapper.toCalendarItem(
      baseSession({ scheduled_at: '2026-06-10T10:00:00.000Z' }),
      'UTC',
      now,
    );
    expect(item.meet).toEqual({ state: 'unavailable', link: null });
  });

  it('meet state hidden when canceled', () => {
    const item = CalendarSessionMapper.toCalendarItem(
      baseSession({ status: 'canceled', meet_link: 'https://meet.example/x' }),
      'UTC',
      now,
    );
    expect(item.meet).toEqual({ state: 'hidden', link: null });
  });

  it('marks collective sessions and collective name', () => {
    for (const sourceType of ['family_group', 'cohort', 'company']) {
      const item = CalendarSessionMapper.toCalendarItem(
        baseSession({
          relationship: { source_type: sourceType, source: { name: 'Grupo X' } },
        }),
        'UTC',
        now,
      );
      expect(item.isCollective).toBe(true);
      expect(item.collectiveName).toBe('Grupo X');
    }
  });

  it('non-collective source_type is not collective', () => {
    const item = CalendarSessionMapper.toCalendarItem(baseSession(), 'UTC', now);
    expect(item.isCollective).toBe(false);
    expect(item.collectiveName).toBeNull();
  });

  it('preserves persisted status (not_completed)', () => {
    const item = CalendarSessionMapper.toCalendarItem(
      baseSession({ status: 'not_completed' }),
      'UTC',
      now,
    );
    expect(item.status).toBe('not_completed');
  });
});
