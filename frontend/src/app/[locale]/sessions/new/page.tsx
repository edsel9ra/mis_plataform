'use client';

import { useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { AppShell } from '@/components/layout/AppShell';
import { useAuth } from '@/components/auth/AuthProvider';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { FormErrorSummary, type FormErrorItem } from '@/components/ui/form-error-summary';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { PageSpinner } from '@/components/ui/spinner';
import { useQueryClient } from '@tanstack/react-query';
import { useBookableRelationships, useBookSession } from '@/hooks/use-booking';
import { availabilitySlotsQueryOptions, useAvailabilitySlots } from '@/hooks/use-availability';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { apiErrorMessage } from '@/lib/errors';
import { sessionActionErrorOutcome } from '@/lib/session-actions';
import {
  BOOKING_WEEK_SEARCH_LIMIT,
  bookingDayOptions,
  bookingServerErrors,
  bookingSuccessKey,
  bookingSummaryParts,
  bookingWeekRange,
  canGoToPreviousWeek,
  firstWeekWithSlots,
  groupSlotsByDay,
  shiftBookingWeek,
  slotLabelParts,
  slotStillAvailable,
  toBookingPayload,
  type BookingSlotView,
} from '@/lib/booking';
import { bookSessionSchema, bookingDurationSchema } from '@/lib/schemas/booking';
import type { BookSessionFormData, BookSessionFormInput } from '@/lib/schemas/booking';
import { SESSION_MAX_DURATION, SESSION_MIN_DURATION } from '@/lib/schemas/session-actions';
import { localePath } from '@/i18n/paths';

/**
 * Mismo valor que `scheduling.default_timezone` (→ `app.timezone` =
 * America/Mexico_City): zona por defecto cuando el perfil no trae una.
 */
const DEFAULT_VIEWER_TIMEZONE = 'America/Mexico_City';

const DURATION_DEBOUNCE_MS = 400;

export default function NewSessionPage() {
  const t = useTranslations();
  const locale = useLocale();
  const { user } = useAuth();
  const viewerTimezone = user?.timezone ?? DEFAULT_VIEWER_TIMEZONE;

  const {
    data: relationships,
    isLoading: relationshipsLoading,
    error: relationshipsError,
    refetch: refetchRelationships,
  } = useBookableRelationships();

  const [announcement, setAnnouncement] = useState<string | null>(null);
  const [relationshipId, setRelationshipId] = useState('');
  const [durationRaw, setDurationRaw] = useState('60');
  const debouncedDuration = useDebouncedValue(durationRaw, DURATION_DEBOUNCE_MS);
  const [weekAnchor, setWeekAnchor] = useState<string>(() => new Date().toISOString());
  const [selectedDate, setSelectedDate] = useState<string | null>(null);
  const [selectedSlot, setSelectedSlot] = useState<BookingSlotView | null>(null);
  const [step3ServerFields, setStep3ServerFields] = useState<Record<string, string>>({});
  const [step3Summary, setStep3Summary] = useState<string[]>([]);
  const [focusStep2, setFocusStep2] = useState(false);
  const [searchRunId, setSearchRunId] = useState(0);
  const [success, setSuccess] = useState<{ id: string; title: string; when: string; created: boolean } | null>(null);

  const durationResult = bookingDurationSchema.safeParse(debouncedDuration);
  const duration = durationResult.success ? durationResult.data : null;
  const durationError = debouncedDuration.trim() === '' || durationResult.success
    ? null
    : durationResult.error.issues[0]?.message ?? null;
  const durationErrorText = durationError?.startsWith('session_actions.')
    || durationError?.startsWith('booking.')
    ? t(durationError)
    : durationError;

  const relationship = relationships?.find((item) => item.id === relationshipId) ?? null;

  // Preselect when there's a single bookable relationship.
  useEffect(() => {
    if (!relationshipId && relationships?.length === 1) {
      setRelationshipId(relationships[0].id);
    }
  }, [relationshipId, relationships]);

  const weekRange = useMemo(
    () => bookingWeekRange(weekAnchor, viewerTimezone),
    [weekAnchor, viewerTimezone],
  );

  const slotsEnabled = Boolean(relationship && duration !== null);
  const {
    data: slots,
    isLoading: slotsLoading,
    error: slotsError,
    refetch: refetchSlots,
  } = useAvailabilitySlots(
    relationship?.mentor.id ?? '',
    weekRange.from,
    weekRange.to,
    duration ?? SESSION_MIN_DURATION,
    slotsEnabled,
  );

  const queryClient = useQueryClient();
  const [searching, setSearching] = useState(false);
  const [horizonMiss, setHorizonMiss] = useState(false);
  const mentorId = relationship?.mentor.id;

  // RF-017.7b: on mount and when relationship/duration change, open directly on
  // the first week (≤8) with free slots; results feed the same React Query
  // cache `useAvailabilitySlots` reads.
  useEffect(() => {
    if (!mentorId || duration === null) return;
    const controller = new AbortController();
    setSearching(true);
    setHorizonMiss(false);
    const durationMinutes = duration;

    void (async () => {
      try {
        const found = await firstWeekWithSlots({
          startAnchor: new Date().toISOString(),
          viewerTimezone,
          fetchWeek: (range) => queryClient.fetchQuery(
            availabilitySlotsQueryOptions(mentorId, range.from, range.to, durationMinutes),
          ),
          signal: controller.signal,
        });
        if (controller.signal.aborted) return;
        if (found) {
          setWeekAnchor(found.range.from);
          setHorizonMiss(false);
        } else {
          setWeekAnchor(new Date().toISOString());
          setHorizonMiss(true);
        }
      } finally {
        if (!controller.signal.aborted) setSearching(false);
      }
    })();

    return () => controller.abort();
  }, [mentorId, duration, viewerTimezone, queryClient, searchRunId]);

  const bookSession = useBookSession();
  const form = useForm<BookSessionFormInput, unknown, BookSessionFormData>({
    resolver: zodResolver(bookSessionSchema),
    defaultValues: { relationship_id: '', title: '', description: '', duration_minutes: 60 },
  });

  // Keep the schema's hidden fields in sync with the wizard selections.
  useEffect(() => {
    form.setValue('relationship_id', relationshipId);
    form.setValue('duration_minutes', duration ?? 60);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [relationshipId, duration]);

  // Focus the step-2 heading after a taken slot is cleared, or the success h2.
  useEffect(() => {
    if (focusStep2) {
      document.getElementById('booking-step2')?.focus();
      setFocusStep2(false);
    }
  }, [focusStep2]);
  useEffect(() => {
    if (success) document.getElementById('booking-success')?.focus();
  }, [success]);

  const grouped = useMemo(
    () => groupSlotsByDay(slots ?? [], viewerTimezone, locale),
    [slots, viewerTimezone, locale],
  );
  const dayOptions = useMemo(
    () => bookingDayOptions(grouped, weekRange, viewerTimezone, locale),
    [grouped, weekRange, viewerTimezone, locale],
  );
  const selectedDay = grouped.find((day) => day.date === selectedDate) ?? null;

  // Default selected day: first one with slots whenever the slot set changes.
  useEffect(() => {
    setSelectedDate(grouped[0]?.date ?? null);
  }, [grouped]);

  // Changing relationship, duration or week clears the selected slot silently.
  useEffect(() => {
    setSelectedSlot(null);
  }, [relationshipId, duration, weekRange.from]);

  const canGoBack = canGoToPreviousWeek(weekRange, new Date().toISOString(), viewerTimezone);

  const weekHeader = (() => {
    const dayFormat = new Intl.DateTimeFormat(locale, {
      day: 'numeric',
      month: 'long',
      timeZone: viewerTimezone,
    });
    const start = dayFormat.format(new Date(weekRange.from));
    const end = dayFormat.format(new Date(Date.parse(weekRange.to) - 1));
    return t('booking.week', { start, end });
  })();

  function selectSlot(slot: BookingSlotView, dayLabel: string) {
    setSelectedSlot(slot);
    setStep3Summary([]);
    setStep3ServerFields({});
    const parts = slotLabelParts(slot, { date: '', label: dayLabel, slots: [] }, viewerTimezone);
    setAnnouncement(t('booking.slot_selected', parts));
  }

  function issueText(message: string | null | undefined): string | null {
    if (!message) return null;
    if (message.startsWith('session_actions.') || message.startsWith('booking.')) return t(message);
    return message;
  }

  async function submitBooking(values: BookSessionFormData) {
    if (!relationship || !selectedSlot || duration === null) return;
    setStep3ServerFields({});
    setStep3Summary([]);
    const day = selectedDay;
    const payload = toBookingPayload(
      relationship,
      selectedSlot,
      { title: values.title, description: values.description, duration_minutes: duration },
      viewerTimezone,
    );

    try {
      const result = await bookSession.mutateAsync(payload);
      const when = day
        ? bookingSummaryParts(relationship, selectedSlot, day, duration, viewerTimezone).when
        : `${selectedSlot.label} (${viewerTimezone})`;
      setSuccess({ id: result.session.id, title: payload.title, when, created: result.created });
      setAnnouncement(t(bookingSuccessKey(result.created), { title: payload.title, when }));
    } catch (error) {
      const outcome = sessionActionErrorOutcome(error);
      const mapped = bookingServerErrors(outcome.fieldErrors);
      const messages = [...mapped.summary];

      if (mapped.refreshSlots) {
        const { data: fresh } = await refetchSlots();
        if (!slotStillAvailable(selectedSlot, fresh ?? [])) {
          setSelectedSlot(null);
          messages.push(t('booking.slot_taken'));
          setFocusStep2(true);
        }
      }

      setStep3ServerFields(mapped.fields);
      if (messages.length > 0) {
        setStep3Summary(messages);
      } else {
        setStep3Summary([
          apiErrorMessage(error, (key) => t(key)) ?? outcome.message ?? t('common.error'),
        ]);
      }
    }
  }

  function bookAnother() {
    setSuccess(null);
    setSelectedSlot(null);
    setStep3Summary([]);
    setStep3ServerFields({});
    form.reset({ relationship_id: relationshipId, title: '', description: '', duration_minutes: 60 });
    setWeekAnchor(new Date().toISOString());
    setSearchRunId((id) => id + 1);
  }

  const hasRelationships = (relationships?.length ?? 0) > 0;
  const step3Visible = selectedSlot !== null && relationship !== null && duration !== null;
  const summaryParts = step3Visible && selectedDay
    ? bookingSummaryParts(relationship!, selectedSlot!, selectedDay, duration!, viewerTimezone)
    : null;

  function step3FieldError(field: 'title' | 'description'): string | null {
    const raw = form.formState.errors[field]?.message ?? step3ServerFields[field] ?? null;
    return issueText(raw);
  }

  const step3SummaryItems: FormErrorItem[] = (
    ['title', 'description'] as const
  ).flatMap((field) => {
    const message = step3FieldError(field);
    return message ? [{ fieldId: `booking-${field}`, message }] : [];
  });

  return (
    <AppShell>
      <div className="space-y-6">
        <div className="flex flex-col gap-2">
          <h1 className="text-2xl font-bold text-gray-950">{t('booking.book')}</h1>
          <Link
            href={localePath('/sessions', locale)}
            className="inline-flex min-h-6 w-fit items-center rounded text-sm font-medium text-primary-600 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
          >
            {t('booking.back')}
          </Link>
        </div>

        <div role="status" className="sr-only">
          {announcement}
        </div>

        {relationshipsLoading ? (
          <PageSpinner />
        ) : relationshipsError ? (
          <Alert variant="error">
            {apiErrorMessage(relationshipsError, (key) => t(key)) || t('common.load_error')}
            <button
              type="button"
              onClick={() => refetchRelationships()}
              className="ml-2 rounded font-semibold text-primary-600 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
              {t('common.retry')}
            </button>
          </Alert>
        ) : !hasRelationships ? (
          <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
            <p className="mx-auto max-w-md text-sm text-slate-500">{t('booking.no_relationships')}</p>
            <Link
              href={localePath('/sessions', locale)}
              className="mt-4 inline-block rounded text-sm font-medium text-primary-600 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
              {t('booking.back')}
            </Link>
          </div>
        ) : (
          <>
            <section aria-labelledby="booking-step1" className="space-y-4">
              <h2 id="booking-step1" className="text-lg font-semibold text-gray-900">
                {t('booking.step1')}
              </h2>
              <div className="grid gap-4 sm:grid-cols-2">
                <Select
                  id="booking-relationship"
                  label={t('booking.relationship')}
                  required
                  announceError={false}
                  placeholder={t('booking.relationship_placeholder')}
                  options={(relationships ?? []).map((item) => ({
                    value: item.id,
                    label: `${item.mentor.full_name} — ${item.source.name ?? ''}`,
                  }))}
                  value={relationshipId}
                  onChange={(event) => setRelationshipId(event.target.value)}
                />
                <Input
                  id="booking-duration"
                  type="number"
                  label={t('booking.duration')}
                  required
                  announceError={false}
                  min={SESSION_MIN_DURATION}
                  max={SESSION_MAX_DURATION}
                  step={1}
                  value={durationRaw}
                  onChange={(event) => setDurationRaw(event.target.value)}
                  error={durationErrorText ?? step3ServerFields.duration_minutes ?? undefined}
                />
              </div>
            </section>

            {success ? (
              <section aria-labelledby="booking-success" className="rounded-2xl border border-green-200 bg-green-50 p-6">
                <h2 id="booking-success" tabIndex={-1} className="text-lg font-semibold text-green-800 outline-none">
                  {t(bookingSuccessKey(success.created), { title: success.title, when: success.when })}
                </h2>
                <div className="mt-4 flex flex-wrap gap-2">
                  <Link
                    href={localePath(`/sessions/${success.id}`, locale)}
                    className="inline-flex min-h-6 items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                  >
                    {t('booking.view_session')}
                  </Link>
                  <Link
                    href={localePath('/sessions', locale)}
                    className="inline-flex min-h-6 items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                  >
                    {t('booking.back')}
                  </Link>
                  <Button variant="outline" type="button" onClick={bookAnother}>
                    {t('booking.book_another')}
                  </Button>
                </div>
              </section>
            ) : (
            <>
            <section aria-labelledby="booking-step2" className="space-y-4">
              <h2 id="booking-step2" tabIndex={-1} className="text-lg font-semibold text-gray-900 outline-none">
                {t('booking.step2')}
              </h2>
              {!selectedSlot && step3Summary.length > 0 && (
                <Alert variant="error">
                  <ul className="list-disc space-y-1 pl-5">
                    {step3Summary.map((message) => (
                      <li key={message}>{message}</li>
                    ))}
                  </ul>
                </Alert>
              )}
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm font-medium text-gray-900">{weekHeader}</p>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    type="button"
                    disabled={!canGoBack}
                    onClick={() => setWeekAnchor(shiftBookingWeek(weekAnchor, -1))}
                  >
                    {t('booking.prev_week')}
                  </Button>
                  <Button
                    variant="outline"
                    type="button"
                    onClick={() => setWeekAnchor(shiftBookingWeek(weekAnchor, 1))}
                  >
                    {t('booking.next_week')}
                  </Button>
                </div>
              </div>
              <p className="text-sm text-gray-500">{t('booking.zone_note', { tz: viewerTimezone })}</p>

              {!slotsEnabled ? (
                <p className="text-sm text-slate-500">{t('booking.relationship_placeholder')}</p>
              ) : searching ? (
                <p className="flex items-center gap-2 text-sm text-slate-500">
                  <PageSpinner />
                  {t('booking.searching')}
                </p>
              ) : horizonMiss ? (
                <Alert variant="info">
                  {t('booking.no_slots_horizon', { weeks: BOOKING_WEEK_SEARCH_LIMIT })}
                </Alert>
              ) : slotsLoading ? (
                <p className="flex items-center gap-2 text-sm text-slate-500">
                  <PageSpinner />
                  {t('booking.loading_slots')}
                </p>
              ) : slotsError ? (
                <Alert variant="error">
                  {apiErrorMessage(slotsError, (key) => t(key)) || t('common.load_error')}
                  <button
                    type="button"
                    onClick={() => refetchSlots()}
                    className="ml-2 rounded font-semibold text-primary-600 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                  >
                    {t('common.retry')}
                  </button>
                </Alert>
              ) : grouped.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                  <p className="mx-auto max-w-md text-sm text-slate-500">{t('booking.empty_week')}</p>
                </div>
              ) : (
                <>
                  <div className="flex flex-wrap gap-2" role="group" aria-label={t('booking.step2')}>
                    {dayOptions.map((option) =>
                      option.count === 0 ? (
                        <span
                          key={option.date}
                          className="rounded-lg px-3 py-2 text-sm text-slate-500"
                        >
                          {t('booking.day_none', { day: option.label })}
                        </span>
                      ) : (
                        <Button
                          key={option.date}
                          variant="outline"
                          type="button"
                          aria-pressed={selectedDate === option.date}
                          onClick={() => setSelectedDate(option.date)}
                        >
                          {t('booking.day_option', { day: option.label, count: option.count })}
                        </Button>
                      ),
                    )}
                  </div>

                  {selectedDay && (
                    <section aria-label={selectedDay.label}>
                      <h3 className="mt-4 text-sm font-semibold text-gray-900">
                        {selectedDay.label}
                      </h3>
                      <ul className="mt-2 flex flex-wrap gap-2">
                        {selectedDay.slots.map((slot) => (
                          <li key={slot.start}>
                            <Button
                              variant={selectedSlot?.start === slot.start ? 'primary' : 'outline'}
                              type="button"
                              aria-pressed={selectedSlot?.start === slot.start}
                              aria-label={t('booking.slot_label', slotLabelParts(slot, selectedDay, viewerTimezone))}
                              onClick={() => selectSlot(slot, selectedDay.label)}
                            >
                              {slot.label}
                            </Button>
                          </li>
                        ))}
                      </ul>
                    </section>
                  )}
                </>
              )}
            </section>

            {step3Visible && (
              <section aria-labelledby="booking-step3" className="space-y-4">
                <h2 id="booking-step3" tabIndex={-1} className="text-lg font-semibold text-gray-900 outline-none">
                  {t('booking.step3')}
                </h2>
                <form
                  onSubmit={(event) => void form.handleSubmit(submitBooking)(event)}
                  className="space-y-4"
                  noValidate
                >
                  <FormErrorSummary
                    title={t('common.validation_error')}
                    errors={step3SummaryItems}
                  />
                  {step3Summary.length > 0 && (
                    <Alert variant="error">
                      <ul className="list-disc space-y-1 pl-5">
                        {step3Summary.map((message) => (
                          <li key={message}>{message}</li>
                        ))}
                      </ul>
                    </Alert>
                  )}

                  <dl className="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
                    <div className="flex flex-wrap gap-1">
                      <dt className="font-medium text-gray-900">{t('booking.summary_relationship')}</dt>
                      <dd className="text-gray-700">{summaryParts?.relationship}</dd>
                    </div>
                    <div className="mt-1 flex flex-wrap gap-1">
                      <dt className="font-medium text-gray-900">{t('booking.summary_when')}</dt>
                      <dd className="text-gray-700">{summaryParts?.when}</dd>
                    </div>
                    <div className="mt-1 flex flex-wrap gap-1">
                      <dt className="font-medium text-gray-900">{t('booking.summary_duration')}</dt>
                      <dd className="text-gray-700">
                        {t('booking.duration_value', { minutes: summaryParts?.minutes ?? 0 })}
                      </dd>
                    </div>
                  </dl>

                  {relationship?.is_collective && (
                    <p role="note" className="text-sm text-gray-500">
                      {t('booking.collective_note', { source: relationship.source.name ?? '' })}
                    </p>
                  )}

                  <div className="grid gap-4">
                    <Input
                      id="booking-title"
                      label={t('booking.title')}
                      required
                      announceError={false}
                      error={step3FieldError('title') ?? undefined}
                      {...form.register('title')}
                    />
                    <Textarea
                      id="booking-description"
                      label={t('booking.description')}
                      rows={3}
                      announceError={false}
                      error={step3FieldError('description') ?? undefined}
                      {...form.register('description')}
                    />
                  </div>

                  <div>
                    <Button
                      variant="primary"
                      type="submit"
                      loading={bookSession.isPending}
                      disabled={bookSession.isPending}
                    >
                      {t('booking.confirm')}
                    </Button>
                  </div>
                </form>
              </section>
            )}
            </>
            )}
          </>
        )}
      </div>
    </AppShell>
  );
}
