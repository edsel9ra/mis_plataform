<?php

return [
    'states' => [
        'value' => 'Con datos',
        'zero' => 'Cero',
        'no_data' => 'Sin datos',
        'incomplete' => 'Datos incompletos',
        'suppressed' => 'Oculto por privacidad',
        'blocked' => 'No disponible en tu plan',
    ],

    'periods' => [
        'current_month' => 'Mes calendario actual',
        'current_state' => 'Estado actual',
        'next_7_days' => 'Próximos 7 días',
    ],

    'blocks' => [
        'objectives' => 'Objetivos',
        'sessions' => 'Sesiones',
        'activities' => 'Actividades',
        'competencies' => 'Competencias',
        'commitments' => 'Compromisos',
        'collective' => 'Progreso del colectivo',
    ],

    'sources' => [
        'objectives' => 'Objetivos',
        'sessions_attendance' => 'Sesiones y asistencia',
        'challenges_paths' => 'Retos y rutas de aprendizaje',
        'skills' => 'Habilidades',
        'personality' => 'Personalidad',
    ],

    'competencies' => [
        'skills' => 'Habilidades',
        'personality' => 'Personalidad',
        'entitlement_missing' => 'El plan del mentee no incluye la evaluación de personalidad.',
        'not_completed' => 'Todavía no hay resultados de personalidad.',
    ],

    'suppression' => [
        'insufficient_base' => 'Se necesitan al menos :threshold participantes para mostrar este agregado.',
    ],

    'collective' => [
        'mean' => 'Media de logro',
        'sum' => 'Objetivos logrados',
        'contributing_members' => 'Miembros con datos',
        'eligible_members' => 'Miembros elegibles',
    ],

    'messages' => [
        'access_denied' => 'No tienes acceso a este panel de progreso.',
        'not_visible' => 'El recurso solicitado no está disponible.',
        'workspace_locked' => 'El plan del mentee no incluye el espacio del mentor.',
        'no_active_process' => 'Todavía no tienes un proceso activo. Completa tu onboarding o explora mentores.',
    ],
];
