<?php

namespace App\Http\Requests\Progress;

/**
 * Support history page (RF-015.4): the subject is an explicit user_id so
 * the support view is deliberate and auditable.
 */
class AdminProgressHistoryRequest extends ProgressHistoryRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'user_id' => ['required', 'uuid'],
        ]);
    }
}
