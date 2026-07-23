<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMeetService
{
    private ?string $accessToken = null;

    public function __construct()
    {
        // Token is loaded lazily so listing/viewing sessions does not require Google credentials.
    }

    public function createMeetEvent(string $title, \DateTime $startTime, int $durationMinutes): array
    {
        $endTime = (clone $startTime)->modify("+{$durationMinutes} minutes");

        $event = [
            'summary' => $title,
            'start' => [
                'dateTime' => $startTime->format('c'),
                'timeZone' => 'America/Mexico_City',
            ],
            'end' => [
                'dateTime' => $endTime->format('c'),
                'timeZone' => 'America/Mexico_City',
            ],
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => uniqid(),
                    'conferenceSolutionKey' => [
                        'type' => 'hangoutsMeet',
                    ],
                ],
            ],
        ];

        $response = Http::withToken($this->accessToken())
            ->post('https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1', $event);

        if (!$response->successful()) {
            Log::error('Google Calendar API error', ['response' => $response->body()]);
            throw new \RuntimeException(__('meet.create_error'));
        }

        $data = $response->json();

        return [
            'meet_link' => $data['hangoutLink'] ?? null,
            'event_id' => $data['id'],
            'event_data' => $data,
        ];
    }

    public function deleteEvent(string $eventId): void
    {
        Http::withToken($this->accessToken())
            ->delete("https://www.googleapis.com/calendar/v3/calendars/primary/events/{$eventId}");
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

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
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
}
