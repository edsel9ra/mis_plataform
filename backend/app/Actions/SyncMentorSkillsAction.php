<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class SyncMentorSkillsAction
{
    public function execute(User $mentor, array $skills): User
    {
        return DB::transaction(function () use ($mentor, $skills): User {
            $tagIds = array_values(array_unique(array_column($skills, 'skill_tag_id')));

            if ($tagIds === []) {
                $mentor->skills()->delete();
            } else {
                $mentor->skills()->whereNotIn('skill_tag_id', $tagIds)->delete();
            }

            foreach ($skills as $skill) {
                $mentor->skills()->updateOrCreate(
                    ['skill_tag_id' => $skill['skill_tag_id']],
                    ['level' => $skill['level']],
                );
            }

            return $mentor->load('skills.skillTag');
        });
    }
}
