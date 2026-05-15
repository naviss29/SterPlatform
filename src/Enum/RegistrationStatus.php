<?php

namespace App\Enum;

enum RegistrationStatus: string
{
    case PENDING   = 'PENDING';
    case PAID      = 'PAID';
    case CANCELLED = 'CANCELLED';
}
