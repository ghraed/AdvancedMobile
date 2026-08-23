<?php

namespace App\Enums;

enum CompatibilityRuleType: string
{
    case ModelIdentifier = 'model_identifier';
    case ModelFamily = 'model_family';
    case Connector = 'connector';
    case ChargingStandard = 'charging_standard';
    case Feature = 'feature';
}
