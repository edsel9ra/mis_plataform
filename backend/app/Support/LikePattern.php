<?php

namespace App\Support;

/**
 * Escapes LIKE/ILIKE wildcards so user input cannot widen a `contains` match.
 * Postgres treats backslash as the default escape character in LIKE patterns.
 */
class LikePattern
{
    public static function contains(string $term): string
    {
        return '%'.strtr($term, [
            '\\' => '\\\\',
            '%' => '\\%',
            '_' => '\\_',
        ]).'%';
    }
}
