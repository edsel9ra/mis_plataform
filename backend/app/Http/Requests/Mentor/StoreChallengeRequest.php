<?php

namespace App\Http\Requests\Mentor;

use App\Http\Requests\Concerns\ValidatesMentorFiles;
use Illuminate\Foundation\Http\FormRequest;

class StoreChallengeRequest extends FormRequest
{
    use ValidatesMentorFiles;

    public function authorize(): bool
    {
        return (bool) $this->user()?->isMentor();
    }

    public function rules(): array
    {
        return array_merge([
            'title' => ['required_without:challenge_id', 'string', 'max:200'],
            'challenge_id' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string', 'max:20000'],
            'instructions' => ['nullable', 'string', 'max:20000'],
            'objective' => ['nullable', 'string', 'max:20000'],
            'success_criteria' => ['nullable', 'string', 'max:20000'],
            'presentation_format' => ['nullable', 'string', 'max:80'],
            'frequency' => ['nullable', 'string', 'max:80'],
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'due_at' => ['nullable', 'date'],
            'recording_url' => ['prohibited'],
            'media_url' => ['prohibited'],
            'attachment_url' => ['prohibited'],
            'status' => ['prohibited'],
        ], $this->mentorFilesRules());
    }
}
