<?php

return [
    'array' => 'O campo :attribute deve ser um array.',
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'date' => 'O campo :attribute deve ser uma data válida.',
    'date_format' => 'O campo :attribute deve corresponder ao formato :format.',
    'email' => 'O campo :attribute deve ser um e-mail válido.',
    'exists' => 'O :attribute selecionado é inválido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'json' => 'O campo :attribute deve ser um JSON válido.',
    'numeric' => 'O campo :attribute deve ser um número.',
    'regex' => 'O formato do campo :attribute é inválido.',
    'string' => 'O campo :attribute deve ser texto.',
    'unique' => 'O :attribute já foi utilizado.',
    'url' => 'O campo :attribute deve ser uma URL válida.',
    'uuid' => 'O campo :attribute deve ser um UUID válido.',
    'between' => [
        'numeric' => 'O campo :attribute deve estar entre :min e :max.',
        'string' => 'O campo :attribute deve ter entre :min e :max caracteres.',
    ],
    'max' => [
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ultrapassar :max caracteres.',
    ],
    'min' => [
        'numeric' => 'O campo :attribute deve ter pelo menos :min.',
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],
    'required' => 'O campo :attribute é obrigatório.',
    'distinct' => 'O campo :attribute contém valores duplicados.',
    'in' => 'O valor selecionado para :attribute é inválido.',
    'mentor_invalid' => 'O usuário selecionado não é um mentor válido.',
    'relationship_source_type_invalid' => 'O contexto do relacionamento é inválido.',
    'mentor_client_type_invalid' => 'O mentor não corresponde ao tipo de cliente.',
    'relationship_exists' => 'Já existe um relacionamento ativo com este mentor.',
];
