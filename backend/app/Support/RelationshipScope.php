<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RelationshipScope
{
    public static function id(Request $request): string
    {
        return Validator::make($request->all(), [
            'relationship_id' => ['required', 'uuid'],
        ])->validated()['relationship_id'];
    }
}
