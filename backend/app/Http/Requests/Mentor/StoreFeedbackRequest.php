<?php

namespace App\Http\Requests\Mentor;

use App\Http\Requests\Concerns\ValidatesMentorFiles;
use Illuminate\Foundation\Http\FormRequest;

class StoreFeedbackRequest extends FormRequest
{
    use ValidatesMentorFiles;

    public function authorize(): bool
    {
        return (bool) $this->user()?->isMentor();
    }

    public function rules(): array
    {
        return array_merge([
            'user_id' => ['nullable', 'uuid'],
            'relationship_id' => ['nullable', 'uuid'],
            'challenge_id' => ['nullable', 'uuid'],
            'body' => ['nullable', 'string', 'max:20000'],
            'recording_url' => ['prohibited'],
            'media_url' => ['prohibited'],
            'attachment_url' => ['prohibited'],
        ], $this->mentorFilesRules());
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('body') && $this->has('note')) {
            $this->merge(['body' => $this->input('note')]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (trim((string) $this->input('body')) === ''
                && !$this->hasFile('files')
                && !$this->hasFile('file')
                && !$this->hasFile('feedback_file')) {
                $validator->errors()->add('body', 'A feedback message or file is required.');
            }
        });
    }
}
