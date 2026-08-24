<?php

return [
    'array' => 'El campo :attribute debe ser un arreglo.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'date' => 'El campo :attribute debe ser una fecha válida.',
    'date_format' => 'El campo :attribute debe coincidir con el formato :format.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'exists' => 'El :attribute seleccionado no es válido.',
    'integer' => 'El campo :attribute debe ser un entero.',
    'json' => 'El campo :attribute debe ser un JSON válido.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'regex' => 'El formato del campo :attribute no es válido.',
    'string' => 'El campo :attribute debe ser texto.',
    'unique' => 'El :attribute ya está registrado.',
    'url' => 'El campo :attribute debe ser una URL válida.',
    'uuid' => 'El campo :attribute debe ser un UUID válido.',
    'between' => [
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
    ],
    'max' => [
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'string' => 'El campo :attribute no puede superar :max caracteres.',
    ],
    'min' => [
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'required' => 'El campo :attribute es obligatorio.',
    'distinct' => 'El campo :attribute contiene valores duplicados.',
    'in' => 'El valor seleccionado para :attribute no es válido.',
    'mentor_invalid' => 'El usuario seleccionado no es un mentor válido.',
    'relationship_source_type_invalid' => 'El contexto de relación no es válido.',
    'mentor_client_type_invalid' => 'El mentor no corresponde al tipo de cliente.',
    'relationship_exists' => 'Ya existe una relación activa con este mentor.',
];
