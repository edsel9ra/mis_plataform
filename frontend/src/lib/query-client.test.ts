import { describe, expect, it } from 'vitest';
import { ApiError } from './api';
import { shouldRetryQuery } from './query-client';

describe('shouldRetryQuery', () => {
  it.each([401, 403, 422])('does not retry HTTP %s errors', (status) => {
    expect(shouldRetryQuery(0, new ApiError('request failed', status))).toBe(false);
  });

  it('does not retry aborted requests', () => {
    expect(shouldRetryQuery(0, new ApiError('Request canceled', 0, undefined, 'aborted'))).toBe(false);
  });

  it('retries one network or server failure and then stops', () => {
    expect(shouldRetryQuery(0, new ApiError('offline', 0, undefined, 'network'))).toBe(true);
    expect(shouldRetryQuery(1, new ApiError('offline', 0, undefined, 'network'))).toBe(false);
    expect(shouldRetryQuery(0, new ApiError('bad gateway', 502))).toBe(true);
  });
});
