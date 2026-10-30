<?php

namespace App\Http\Requests\Session;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class IndexSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isoTimestamp = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/';

        return [
            'from' => ['required_with:to', 'string', 'regex:'.$isoTimestamp, 'date'],
            'to' => ['required_with:from', 'string', 'regex:'.$isoTimestamp, 'date'],
            'include_collectives' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled(['from', 'to'])) {
                return;
            }

            if (CarbonImmutable::parse($this->string('from')->toString())->greaterThanOrEqualTo(
                CarbonImmutable::parse($this->string('to')->toString()),
            )) {
                $validator->errors()->add('to', __('validation.after', ['attribute' => 'to', 'date' => 'from']));
            }
        });
    }
}
