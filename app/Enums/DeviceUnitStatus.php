<?php

namespace App\Enums;

enum DeviceUnitStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Repair = 'repair';
    case Returned = 'returned';
    case Retired = 'retired';

    public function label(): string { return str($this->value)->headline()->toString(); }
}
