<?php

namespace App\Http\Requests\Mentor;

use App\Http\Requests\Concerns\ValidatesMentorFiles;
use Illuminate\Foundation\Http\FormRequest;

class UploadMediaRequest extends FormRequest
{
    use ValidatesMentorFiles;

    public function authorize(): bool
    {
        return (bool) $this->user()?->isMentor();
    }

    public function rules(): array
    {
        return array_merge([
            'relationship_id' => ['nullable', 'uuid', 'exists:mentorship_relationships,id'],
            'purpose' => ['required', 'string', 'in:challenge_content,mentor_feedback'],
            'challenge_id' => ['required_if:purpose,challenge_content', 'nullable', 'uuid'],
            'feedback_entry_id' => ['required_if:purpose,mentor_feedback', 'nullable', 'uuid'],
            'recording_url' => ['prohibited'],
            'media_url' => ['prohibited'],
            'attachment_url' => ['prohibited'],
            'files' => ['prohibited'],
        ], [
            'file' => array_merge(['required'], $this->mentorFileRules()),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('purpose') === 'mentor_feedback' && !$this->filled('relationship_id')) {
                $validator->errors()->add('relationship_id', 'A relationship is required for mentor feedback.');
            }
        });
    }
}
