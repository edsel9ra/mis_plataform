<?php

namespace App\Http\Requests\Concerns;

trait ValidatesMentorFiles
{
    /** @return array<int, string> */
    protected function mentorFileRules(): array
    {
        return [
            'file',
            'mimetypes:' . implode(',', config('mentor.media.mimetypes', [])),
            'max:' . (int) config('mentor.media.max_size_kb', 25600),
        ];
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
