<?php

namespace App\Services\Progress\Map;

/**
 * Element types placeable under a module in the learning map. Only
 * challenges have a registered link today (Spec 012); new types are added
 * here, never inferred (RF-013.3/.4).
 */
enum MapElementType: string
{
    case Challenge = 'challenge';
}
