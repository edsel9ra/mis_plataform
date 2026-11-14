<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge(
            collect($this->resource->attributesToArray())->only([
                'id', 'user_id', 'relationship_id', 'type', 'title', 'description', 'metadata',
                'ipfs_cid', 'ipfs_uri', 'blockchain_tx_hash', 'blockchain_contract_address',
                'blockchain_token_id', 'issued_at', 'expires_at', 'revoked',
                'created_at', 'updated_at',
            ])->all(),
            $this->resource->relationsToArray(),
        );
    }
}
