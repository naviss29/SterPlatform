<?php

namespace App\Enum;

enum MatchStatus: string
{
    case PENDING     = 'PENDING';
    case IN_PROGRESS = 'IN_PROGRESS';
    case FINISHED    = 'FINISHED';
}
