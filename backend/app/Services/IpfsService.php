<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpfsService
{
    private string $apiUrl;
    private ?string $apiKey;
    private ?string $secretKey;

    public function __construct()
    {
        $this->apiUrl = config('services.ipfs.api_url', 'https://api.pinata.cloud');
        $this->apiKey = config('services.ipfs.api_key');
        $this->secretKey = config('services.ipfs.secret_key');
    }

    public function uploadCertificate(array $data): array
    {
        if (!filled($this->apiKey) || !filled($this->secretKey)) {
            throw new \RuntimeException(__('ipfs.not_configured'));
        }

        $publicContent = [
            'version' => 1,
            'certificate_hash' => hash('sha256', json_encode([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'issued_at' => $data['issued_at'] ?? null,
            ], JSON_THROW_ON_ERROR)),
            'type' => $data['type'] ?? null,
            'issued_at' => $data['issued_at'] ?? null,
        ];

        $metadata = [
            'pinataContent' => $publicContent,
            'pinataMetadata' => [
                'name' => 'mis-certificate-' . uniqid(),
                'keyvalues' => [
                    'type' => 'certificate',
                    'timestamp' => now()->toIso8601String(),
                ],
            ],
        ];

        $response = Http::withHeaders([
            'pinata_api_key' => $this->apiKey,
            'pinata_secret_api_key' => $this->secretKey,
            'Content-Type' => 'application/json',
        ])->connectTimeout((int) config('services.ipfs.timeout', 15))
            ->timeout((int) config('services.ipfs.timeout', 15))
            ->post("{$this->apiUrl}/pinning/pinJSONToIPFS", $metadata);

        if (!$response->successful()) {
            Log::error('IPFS upload error', ['status' => $response->status()]);
            throw new \RuntimeException(__('ipfs.upload_error'));
        }

        $result = $response->json();

        return [
            'cid' => $result['IpfsHash'],
            'uri' => "ipfs://{$result['IpfsHash']}",
        ];
    }

    public function getCertificate(string $cid): ?array
    {
        if (!preg_match('/^[A-Za-z0-9]+$/', $cid)) {
            return null;
        }

        $response = Http::connectTimeout((int) config('services.ipfs.timeout', 15))
            ->timeout((int) config('services.ipfs.timeout', 15))
            ->get("https://gateway.pinata.cloud/ipfs/{$cid}");

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }
}
