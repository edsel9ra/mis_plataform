<?php

namespace App\Http\Requests\Mentor;

use App\Enums\SkillLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncMentorSkillsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (!$user) {
            return false;
        }

        return $this->route('id') !== null ? $user->isAdmin() : $user->isMentor();
    }

    public function rules(): array
    {
        return [
            'skills' => ['present', 'array', 'max:20'],
            'skills.*.skill_tag_id' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('skill_tags', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'skills.*.level' => ['required', Rule::enum(SkillLevel::class)],
        ];
    }
}
