<?php

namespace App\Http\Requests\Concerns;

use App\Support\MediaUploadRules;

trait ValidatesMentorFiles
{
    /** @return array<int, string> */
    protected function mentorFileRules(): array
    {
        return MediaUploadRules::file();
    }

    /** @return array<string, array<int, string>> */
    protected function mentorFilesRules(): array
    {
        return [
            'files' => ['sometimes', 'array', 'max:' . (int) config('mentor.media.max_files', 5)],
            'files.*' => $this->mentorFileRules(),
            'file' => $this->mentorFileRules(),
            'content_file' => $this->mentorFileRules(),
            'feedback_file' => $this->mentorFileRules(),
        ];
    }

}
