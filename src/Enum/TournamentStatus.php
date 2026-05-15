<?php

namespace App\Enum;

enum TournamentStatus: string
{
    case DRAFT       = 'DRAFT';
    case OPEN        = 'OPEN';
    case IN_PROGRESS = 'IN_PROGRESS';
    case FINISHED    = 'FINISHED';
}
