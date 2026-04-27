<?php

namespace Modules\Borme\Enums;

enum OfficerAction: string
{
    case Appointment = 'appointment';
    case Cease = 'cease';
    case Reelection = 'reelection';
    case Revocation = 'revocation';
}
