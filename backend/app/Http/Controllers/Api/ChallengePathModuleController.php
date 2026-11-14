<?php

namespace App\Http\Controllers\Api;

use App\Actions\SetChallengePathModuleAction;
use App\Http\Requests\Challenge\SetChallengePathModuleRequest;
use App\Models\Challenge;
use Illuminate\Http\JsonResponse;

class ChallengePathModuleController
{
    public function __construct(
        private SetChallengePathModuleAction $setModule,
    ) {}

    /**
     * PUT /challenges/{challenge}/path-module — set or remove the explicit
     * challenge → path-module link. Authorization (actor vs challenge,
     * target and current module visibility) happens inside the action and
     * denies with a 404 so the link is never revealed (RF-012.8-.10).
     */
    public function update(SetChallengePathModuleRequest $request, Challenge $challenge): JsonResponse
    {
        $challenge = $this->setModule->execute(
            $challenge,
            $request->user(),
            $request->validated('path_module_id'),
            now(),
        );

        $module = $challenge->pathModule;

        return response()->json([
            'challenge_id' => $challenge->id,
            'path_module' => $module === null ? null : [
                'id' => $module->id,
                'title' => $module->title,
                'learning_path_id' => $module->learning_path_id,
            ],
        ]);
    }
}
