/**
 * Presentación pura del flujo de reserva (spec 017, T017-03). Sin cadenas
 * traducidas: solo datos y claves.
 */

import { calendarLocalDate, CalendarRange } from './scheduling';
import type { AvailabilitySlot } from './availability';

export interface BookableRelationship {
  id: string;
  type: string;
  session_type: string;
  is_collective: boolean;
  mentor: { id: string; full_name: string };
  source: { type: string; name: string | null };
}

const DAY_MS = 86_400_000;

/** Semana (lunes→lunes) del ancla en la zona de quien consulta; reutiliza `CalendarRange.forPeriod`. */
export function bookingWeekRange(anchorIso: string, viewerTimezone: string): { from: string; to: string } {
  return CalendarRange.forPeriod('week', { anchor: anchorIso, now: anchorIso, timezone: viewerTimezone });
}

/** Desplaza el ancla de semana `direction` semanas completas (instante ±7 días). */
export function shiftBookingWeek(anchorIso: string, direction: 1 | -1): string {
  return new Date(Date.parse(anchorIso) + direction * 7 * DAY_MS).toISOString();
}

export interface BookingSlotView {
  start: string;
  end: string;
  /** Rango horario `HH:mm – HH:mm` en la zona de quien consulta. */
  label: string;
}

export interface SlotsByDay {
  /** `YYYY-MM-DD` local de quien consulta. */
  date: string;
  /** Etiqueta del día localizada (día de semana + fecha). */
  label: string;
  slots: BookingSlotView[];
}

export function groupSlotsByDay(
  slots: AvailabilitySlot[],
  viewerTimezone: string,
  locale: string,
): SlotsByDay[] {
  const timeFormat = new Intl.DateTimeFormat(locale, {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: viewerTimezone,
  });
  const dayFormat = new Intl.DateTimeFormat(locale, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    timeZone: viewerTimezone,
  });

  const byDate = new Map<string, SlotsByDay>();

  for (const slot of slots) {
    const date = calendarLocalDate(slot.start, viewerTimezone);
    const view: BookingSlotView = {
      start: slot.start,
      end: slot.end,
      label: `${timeFormat.format(new Date(slot.start))} – ${timeFormat.format(new Date(slot.end))}`,
    };
    const existing = byDate.get(date);
    if (existing) {
      existing.slots.push(view);
    } else {
      byDate.set(date, { date, label: dayFormat.format(new Date(slot.start)), slots: [view] });
    }
  }

  return [...byDate.values()].sort((a, b) => a.date.localeCompare(b.date));
}

export interface BookingDayOption {
  /** `YYYY-MM-DD` en la zona de quien consulta. */
  date: string;
  /** Etiqueta corta localizada, p. ej. `lun 22` / `Mon 22`. */
  label: string;
  /** Número de huecos disponibles ese día. */
  count: number;
}

/**
 * Las 7 opciones de día de una semana (lunes→domingo en la zona de quien
 * consulta), con la cuenta de huecos por día. Las fechas se derivan por
 * aritmética de días civiles (UTC) sobre la fecha local de `range.from`, así
 * no se ven afectadas por cambios de horario de verano.
 */
export function bookingDayOptions(
  groupedSlots: SlotsByDay[],
  weekRange: { from: string; to: string },
  viewerTimezone: string,
  locale: string,
): BookingDayOption[] {
  const weekStart = calendarLocalDate(weekRange.from, viewerTimezone);
  const [year, month, day] = weekStart.split('-').map(Number);
  const labelFormat = new Intl.DateTimeFormat(locale, {
    weekday: 'short',
    day: 'numeric',
    timeZone: 'UTC',
  });
  const counts = new Map(groupedSlots.map((group) => [group.date, group.slots.length]));

  return Array.from({ length: 7 }, (_, index) => {
    const date = new Date(Date.UTC(year, month - 1, day + index)).toISOString().slice(0, 10);
    return {
      date,
      label: labelFormat.format(new Date(`${date}T00:00:00Z`)),
      count: counts.get(date) ?? 0,
    };
  });
}

/**
 * Solo se puede retroceder si la semana mostrada empieza después de la fecha
 * local actual (no permite ir a la semana en curso ni anteriores).
 */
export function canGoToPreviousWeek(
  weekRange: { from: string; to: string },
  nowIso: string,
  viewerTimezone: string,
): boolean {
  return calendarLocalDate(weekRange.from, viewerTimezone)
    > calendarLocalDate(nowIso, viewerTimezone);
}

/** Partes para `booking.slot_label` / `booking.slot_selected` (aria-label y anuncio). */
export function slotLabelParts(
  slot: BookingSlotView,
  day: SlotsByDay,
  viewerTimezone: string,
): { range: string; date: string; tz: string } {
  return { range: slot.label, date: day.label, tz: viewerTimezone };
}

/**
 * Número máximo de semanas consecutivas que se recorren buscando la primera
 * con huecos (RF-017.7b). Límite documentado; la búsqueda es secuencial.
 */
export const BOOKING_WEEK_SEARCH_LIMIT = 8;

export interface FirstWeekWithSlotsResult {
  range: { from: string; to: string };
  slots: AvailabilitySlot[];
}

/**
 * Busca la primera semana (desde la del ancla, luego +1, +2…) con huecos,
 * llamando a `fetchWeek` de forma secuencial. Devuelve null cuando se agota el
 * límite o cuando la señal se aborta (sin más peticiones).
 */
export async function firstWeekWithSlots({
  startAnchor,
  viewerTimezone,
  limit = BOOKING_WEEK_SEARCH_LIMIT,
  fetchWeek,
  signal,
}: {
  startAnchor: string;
  viewerTimezone: string;
  limit?: number;
  fetchWeek: (range: { from: string; to: string }) => Promise<AvailabilitySlot[]>;
  signal?: AbortSignal;
}): Promise<FirstWeekWithSlotsResult | null> {
  let anchor = startAnchor;

  for (let index = 0; index < limit; index++) {
    if (signal?.aborted) return null;
    const range = bookingWeekRange(anchor, viewerTimezone);
    let slots: AvailabilitySlot[];
    try {
      slots = await fetchWeek(range);
    } catch (error) {
      if (signal?.aborted) return null;
      throw error;
    }
    if (slots.length > 0) {
      return { range, slots };
    }
    anchor = shiftBookingWeek(anchor, 1);
  }

  return null;
}

export interface BookingServerErrors {
  fields: { title?: string; description?: string; duration_minutes?: string };
  /** Mensajes de nivel formulario (rechazos de horario, relación, etc.). */
  summary: string[];
  /** True cuando hay que refrescar los huecos (rechazo por `scheduled_at`/`session`). */
  refreshSlots: boolean;
}

const BOOKING_FIELD_KEYS = new Set(['title', 'description', 'duration_minutes']);
const BOOKING_SLOT_KEYS = new Set(['scheduled_at', 'session']);

/** Reparte los errores 422 del backend entre campos, resumen y refresco de huecos. */
export function bookingServerErrors(fieldErrors: Record<string, string[]>): BookingServerErrors {
  const result: BookingServerErrors = { fields: {}, summary: [], refreshSlots: false };

  for (const [key, messages] of Object.entries(fieldErrors)) {
    const message = messages[0];
    if (message === undefined) continue;

    if (BOOKING_FIELD_KEYS.has(key)) {
      result.fields[key as keyof BookingServerErrors['fields']] = message;
      continue;
    }

    if (BOOKING_SLOT_KEYS.has(key)) result.refreshSlots = true;
    result.summary.push(message);
  }

  return result;
}

/** Partes del resumen del paso 3 (sin cadenas traducidas). */
export interface BookingSummaryParts {
  relationship: string;
  /** `{day label}, {range} ({tz})` — fecha y hora con zona en el locale/zona de quien consulta. */
  when: string;
  minutes: number;
}

export function bookingSummaryParts(
  relationship: BookableRelationship,
  slot: BookingSlotView,
  day: SlotsByDay,
  durationMinutes: number,
  viewerTimezone: string,
): BookingSummaryParts {
  return {
    relationship: `${relationship.mentor.full_name} — ${relationship.source.name ?? ''}`,
    when: `${day.label}, ${slot.label} (${viewerTimezone})`,
    minutes: durationMinutes,
  };
}

/** Clave i18n del mensaje de éxito según el resultado idempotente del POST. */
export function bookingSuccessKey(created: boolean): 'booking.success_created' | 'booking.success_existing' {
  return created ? 'booking.success_created' : 'booking.success_existing';
}

/** Comprueba si el hueco elegido sigue presente en la lista refrescada. */
export function slotStillAvailable(slot: BookingSlotView, slots: AvailabilitySlot[]): boolean {
  return slots.some((candidate) => candidate.start === slot.start && candidate.end === slot.end);
}

export interface BookingPayload {
  relationship_id: string;
  session_type: string;
  title: string;
  description?: string;
  scheduled_at: string;
  duration_minutes: number;
  timezone: string;
}

export interface BookingFormValues {
  title: string;
  description?: string;
  duration_minutes: number;
}

export function toBookingPayload(
  relationship: Pick<BookableRelationship, 'id' | 'session_type'>,
  slot: Pick<AvailabilitySlot, 'start'>,
  values: BookingFormValues,
  viewerTimezone: string,
): BookingPayload {
  const payload: BookingPayload = {
    relationship_id: relationship.id,
    session_type: relationship.session_type,
    title: values.title.trim(),
    scheduled_at: slot.start,
    duration_minutes: values.duration_minutes,
    timezone: viewerTimezone,
  };
  const description = values.description?.trim();
  if (description) payload.description = description;

  return payload;
}
