<?php

namespace App\Http\Requests\Mentor;

class StoreGeneralFeedbackRequest extends StoreFeedbackRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'user_id' => ['required', 'uuid'],
            'relationship_id' => ['required', 'uuid'],
        ]);
    }
}
