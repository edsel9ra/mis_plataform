<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlockchainService
{
    private string $rpcUrl;
    private string $privateKey;
    private string $contractAddress;

    public function __construct()
    {
        $this->rpcUrl = config('services.polygon.rpc_url');
        $this->privateKey = config('services.polygon.private_key');
        $this->contractAddress = config('services.polygon.contract_address');
    }

    public function issueCertificate(string $ipfsCid): array
    {
        $hash = '0x' . hash('sha256', $ipfsCid . now()->toIso8601String());

        $response = Http::timeout(60)->post($this->rpcUrl, [
            'jsonrpc' => '2.0',
            'method' => 'eth_sendTransaction',
            'params' => [[
                'from' => $this->getWalletAddress(),
                'to' => $this->contractAddress,
                'data' => $this->encodeIssueData($hash, $this->getWalletAddress()),
                'gas' => '0x493E0',
            ]],
            'id' => 1,
        ]);

        if (!$response->successful()) {
            Log::error('Blockchain issue error', ['response' => $response->body()]);
            throw new \RuntimeException(__('blockchain.issue_error'));
        }

        $result = $response->json();

        if (isset($result['error'])) {
            throw new \RuntimeException($result['error']['message']);
        }

        return [
            'tx_hash' => $result['result'],
            'contract_address' => $this->contractAddress,
        ];
    }

    public function verifyCertificate(string $txHash, string $ipfsCid): bool
    {
        $storedHash = '0x' . hash('sha256', $ipfsCid . '*');

        $response = Http::timeout(30)->post($this->rpcUrl, [
            'jsonrpc' => '2.0',
            'method' => 'eth_call',
            'params' => [[
                'to' => $this->contractAddress,
                'data' => $this->encodeVerifyData($storedHash),
            ], 'latest'],
            'id' => 1,
        ]);

        if (!$response->successful()) {
            return false;
        }

        $result = $response->json();

        if (isset($result['error'])) {
            Log::warning('Blockchain verify warning', ['error' => $result['error']]);
            return false;
        }

        $txResponse = Http::timeout(30)->post($this->rpcUrl, [
            'jsonrpc' => '2.0',
            'method' => 'eth_getTransactionReceipt',
            'params' => [$txHash],
            'id' => 1,
        ]);

        if (!$txResponse->successful() || !$txResponse->json()['result']) {
            return false;
        }

        return true;
    }

    public function revokeCertificate(string $ipfsCid): void
    {
        $hash = '0x' . hash('sha256', $ipfsCid . now()->toIso8601String());

        $response = Http::timeout(60)->post($this->rpcUrl, [
            'jsonrpc' => '2.0',
            'method' => 'eth_sendTransaction',
            'params' => [[
                'from' => $this->getWalletAddress(),
                'to' => $this->contractAddress,
                'data' => $this->encodeRevokeData($hash),
                'gas' => '0x493E0',
            ]],
            'id' => 1,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(__('blockchain.revoke_error'));
        }
    }

    private function getWalletAddress(): string
    {
        // Derive address from private key
        // In production, use web3.php library for proper key management
        return '0x0000000000000000000000000000000000000000';
    }

    private function encodeIssueData(string $hash, string $issuer): string
    {
        // Simplified ABI encoding for issue(bytes32,address)
        $method = '0x' . substr(hash('sha256', 'issue(bytes32,address)'), 0, 8);
        $hashPadded = str_pad(substr($hash, 2), 64, '0', STR_PAD_LEFT);
        $issuerPadded = str_pad(substr($issuer, 2), 64, '0', STR_PAD_LEFT);

        return $method . $hashPadded . $issuerPadded;
    }

    private function encodeVerifyData(string $hash): string
    {
        $method = '0x' . substr(hash('sha256', 'verify(bytes32)'), 0, 8);
        $hashPadded = str_pad(substr($hash, 2), 64, '0', STR_PAD_LEFT);

        return $method . $hashPadded;
    }

    private function encodeRevokeData(string $hash): string
    {
        $method = '0x' . substr(hash('sha256', 'revoke(bytes32)'), 0, 8);
        $hashPadded = str_pad(substr($hash, 2), 64, '0', STR_PAD_LEFT);

        return $method . $hashPadded;
    }
}
