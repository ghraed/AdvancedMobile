<?php

namespace App\Enums;

enum DeviceConditionGrade: string
{
    case A = 'a';
    case B = 'b';
    case C = 'c';
    case D = 'd';

    public function label(): string
    {
        return match ($this) {
            self::A => 'A / Excellent', self::B => 'B / Very Good',
            self::C => 'C / Good', self::D => 'D / Fair',
        };
    }
}
