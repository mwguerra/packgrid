<?php

namespace App\Enums;

enum SyncStatus: string
{
    case Success = 'success';
    case Fail = 'fail';
}
