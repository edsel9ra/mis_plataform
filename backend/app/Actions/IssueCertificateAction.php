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
        $relationship = MentorshipRelationship::with('mentor')->findOrFail($data['relationship_id']);

        $metadata = array_merge($data['metadata'] ?? [], [
            'user_name' => $user->full_name,
            'user_email' => $user->email,
            'mentor_name' => $relationship->mentor->full_name,
            'relationship_type' => $relationship->type,
            'issued_at' => now()->toIso8601String(),
        ]);

        $ipfsResult = $this->ipfsService->uploadCertificate([
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'type' => $data['type'],
            'metadata' => $metadata,
            'issued_at' => now()->toIso8601String(),
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
            'issued_at' => now(),
        ]);
    }
}
