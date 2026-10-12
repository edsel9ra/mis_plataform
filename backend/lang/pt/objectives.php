<?php

return [
    'scopes' => [
        'individual' => 'Individual',
        'collective' => 'Coletivo',
    ],

    'statuses' => [
        'not_started' => 'Não iniciado',
        'in_progress' => 'Em andamento',
        'achieved' => 'Alcançado',
        'abandoned' => 'Abandonado',
    ],

    'validation' => [
        'success_criteria_required' => 'O critério de sucesso é obrigatório.',
        'owner_required' => 'Você deve indicar um titular ou um coletivo responsável.',
        'owner_conflict' => 'Você não pode indicar titular e coletivo responsável ao mesmo tempo.',
        'owner_not_managed' => 'Você não administra o coletivo indicado.',
        'invalid_transition' => 'O objetivo não pode passar de :from para :to.',
    ],

    'activity' => [
        'status_changed' => 'Status do objetivo atualizado',
    ],

    'messages' => [
        'created' => 'Objetivo criado com sucesso.',
        'updated' => 'Objetivo atualizado com sucesso.',
        'deleted' => 'Objetivo excluído com sucesso.',
    ],
];
