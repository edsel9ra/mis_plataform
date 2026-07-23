<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BlockchainService
{
    private ?string $rpcUrl;
    private ?string $privateKey;
    private ?string $contractAddress;

    public function __construct()
    {
        $this->rpcUrl = config('services.polygon.rpc_url');
        $this->privateKey = config('services.polygon.private_key');
        $this->contractAddress = config('services.polygon.contract_address');
    }

    public function issueCertificate(string $ipfsCid): array
    {
        $this->assertIssueAvailable();
    }

    public function verifyCertificate(string $txHash, string $ipfsCid): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $response = Http::timeout(30)->post($this->rpcUrl, [
            'jsonrpc' => '2.0',
            'method' => 'eth_getTransactionReceipt',
            'params' => [$txHash],
            'id' => 1,
        ]);

        if (!$response->successful()) {
            return false;
        }

        $receipt = $response->json('result');

        return is_array($receipt)
            && strtolower((string) ($receipt['to'] ?? '')) === strtolower($this->contractAddress)
            && ($receipt['status'] ?? null) === '0x1';
    }

    public function revokeCertificate(string $ipfsCid): void
    {
        $this->assertIssueAvailable();
    }

    public function assertIssueAvailable(): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException(__('blockchain.not_configured'));
        }

        throw new \RuntimeException(__('blockchain.signing_not_implemented'));
    }

    private function isConfigured(): bool
    {
        return filled($this->rpcUrl) && filled($this->privateKey) && filled($this->contractAddress);
    }
}
