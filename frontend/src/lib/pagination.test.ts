import { describe, expect, it } from 'vitest';
import { normalizePaginatedResponse } from './pagination';

describe('normalizePaginatedResponse', () => {
  it('normalizes Laravel resource metadata', () => {
    const result = normalizePaginatedResponse(
      {
        data: [{ id: 'one' }],
        meta: { current_page: 2, last_page: 4, per_page: 12, total: 40, from: 13, to: 13 },
      },
      { page: 2, perPage: 12 },
    );

    expect(result).toEqual({
      data: [{ id: 'one' }],
      meta: { current_page: 2, last_page: 4, per_page: 12, total: 40, from: 13, to: 13 },
    });
  });

  it('normalizes administrative root pagination fields', () => {
    const result = normalizePaginatedResponse(
      { data: [{ id: 'one' }], current_page: 3, last_page: 5, per_page: 20, total: 100 },
      { page: 3, perPage: 20 },
    );

    expect(result.meta).toMatchObject({ current_page: 3, last_page: 5, per_page: 20, total: 100 });
  });

  it('provides deterministic metadata for an unpaginated array', () => {
    const result = normalizePaginatedResponse([{ id: 'one' }, { id: 'two' }], { page: 2, perPage: 12 });

    expect(result.meta).toEqual({ current_page: 2, last_page: 1, per_page: 12, total: 2 });
  });
});
