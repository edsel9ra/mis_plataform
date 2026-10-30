<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleMeetService
{
    private ?string $accessToken = null;

    public function __construct()
    {
        // Token is loaded lazily so listing/viewing sessions does not require Google credentials.
    }

    public function createMeetEvent(
        string $title,
        \DateTimeInterface $startTime,
        int $durationMinutes,
        string $timezone,
        string $idempotencyKey,
    ): array
    {
        // Calendar event IDs are base32hex. A lowercase hexadecimal SHA-256
        // digest is a valid subset and is stable for every retry of this session.
        $eventId = hash('sha256', 'mis-session-event:'.$idempotencyKey);
        $event = array_merge($this->eventPayload($title, $startTime, $durationMinutes, $timezone), [
            'id' => $eventId,
            'conferenceData' => [
                'createRequest' => [
                    // This IDs the Meet-conference request only; the stable
                    // event ID below provides Calendar insert idempotency.
                    'requestId' => (string) Str::uuid(),
                    'conferenceSolutionKey' => [
                        'type' => 'hangoutsMeet',
                    ],
                ],
            ],
        ]);

        $response = Http::withToken($this->accessToken())
            ->connectTimeout((int) config('services.google.timeout', 10))
            ->timeout((int) config('services.google.timeout', 10))
            ->post('https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1', $event);

        if ($response->status() === 409) {
            // A previous insert may have reached Google although MIS lost its
            // response. Retrieve by the stable event ID, then reconcile its
            // schedule to the current MIS values before persisting it locally.
            $existing = Http::withToken($this->accessToken())
                ->connectTimeout((int) config('services.google.timeout', 10))
                ->timeout((int) config('services.google.timeout', 10))
                ->get('https://www.googleapis.com/calendar/v3/calendars/primary/events/'.rawurlencode($eventId));

            if (! $existing->successful()) {
                throw new \RuntimeException(__('meet.create_error'));
            }

            $existingData = $existing->json();
            $updated = $this->updateMeetEvent($eventId, $title, $startTime, $durationMinutes, $timezone);
            if ($updated === null) {
                throw new \RuntimeException(__('meet.create_error'));
            }

            return [
                'meet_link' => $updated['meet_link'] ?? $existingData['hangoutLink'] ?? null,
                'event_id' => $eventId,
                'event_data' => $existingData,
            ];
        }

        if (!$response->successful()) {
            Log::error('Google Calendar API error', ['status' => $response->status()]);
            throw new \RuntimeException(__('meet.create_error'));
        }

        $data = $response->json();

        return [
            'meet_link' => $data['hangoutLink'] ?? null,
            'event_id' => $data['id'] ?? $eventId,
            'event_data' => $data,
        ];
    }

    /**
     * Update the existing Calendar event. A null result means Google confirms
     * that the stored event no longer exists, so the caller may safely recreate it.
     */
    public function updateMeetEvent(
        string $eventId,
        string $title,
        \DateTimeInterface $startTime,
        int $durationMinutes,
        string $timezone,
    ): ?array {
        $response = Http::withToken($this->accessToken())
            ->connectTimeout((int) config('services.google.timeout', 10))
            ->timeout((int) config('services.google.timeout', 10))
            ->patch(
                'https://www.googleapis.com/calendar/v3/calendars/primary/events/'.rawurlencode($eventId),
                $this->eventPayload($title, $startTime, $durationMinutes, $timezone),
            );

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            Log::error('Google Calendar event update failed', ['status' => $response->status()]);
            throw new \RuntimeException('Google Calendar event update failed.');
        }

        $data = $response->json();

        return [
            'meet_link' => $data['hangoutLink'] ?? null,
            'event_id' => $data['id'] ?? $eventId,
        ];
    }

    public function deleteEvent(string $eventId): void
    {
        $response = Http::withToken($this->accessToken())
            ->connectTimeout((int) config('services.google.timeout', 10))
            ->timeout((int) config('services.google.timeout', 10))
            ->delete('https://www.googleapis.com/calendar/v3/calendars/primary/events/'.rawurlencode($eventId));

        if (! $response->successful() && $response->status() !== 404) {
            Log::error('Google Calendar event deletion failed', ['status' => $response->status()]);
            throw new \RuntimeException('Google Calendar event deletion failed.');
        }
    }

    private function accessToken(): string
    {
        return $this->accessToken ??= $this->getAccessToken();
    }

    private function getAccessToken(): string
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $impersonate = config('services.google.calendar.impersonate');

        $credentialsPath = storage_path('app/google-service-account.json');

        if (!file_exists($credentialsPath)) {
            throw new \RuntimeException(__('meet.credentials_missing'));
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);

        $jwtHeader = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $now = time();
        $jwtPayload = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/calendar.events',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
            'sub' => $impersonate,
        ]));

        $signature = '';
        openssl_sign("{$jwtHeader}.{$jwtPayload}", $signature, $credentials['private_key'], 'sha256WithRSAEncryption');

        $jwt = "{$jwtHeader}.{$jwtPayload}." . $this->base64UrlEncode($signature);

        $response = Http::asForm()
            ->connectTimeout((int) config('services.google.timeout', 10))
            ->timeout((int) config('services.google.timeout', 10))
            ->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(__('meet.token_error'));
        }

        return $response->json()['access_token'];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function eventPayload(
        string $title,
        \DateTimeInterface $startTime,
        int $durationMinutes,
        string $timezone,
    ): array {
        $startTime = \DateTimeImmutable::createFromInterface($startTime);
        $endTime = $startTime->modify("+{$durationMinutes} minutes");

        return [
            'summary' => $title,
            'start' => [
                'dateTime' => $startTime->format('c'),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => $endTime->format('c'),
                'timeZone' => $timezone,
            ],
        ];
    }
}
