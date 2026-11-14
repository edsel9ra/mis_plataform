<?php

namespace App\Http\Requests\Availability;

use App\Models\Session;
use App\Services\Scheduling\Rules\TimezoneRules;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class GetAvailabilitySlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $mentor = $this->route('mentor');

        Gate::authorize('viewAvailabilitySlots', [Session::class, $mentor]);

        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'duration_minutes' => [
                'required',
                'integer',
                'min:'.config('scheduling.min_duration_minutes', 15),
                'max:'.config('scheduling.max_duration_minutes', 480),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('from') || $validator->errors()->has('to')) {
                return;
            }

            $timezone = TimezoneRules::resolveProfileTimezone(
                $this->user()?->timezone,
                config('scheduling.default_timezone'),
            );
            $from = CarbonImmutable::parse($this->input('from'), $timezone);
            $to = CarbonImmutable::parse($this->input('to'), $timezone);

            if ($to->lessThanOrEqualTo($from)) {
                $validator->errors()->add('to', __('validation.after', ['attribute' => 'to', 'date' => 'from']));

                return;
            }

            $maxDays = (int) config('scheduling.slots_max_range_days', 31);

            if ($to->getTimestamp() - $from->getTimestamp() > $maxDays * 86400) {
                $validator->errors()->add('to', __('validation.availability_slots_range', ['days' => $maxDays]));
            }
        }];
    }
}
