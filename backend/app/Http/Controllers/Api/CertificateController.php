<?php

namespace App\Http\Controllers\Api;

use App\Actions\IssueCertificateAction;
use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use App\Services\BlockchainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateController
{
    public function __construct(
        private IssueCertificateAction $issueCertificate,
        private BlockchainService $blockchainService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $certificates = Certificate::where('user_id', $request->user()->id)
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json($certificates);
    }

    public function issue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'relationship_id' => ['required', 'exists:mentorship_relationships,id'],
            'type' => ['required', 'string', 'in:completion,skill,mentorship_hours'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $certificate = $this->issueCertificate->execute($validated);

        return response()->json(new CertificateResource($certificate), 201);
    }

    public function show(string $id): JsonResponse
    {
        $certificate = Certificate::with(['user', 'relationship.mentor'])
            ->findOrFail($id);

        return response()->json(new CertificateResource($certificate));
    }

    public function verify(string $id): JsonResponse
    {
        $certificate = Certificate::with('user')->findOrFail($id);

        $blockchainValid = $this->blockchainService->verifyCertificate(
            $certificate->blockchain_tx_hash,
            $certificate->ipfs_cid,
        );

        return response()->json([
            'valid' => !$certificate->revoked && $blockchainValid,
            'certificate' => [
                'id' => $certificate->id,
                'title' => $certificate->title,
                'user' => $certificate->user->full_name,
                'issued_at' => $certificate->issued_at,
                'revoked' => $certificate->revoked,
            ],
            'blockchain_verification' => $blockchainValid,
            'ipfs_uri' => $certificate->ipfs_uri,
        ]);
    }

    public function revoke(string $id): JsonResponse
    {
        $certificate = Certificate::findOrFail($id);

        $this->blockchainService->revokeCertificate($certificate->ipfs_cid);

        $certificate->update(['revoked' => true]);

        return response()->json(['message' => __('certificates.revoked')]);
    }
}
