<?php

namespace App\Enums;

enum ProductType: string
{
    case Device = 'device';
    case Accessory = 'accessory';
    case Other = 'other';
}
