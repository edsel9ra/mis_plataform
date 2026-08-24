export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from?: number | null;
  to?: number | null;
}

export interface RawPaginatedResponse<T> {
  data?: T[];
  meta?: Partial<PaginationMeta> | null;
  links?: Record<string, string | null>;
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
  from?: number | null;
  to?: number | null;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: PaginationMeta;
  links?: Record<string, string | null>;
}

interface PaginationFallback {
  page: number;
  perPage: number;
}

export function normalizePaginatedResponse<T>(
  response: RawPaginatedResponse<T> | T[],
  fallback: PaginationFallback = { page: 1, perPage: 20 },
): PaginatedResponse<T> {
  if (Array.isArray(response)) {
    return {
      data: response,
      meta: {
        current_page: fallback.page,
        last_page: 1,
        per_page: fallback.perPage,
        total: response.length,
      },
    };
  }

  const source = response.meta ?? response;
  const currentPage = positiveNumber(source.current_page, fallback.page);
  const perPage = positiveNumber(source.per_page, fallback.perPage);
  const total = nonNegativeNumber(source.total, response.data?.length ?? 0);
  const lastPage = positiveNumber(source.last_page, Math.max(1, Math.ceil(total / perPage)));

  return {
    data: Array.isArray(response.data) ? response.data : [],
    meta: {
      current_page: currentPage,
      last_page: Math.max(currentPage, lastPage),
      per_page: perPage,
      total,
      from: numberOrNull(source.from),
      to: numberOrNull(source.to),
    },
    links: response.links,
  };
}

function positiveNumber(value: number | undefined, fallback: number): number {
  return typeof value === 'number' && Number.isFinite(value) && value > 0 ? value : fallback;
}

function nonNegativeNumber(value: number | undefined, fallback: number): number {
  return typeof value === 'number' && Number.isFinite(value) && value >= 0 ? value : fallback;
}

function numberOrNull(value: number | null | undefined): number | null | undefined {
  return typeof value === 'number' || value === null ? value : undefined;
}
