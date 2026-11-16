<?php

namespace App\Http\Requests\Progress;

use App\Services\Progress\Calculators\ProgressHistoryCalculator;
use App\Services\Progress\History\ProgressHistoryService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query payload for the unified history page (RF-015.1/.7/.8). The subject
 * never comes from the client on the own route — it is resolved by the
 * route or by the admin user_id rule in the subclass. Authorization lives
 * in ProgressAccessService.
 */
class ProgressHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cursor' => [
                'bail',
                'nullable',
                'string',
                'max:512',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (ProgressHistoryCalculator::decodeCursor((string) $value) === null) {
                        $fail(__('validation.progress_history_cursor'));
                    }
                },
            ],
            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:'.ProgressHistoryService::MAX_LIMIT,
            ],
        ];
    }

    public function cursor(): ?string
    {
        return $this->validated('cursor');
    }

    public function limit(): int
    {
        return (int) ($this->validated('limit') ?? ProgressHistoryService::DEFAULT_LIMIT);
    }
}
