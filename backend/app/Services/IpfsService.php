<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpfsService
{
    private string $apiUrl;
    private string $apiKey;
    private string $secretKey;

    public function __construct()
    {
        $this->apiUrl = config('services.ipfs.api_url', 'https://api.pinata.cloud');
        $this->apiKey = config('services.ipfs.api_key');
        $this->secretKey = config('services.ipfs.secret_key');
    }

    public function uploadCertificate(array $data): array
    {
        $metadata = [
            'pinataContent' => $data,
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
        ])->post("{$this->apiUrl}/pinning/pinJSONToIPFS", $metadata);

        if (!$response->successful()) {
            Log::error('IPFS upload error', ['response' => $response->body()]);
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
        $response = Http::get("https://gateway.pinata.cloud/ipfs/{$cid}");

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }
}
