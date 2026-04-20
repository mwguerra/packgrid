<?php

namespace App\Enums;

enum GitProvider: string
{
    case GitHub = 'github';
    case GitLab = 'gitlab';
}
