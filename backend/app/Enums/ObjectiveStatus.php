<?php

namespace App\Enums;

enum ObjectiveStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Achieved = 'achieved';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => __('objectives.statuses.not_started'),
            self::InProgress => __('objectives.statuses.in_progress'),
            self::Achieved => __('objectives.statuses.achieved'),
            self::Abandoned => __('objectives.statuses.abandoned'),
        };
    }

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::NotStarted => [self::InProgress, self::Achieved, self::Abandoned],
            self::InProgress => [self::Achieved, self::Abandoned, self::NotStarted],
            self::Achieved => [self::InProgress, self::NotStarted, self::Abandoned],
            self::Abandoned => [self::NotStarted, self::InProgress],
        };
    }

    public function canTransitionTo(self|string $target): bool
    {
        $target = $target instanceof self ? $target : self::tryFrom($target);

        if ($target === null || $target === $this) {
            return false;
        }

        return in_array($target, $this->allowedTransitions(), true);
    }
}
