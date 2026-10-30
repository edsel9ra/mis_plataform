<?php

namespace App\Http\Requests\Availability;

use App\Models\MentorAvailabilityWindow;
use App\Models\Session;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class SaveAvailabilityWindowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $window = $this->route('window');
        $availability = $window instanceof MentorAvailabilityWindow ? $window : $this->user();

        Gate::authorize('manageAvailability', [Session::class, $availability]);

        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['prohibited'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'string', 'timezone'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $start = $this->input('start_time');
            $end = $this->input('end_time');

            if (is_string($start) && is_string($end) && $end <= $start) {
                $validator->errors()->add('end_time', __('validation.availability_time_order'));

                return;
            }

            if ($validator->errors()->isNotEmpty() || ! is_string($start) || ! is_string($end)) {
                return;
            }

            $window = $this->route('window');
            $query = MentorAvailabilityWindow::query()
                ->where('user_id', $this->user()->id)
                ->where('day_of_week', $this->input('day_of_week'))
                ->where('start_time', '<', $end)
                ->where('end_time', '>', $start);

            if ($window instanceof MentorAvailabilityWindow) {
                $query->where('id', '<>', $window->getKey());
            }

            if ($query->exists()) {
                $validator->errors()->add('day_of_week', __('validation.availability_window_overlap'));
            }
        }];
    }
}
