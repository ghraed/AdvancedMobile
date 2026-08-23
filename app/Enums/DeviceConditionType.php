<?php

namespace App\Enums;

enum DeviceConditionType: string
{
    case New = 'new';
    case Used = 'used';
    case Refurbished = 'refurbished';

    public function label(): string { return str($this->value)->headline()->toString(); }
}
