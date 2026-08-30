<?php

namespace App\Enums;

enum AccessScopeType: string
{
    case All = 'all';
    case Province = 'province';
    case District = 'district';
}
