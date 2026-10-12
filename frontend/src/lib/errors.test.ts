import { describe, expect, it } from 'vitest';
import { ApiError } from './api';
import { apiErrorMessage } from './errors';

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
});
