<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'relationship_id' => $this->relationship_id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'ipfs_cid' => $this->ipfs_cid,
            'ipfs_uri' => $this->ipfs_uri,
            'blockchain_tx_hash' => $this->blockchain_tx_hash,
            'blockchain_contract_address' => $this->blockchain_contract_address,
            'revoked' => $this->revoked,
            'issued_at' => $this->issued_at,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'user' => UserResource::make($this->whenLoaded('user')),
            'relationship' => RelationshipResource::make($this->whenLoaded('relationship')),
        ];
    }
}
