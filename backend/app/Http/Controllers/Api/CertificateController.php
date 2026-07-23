<?php

namespace App\Http\Controllers\Api;

use App\Actions\IssueCertificateAction;
use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use App\Models\MentorshipRelationship;
use App\Models\User;
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

        $relationship = MentorshipRelationship::findOrFail($validated['relationship_id']);
        $recipient = User::findOrFail($validated['user_id']);
        $issuer = $request->user();

        abort_unless($issuer->isAdmin() || $relationship->mentor_id === $issuer->id, 403);
        abort_unless($relationship->status === 'completed', 422, __('certificates.relationship_not_completed'));
        abort_unless($relationship->involvesUser($recipient), 422, __('certificates.user_not_in_relationship'));

        $certificate = $this->issueCertificate->execute($validated);

        return response()->json(new CertificateResource($certificate), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $certificate = Certificate::with(['user', 'relationship.mentor'])
            ->findOrFail($id);

        abort_unless($this->canAccessCertificate($request->user(), $certificate), 403);

        return response()->json(new CertificateResource($certificate));
    }

    public function verify(Request $request, string $id): JsonResponse
    {
        $certificate = Certificate::with(['user', 'relationship'])->findOrFail($id);

        abort_unless($this->canAccessCertificate($request->user(), $certificate), 403);

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

    public function revoke(Request $request, string $id): JsonResponse
    {
        $certificate = Certificate::findOrFail($id);

        abort_unless($request->user()->isAdmin(), 403);

        $this->blockchainService->revokeCertificate($certificate->ipfs_cid);

        $certificate->update(['revoked' => true]);

        return response()->json(['message' => __('certificates.revoked')]);
    }

    private function canAccessCertificate(User $user, Certificate $certificate): bool
    {
        if ($user->isAdmin() || $certificate->user_id === $user->id) {
            return true;
        }

        $relationship = $certificate->relationLoaded('relationship')
            ? $certificate->relationship
            : $certificate->relationship()->first();

        return $relationship?->mentor_id === $user->id;
    }
}
