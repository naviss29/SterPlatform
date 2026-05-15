<?php

namespace App\Enum;

enum FinishType: string
{
    case SINGLE = 'SINGLE';
    case DOUBLE = 'DOUBLE';
    case TRIPLE = 'TRIPLE';
    case MASTER = 'MASTER';
}
