<?php

namespace App\Enums;

enum CredentialStatus: string
{
    case Ok = 'ok';
    case Fail = 'fail';
    case Unknown = 'unknown';
}
