<?php

return [
    'states' => [
        'value' => 'Has data',
        'zero' => 'Zero',
        'no_data' => 'No data',
        'incomplete' => 'Incomplete data',
        'suppressed' => 'Hidden for privacy',
        'blocked' => 'Not available in your plan',
    ],

    'periods' => [
        'current_month' => 'Current calendar month',
        'current_state' => 'Current state',
        'next_7_days' => 'Next 7 days',
    ],

    'blocks' => [
        'objectives' => 'Objectives',
        'sessions' => 'Sessions',
        'activities' => 'Activities',
        'competencies' => 'Competencies',
        'commitments' => 'Commitments',
        'collective' => 'Collective progress',
    ],

    'sources' => [
        'objectives' => 'Objectives',
        'sessions_attendance' => 'Sessions and attendance',
        'challenges_paths' => 'Challenges and learning paths',
        'skills' => 'Skills',
        'personality' => 'Personality',
    ],

    'competencies' => [
        'skills' => 'Skills',
        'personality' => 'Personality',
        'entitlement_missing' => "The mentee's plan does not include the personality assessment.",
        'not_completed' => 'No personality results yet.',
    ],

    'suppression' => [
        'insufficient_base' => 'At least :threshold participants are required to show this aggregate.',
    ],

    'collective' => [
        'mean' => 'Achievement mean',
        'sum' => 'Achieved objectives',
        'contributing_members' => 'Members with data',
        'eligible_members' => 'Eligible members',
    ],

    'messages' => [
        'access_denied' => 'You do not have access to this progress panel.',
        'not_visible' => 'The requested resource is not available.',
        'workspace_locked' => "The mentee's plan does not include the mentor workspace.",
        'no_active_process' => 'You do not have an active process yet. Complete your onboarding or explore mentors.',
    ],
];
