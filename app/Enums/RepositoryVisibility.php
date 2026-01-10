<?php

namespace App\Enums;

enum RepositoryVisibility: string
{
    case PublicRepo = 'public';
    case PrivateRepo = 'private';
}
