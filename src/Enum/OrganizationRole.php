<?php

namespace App\Enum;

enum OrganizationRole: string
{
    case OWNER  = 'OWNER';
    case ADMIN  = 'ADMIN';
    case MEMBER = 'MEMBER';
}
