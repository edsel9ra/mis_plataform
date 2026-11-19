import { describe, expect, it } from 'vitest';
import { ApiError } from './api';
import { apiErrorMessage, classifyApiError, isNotFoundError } from './errors';

const translate = (key: string) => key;

describe('apiErrorMessage', () => {
  it('does not expose raw validation messages', () => {
    const error = new ApiError('backend detail', 422, { email: ['Correo interno'] });

    expect(apiErrorMessage(error, translate)).toBe('common.validation_error');
  });

  it('maps authentication failures to localized keys', () => {
    expect(apiErrorMessage(new ApiError('unauthorized', 401), translate)).toBe('common.unauthorized_error');
    expect(apiErrorMessage(new ApiError('forbidden', 403), translate)).toBe('common.forbidden_error');
  });

  it('uses a localized generic message for unknown errors', () => {
    expect(apiErrorMessage(new Error('database connection details'), translate)).toBe('common.error');
  });

  it('maps HTTP 429 to a localized rate-limit message', () => {
    expect(classifyApiError(new ApiError('too many requests', 429))).toBe('rate_limited');
    expect(apiErrorMessage(new ApiError('too many requests', 429), translate)).toBe('common.rate_limited');
  });
});

describe('isNotFoundError', () => {
  it('returns true for a 404 ApiError', () => {
    expect(isNotFoundError(new ApiError('not found', 404))).toBe(true);
  });

  it('returns false for other ApiError statuses', () => {
    expect(isNotFoundError(new ApiError('forbidden', 403))).toBe(false);
    expect(isNotFoundError(new ApiError('server error', 500))).toBe(false);
    expect(isNotFoundError(new ApiError('unprocessable', 422))).toBe(false);
  });

  it('returns false for a plain Error', () => {
    expect(isNotFoundError(new Error('not found'))).toBe(false);
  });

  it('returns false for undefined', () => {
    expect(isNotFoundError(undefined)).toBe(false);
  });
});
