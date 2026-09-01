<?php

namespace App\Http\Requests\Mentee;

use App\Http\Requests\Concerns\ValidatesMentorFiles;
use Illuminate\Foundation\Http\FormRequest;

class StoreChallengeSubmissionRequest extends FormRequest
{
    use ValidatesMentorFiles;

    public function authorize(): bool
    {
        return (bool) $this->user() && !$this->user()->isMentor();
    }

    public function rules(): array
    {
        return array_merge([
            'note' => ['nullable', 'string', 'max:20000'],
            'recording_url' => ['prohibited'],
            'media_url' => ['prohibited'],
            'attachment_url' => ['prohibited'],
        ], $this->mentorFilesRules());
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (trim((string) $this->input('note')) === ''
                && !$this->hasFile('files')
                && !$this->hasFile('file')
                && !$this->hasFile('content_file')) {
                $validator->errors()->add('note', 'A submission note or file is required.');
            }
        });
    }
}
