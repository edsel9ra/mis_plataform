<?php

return [
    'scopes' => [
        'individual' => 'Individual',
        'collective' => 'Collective',
    ],

    'statuses' => [
        'not_started' => 'Not started',
        'in_progress' => 'In progress',
        'achieved' => 'Achieved',
        'abandoned' => 'Abandoned',
    ],

    'validation' => [
        'success_criteria_required' => 'The success criteria is required.',
        'owner_required' => 'You must provide an owner or a responsible collective.',
        'owner_conflict' => 'You cannot provide both an owner and a responsible collective.',
        'owner_not_managed' => 'You do not manage the requested collective.',
        'invalid_transition' => 'The objective cannot move from :from to :to.',
    ],

    'activity' => [
        'status_changed' => 'Objective status updated',
    ],

    'messages' => [
        'created' => 'Objective created successfully.',
        'updated' => 'Objective updated successfully.',
        'deleted' => 'Objective deleted successfully.',
    ],
];
