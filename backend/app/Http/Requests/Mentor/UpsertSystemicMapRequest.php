<?php

namespace App\Http\Requests\Mentor;

use Illuminate\Foundation\Http\FormRequest;

class UpsertSystemicMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isMentor();
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:20000'],
            'is_private' => ['prohibited'],
            'nodes' => ['sometimes', 'array', 'max:100'],
            'nodes.*.key' => ['nullable', 'string', 'max:100'],
            'nodes.*.id' => ['nullable', 'string', 'max:100'],
            'nodes.*.label' => ['required_with:nodes', 'string', 'max:200'],
            'nodes.*.kind' => ['nullable', 'string', 'max:50'],
            'nodes.*.position' => ['nullable', 'array'],
            'nodes.*.x' => ['nullable', 'numeric'],
            'nodes.*.y' => ['nullable', 'numeric'],
            'nodes.*.color' => ['nullable', 'string', 'max:30'],
            'nodes.*.notes' => ['nullable', 'string', 'max:5000'],
            'edges' => ['sometimes', 'array', 'max:200'],
            'edges.*.from' => ['nullable', 'string', 'max:100'],
            'edges.*.to' => ['nullable', 'string', 'max:100'],
            'edges.*.from_node_id' => ['nullable', 'uuid'],
            'edges.*.to_node_id' => ['nullable', 'uuid'],
            'edges.*.source' => ['nullable', 'string', 'max:100'],
            'edges.*.target' => ['nullable', 'string', 'max:100'],
            'edges.*.label' => ['nullable', 'string', 'max:200'],
            'edges.*.metadata' => ['nullable', 'array'],
        ];
    }
}
