<?php

namespace App\Http\Requests\Admin;

class UpdateEvaluationCatalogRequest extends StoreEvaluationCatalogRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'slug' => ['required', 'string', 'max:120', 'unique:assessments,slug,'.$this->route('id')],
        ]);
    }
}
