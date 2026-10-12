<?php

return [
    'array' => 'The :attribute field must be an array.',
    'boolean' => 'The :attribute field must be true or false.',
    'date' => 'The :attribute field must be a valid date.',
    'date_format' => 'The :attribute field must match the format :format.',
    'email' => 'The :attribute field must be a valid email address.',
    'exists' => 'The selected :attribute is invalid.',
    'integer' => 'The :attribute field must be an integer.',
    'json' => 'The :attribute field must be a valid JSON string.',
    'numeric' => 'The :attribute field must be a number.',
    'regex' => 'The :attribute field format is invalid.',
    'string' => 'The :attribute field must be a string.',
    'unique' => 'The :attribute has already been taken.',
    'url' => 'The :attribute field must be a valid URL.',
    'uuid' => 'The :attribute field must be a valid UUID.',
    'between' => [
        'numeric' => 'The :attribute field must be between :min and :max.',
        'string' => 'The :attribute field must be between :min and :max characters.',
    ],
    'max' => [
        'numeric' => 'The :attribute field must not be greater than :max.',
        'string' => 'The :attribute field must not be greater than :max characters.',
    ],
    'min' => [
        'numeric' => 'The :attribute field must be at least :min.',
        'string' => 'The :attribute field must be at least :min characters.',
    ],
    'required' => 'The :attribute field is required.',
    'distinct' => 'The :attribute field contains duplicate values.',
    'in' => 'The selected :attribute is invalid.',
    'mentor_invalid' => 'The selected user is not a valid mentor.',
    'relationship_source_type_invalid' => 'The relationship context is invalid.',
    'mentor_client_type_invalid' => 'The mentor does not match the client type.',
    'relationship_exists' => 'An active relationship with this mentor already exists.',
];
