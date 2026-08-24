<?php

use App\Services\IpfsService;
use Illuminate\Support\Facades\Http;

it('uploads only a certificate hash and non-identifying fields to IPFS', function () {
    config([
        'services.ipfs.api_key' => 'test-api-key',
        'services.ipfs.secret_key' => 'test-secret-key',
    ]);

    Http::fake([
        'https://api.pinata.cloud/pinning/pinJSONToIPFS' => Http::response([
            'IpfsHash' => 'bafytestcertificate',
        ], 200),
    ]);

    app(IpfsService::class)->uploadCertificate([
        'title' => 'Certificate for Alice Example',
        'description' => 'Private mentoring details',
        'type' => 'completion',
        'metadata' => [
            'subject_hash' => 'subject-hash',
            'mentor_hash' => 'mentor-hash',
        ],
        'issued_at' => now()->toIso8601String(),
    ]);

    Http::assertSent(function ($request) {
        $payload = json_encode($request->data(), JSON_THROW_ON_ERROR);

        return isset($request->data()['pinataContent']['certificate_hash'])
            && $request->data()['pinataContent']['type'] === 'completion'
            && !str_contains($payload, 'Alice Example')
            && !str_contains($payload, 'Private mentoring details');
    });
});
