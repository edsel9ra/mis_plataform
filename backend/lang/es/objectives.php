<?php

return [
    'scopes' => [
        'individual' => 'Individual',
        'collective' => 'Colectivo',
    ],

    'statuses' => [
        'not_started' => 'No iniciado',
        'in_progress' => 'En progreso',
        'achieved' => 'Logrado',
        'abandoned' => 'Abandonado',
    ],

    'validation' => [
        'success_criteria_required' => 'El criterio de logro es obligatorio.',
        'owner_required' => 'Debes indicar un titular o un colectivo responsable.',
        'owner_conflict' => 'No puedes indicar titular y colectivo responsable a la vez.',
        'owner_not_managed' => 'No administras el colectivo indicado.',
        'invalid_transition' => 'El objetivo no puede pasar de :from a :to.',
    ],

    'activity' => [
        'status_changed' => 'Estado del objetivo actualizado',
    ],

    'messages' => [
        'created' => 'Objetivo creado correctamente.',
        'updated' => 'Objetivo actualizado correctamente.',
        'deleted' => 'Objetivo eliminado correctamente.',
    ],
];
