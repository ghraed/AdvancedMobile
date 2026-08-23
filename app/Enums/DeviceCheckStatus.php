<?php

namespace App\Enums;

enum DeviceCheckStatus: string
{
    case TestedOk = 'tested_ok';
    case MinorIssue = 'minor_issue';
    case Fault = 'fault';
    case NotTested = 'not_tested';

    public function label(): string { return str($this->value)->replace('_', ' ')->headline()->toString(); }
}
