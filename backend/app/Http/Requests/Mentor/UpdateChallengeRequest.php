<?php

namespace App\Http\Requests\Mentor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isMentor();
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:20000'],
            'instructions' => ['nullable', 'string', 'max:20000'],
            'objective' => ['nullable', 'string', 'max:20000'],
            'success_criteria' => ['nullable', 'string', 'max:20000'],
            'presentation_format' => ['nullable', 'string', 'max:80'],
            'frequency' => ['nullable', 'string', 'max:80'],
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'status' => ['sometimes', 'string', 'in:template,assigned,in_progress,completed,canceled'],
            'due_at' => ['nullable', 'date'],
            'recording_url' => ['prohibited'],
            'media_url' => ['prohibited'],
            'attachment_url' => ['prohibited'],
            'template_id' => ['prohibited'],
            'relationship_id' => ['prohibited'],
            'mentor_id' => ['prohibited'],
            'mentee_id' => ['prohibited'],
            'completed_at' => ['prohibited'],
        ];
    }
}
