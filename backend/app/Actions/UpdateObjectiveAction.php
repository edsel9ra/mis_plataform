<?php

namespace App\Actions;

use App\Models\Objective;
use App\Models\User;

class UpdateObjectiveAction
{
    /**
     * Update the administrable fields only. `owner`, `scope`, `status` and the
     * proposal metadata are never altered here (RF-1).
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Objective $objective, array $data): Objective
    {
        $objective->fill([
            'title' => $data['title'] ?? $objective->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $objective->description,
            'success_criteria' => $data['success_criteria'] ?? $objective->success_criteria,
            'due_at' => array_key_exists('due_at', $data) ? $data['due_at'] : $objective->due_at,
        ]);

        $objective->save();

        return $objective->refresh();
    }
}
