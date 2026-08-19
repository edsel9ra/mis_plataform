function pad(value: number): string {
  return String(value).padStart(2, '0');
}

export function apiIsoToDatetimeLocal(value: string | null | undefined): string {
  if (!value) return '';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function datetimeLocalToApiIso(value: string): string {
  if (!value) return '';

  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toISOString();
}
