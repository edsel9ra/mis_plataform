<?php

use App\Support\LikePattern;

it('escapes LIKE wildcards in contains patterns', function () {
    expect(LikePattern::contains('100%'))->toBe('%100\\%%')
        ->and(LikePattern::contains('a_b'))->toBe('%a\\_b%')
        ->and(LikePattern::contains('a\\b'))->toBe('%a\\\\b%')
        ->and(LikePattern::contains('plain'))->toBe('%plain%');
});
