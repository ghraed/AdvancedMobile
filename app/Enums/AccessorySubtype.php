<?php

namespace App\Enums;

enum AccessorySubtype: string
{
    case Case = 'case';
    case ScreenProtector = 'screen_protector';
    case Charger = 'charger';
    case Cable = 'cable';
    case PowerBank = 'power_bank';
    case WirelessCharger = 'wireless_charger';
    case Headphones = 'headphones';
    case Adapter = 'adapter';
    case Other = 'other';
}
