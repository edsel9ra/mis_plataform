<?php

namespace App\Http\Resources;

use App\Services\RelationshipViewerFeatures;
use Illuminate\Http\Request;

class ViewerRelationshipResource extends RelationshipResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'features' => app(RelationshipViewerFeatures::class)->for($request->user(), $this->resource),
        ]);
    }
}
