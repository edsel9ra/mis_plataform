<?php

use App\Services\Scheduling\Rules\SessionTypeRules;

uses()->group('scheduling', 'booking');

it('maps each relationship type to the session type POST /sessions expects', function (string $type, ?string $expected) {
    expect(SessionTypeRules::forRelationshipType($type))->toBe($expected);
})->with([
    ['personal', 'individual'],
    ['familiar', 'family'],
    ['grupal', 'group'],
    ['empresa', 'corporate'],
    ['unknown', null],
    ['', null],
]);
