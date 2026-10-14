<?php

namespace App\Enums;

enum Difficulty: string
{
    case VeryEasy = 'very_easy';
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    public function label(): string
    {
        return match ($this) {
            self::VeryEasy => 'Sehr einfach',
            self::Easy => 'Einfach',
            self::Medium => 'Mittel',
            self::Hard => 'Anspruchsvoll',
        };
    }
}
