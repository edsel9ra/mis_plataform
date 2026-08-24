<?php

namespace App\Http\Requests\Concerns;

trait NormalizesEmail
{
    protected function normalizeEmailInput(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => mb_strtolower(trim((string) $this->input('email'))),
            ]);
        }
    }
}
