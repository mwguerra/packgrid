<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as FilamentLogin;

class Login extends FilamentLogin
{
    public function getSubheading(): string
    {
        return __('auth.login.subheading');
    }
}
