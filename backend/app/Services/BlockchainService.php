<?php

namespace App\Services;

class BlockchainService
{
    private ?string $rpcUrl;
    private ?string $privateKey;
    private ?string $contractAddress;
    private bool $enabled;

    public function __construct()
    {
        $this->rpcUrl = config('services.polygon.rpc_url');
        $this->privateKey = config('services.polygon.private_key');
        $this->contractAddress = config('services.polygon.contract_address');
        $this->enabled = (bool) config('services.polygon.enabled', false);
    }

    public function issueCertificate(string $ipfsCid): array
    {
        $this->assertIssueAvailable();
    }

    public function verifyCertificate(string $txHash, string $ipfsCid): bool
    {
        if (!$this->enabled || !$this->isConfigured()) {
            return false;
        }

        // Receipt-only verification is unsafe because it does not prove the CID/hash.
        return false;
    }

    public function revokeCertificate(string $ipfsCid): void
    {
        $this->assertIssueAvailable();
    }

    public function assertIssueAvailable(): void
    {
        if (!$this->enabled) {
            throw new \RuntimeException(__('blockchain.disabled'));
        }

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
