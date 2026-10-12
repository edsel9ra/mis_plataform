<?php

namespace App\Actions;

use App\Models\Certificate;
use App\Models\MentorshipRelationship;
use App\Models\User;
use App\Services\IpfsService;
use App\Services\BlockchainService;

class IssueCertificateAction
{
    public function __construct(
        private IpfsService $ipfsService,
        private BlockchainService $blockchainService,
    ) {}

    public function execute(array $data): Certificate
    {
        $user = User::findOrFail($data['user_id']);
        $relationship = MentorshipRelationship::findOrFail($data['relationship_id']);

        $this->blockchainService->assertIssueAvailable();

        $issuedAt = now();
        $metadata = array_merge($data['metadata'] ?? [], [
            'subject_hash' => $this->publicIdentifier($user->id),
            'mentor_hash' => $this->publicIdentifier($relationship->mentor_id),
            'relationship_hash' => $this->publicIdentifier($relationship->id),
            'relationship_type' => $relationship->type,
            'issued_at' => $issuedAt->toIso8601String(),
        ]);

        $ipfsResult = $this->ipfsService->uploadCertificate([
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'type' => $data['type'],
            'metadata' => $metadata,
            'issued_at' => $issuedAt->toIso8601String(),
        ]);

        $blockchainResult = $this->blockchainService->issueCertificate($ipfsResult['cid']);

        return Certificate::create([
            'user_id' => $data['user_id'],
            'relationship_id' => $data['relationship_id'],
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'metadata' => $metadata,
            'ipfs_cid' => $ipfsResult['cid'],
            'ipfs_uri' => $ipfsResult['uri'],
            'blockchain_tx_hash' => $blockchainResult['tx_hash'],
            'blockchain_contract_address' => $blockchainResult['contract_address'],
            'issued_at' => $issuedAt,
        ]);
    }

    private function publicIdentifier(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
