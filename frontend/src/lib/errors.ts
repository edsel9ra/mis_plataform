import { ApiError } from './api';

export type ApiErrorKind = 'validation' | 'auth' | 'network' | 'server' | 'unknown' | 'aborted';

export function classifyApiError(error: unknown): ApiErrorKind {
  if (!(error instanceof ApiError)) return 'unknown';
  if (error.code === 'aborted') return 'aborted';
  if (error.code === 'network' || error.code === 'timeout') return 'network';
  if (error.status === 401 || error.status === 403) return 'auth';
  if (error.status === 422) return 'validation';
  if (error.status >= 500) return 'server';

  return 'unknown';
}

export function apiErrorMessage(
  error: unknown,
  translate: (key: string) => string,
): string {
  const kind = classifyApiError(error);

  if (kind === 'validation') return translate('common.validation_error');

  if (kind === 'auth' && error instanceof ApiError) {
    return translate(error.status === 401 ? 'common.unauthorized_error' : 'common.forbidden_error');
  }

  if (kind === 'network') return translate('common.network_error');
  if (kind === 'server') return translate('common.server_error');
  if (kind === 'aborted') return '';

  return translate('common.error');
}
