<?php

namespace App\Support;

/**
 * Shared validation rules for user-uploaded media files. Single source of
 * truth for the allowed MIME types and the 25 MB cap, used by both the
 * mentor media uploads and the objective-evidence media uploads.
 */
final class MediaUploadRules
{
    /** @return array<int, string> */
    public static function file(): array
    {
        return [
            'file',
            'mimetypes:' . implode(',', config('mentor.media.mimetypes', [])),
            'max:' . (int) config('mentor.media.max_size_kb', 25600),
        ];
    }
}
