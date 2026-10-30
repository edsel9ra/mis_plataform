<?php

namespace App\Http\Requests\Session;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:attended,absent'],
            'attendee_user_id' => ['sometimes', 'uuid'],
        ];
    }
}
