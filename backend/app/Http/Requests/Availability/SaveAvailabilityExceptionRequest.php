<?php

namespace App\Http\Requests\Availability;

use App\Models\MentorAvailabilityException;
use App\Models\Session;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveAvailabilityExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $exception = $this->route('exception');
        $availability = $exception instanceof MentorAvailabilityException ? $exception : $this->user();

        Gate::authorize('manageAvailability', [Session::class, $availability]);

        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['prohibited'],
            'mode' => ['required', 'string', 'in:block,open'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'required_with:end_time', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_with:start_time', 'date_format:H:i'],
            'timezone' => ['required', 'string', 'timezone'],
            'reason' => ['nullable', 'string'],
        ];
    }

}
